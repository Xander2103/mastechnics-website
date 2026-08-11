<?php

namespace App\Services\Hvac;

use App\Services\Hvac\Input\HvacRoomInput;

/**
 * Deterministic cooling-load calculation for one room.
 *
 * The rule set's `load_method` selects the model:
 *
 * - simple_v1 (default, rule sets without the key):
 *   base_watts = area × insulation W/m² × ceiling factor × orientation factor
 *                × window factor × roof factor
 *   final_watts = base_watts + occupancy heat + equipment heat
 *
 * - engineering_v2 ("Belgische residentiële koellast", reverse-engineered
 *   from the reference workbook — docs/hvac/excel-calculator-audit.md):
 *   Qtrans = Ueq × envelope × ΔT          (envelope = 2·H·(L+B) + floor area)
 *   Qsol   = window area × qsol(orientation) × Fs(shading)
 *   Qs     = Qtrans + Qsol + people·75 + equipment + 2.67·ACH·V
 *   Ql     = people·55 + 1.3·ACH·V
 *   design = (Qs + Ql) × safety factor
 *
 * The two methods are never mixed within one calculation. Every intermediate
 * value is returned so the admin can always see exactly how the final number
 * was obtained. This is a pre-calculation aid, not certified engineering
 * software.
 */
class CoolingLoadCalculator
{
    public function calculateRoom(HvacRoomInput $room, array $rules): array
    {
        if (($rules['load_method'] ?? 'simple_v1') === 'engineering_v2') {
            return $this->calculateEngineeringV2($room, $rules);
        }

        return $this->calculateSimpleV1($room, $rules);
    }

    private function calculateSimpleV1(HvacRoomInput $room, array $rules): array
    {
        $wPerM2 = (float) ($rules['insulation_w_per_m2'][$room->insulation]
            ?? $rules['insulation_w_per_m2']['average']);

        $referenceHeight = (float) ($rules['ceiling_reference_height_m'] ?? 2.5);
        $ceilingFactor = round($room->heightM / $referenceHeight, 3);

        $orientationFactor = (float) ($rules['orientation_factors'][$room->orientation]
            ?? $rules['orientation_factors']['unknown'] ?? 1.0);

        $windowFactor = $this->windowFactor($room, $rules);

        $roofFactor = (float) ($rules['roof_type_factors'][$room->roofType]
            ?? $rules['roof_type_factors']['unknown'] ?? 1.0);

        $baseWatts = $room->areaM2 * $wPerM2 * $ceilingFactor * $orientationFactor
            * $windowFactor * $roofFactor;

        $occupancyRules = $rules['occupancy'] ?? [];
        $includedPersons = (int) ($occupancyRules['included_persons'] ?? 1);
        $wattsPerExtraPerson = (float) ($occupancyRules['watts_per_extra_person'] ?? 120);
        $occupancyHeat = max(0, $room->occupants - $includedPersons) * $wattsPerExtraPerson;

        $equipmentHeatMap = $rules['equipment_heat_w'] ?? [];
        $equipmentBreakdown = [];
        $equipmentHeat = 0.0;
        foreach ($room->equipment as $equipment) {
            $watts = (float) ($equipmentHeatMap[$equipment] ?? 0);
            $equipmentBreakdown[$equipment] = $watts;
            $equipmentHeat += $watts;
        }

        $finalWatts = (int) round($baseWatts + $occupancyHeat + $equipmentHeat);
        $finalKw = round($finalWatts / 1000, 2);

        return [
            'method'              => 'simple_v1',
            'room_index'          => $room->index,
            'room_name'           => $room->name,
            'area_m2'             => $room->areaM2,
            'height_m'            => $room->heightM,
            'insulation'          => $room->insulation,
            'insulation_w_per_m2' => $wPerM2,
            'ceiling_factor'      => $ceilingFactor,
            'orientation'         => $room->orientation,
            'orientation_factor'  => $orientationFactor,
            'window_type'         => $room->windowType,
            'window_factor'       => $windowFactor,
            'roof_type'           => $room->roofType,
            'roof_factor'         => $roofFactor,
            'base_watts'          => round($baseWatts, 1),
            'occupants'           => $room->occupants,
            'occupants_assumed'   => $room->occupantsAssumed,
            'occupancy_heat_w'    => round($occupancyHeat, 1),
            'equipment'           => $equipmentBreakdown,
            'equipment_assumed'   => $room->equipmentAssumed,
            'equipment_heat_w'    => round($equipmentHeat, 1),
            'final_watts'         => $finalWatts,
            'final_kw'            => $finalKw,
        ];
    }

    /**
     * Engineering model v2. All intermediates are returned unrounded enough
     * (2–3 decimals) to be auditable against the reference workbook;
     * final_watts / final_kw keep their v1 names and rounding so class
     * selection, overrides and product selection work unchanged.
     */
    private function calculateEngineeringV2(HvacRoomInput $room, array $rules): array
    {
        // Geometry. Envelope = 4 walls + ceiling (no floor), like the workbook.
        $area = $room->areaM2 > 0 ? $room->areaM2 : round($room->widthM * $room->lengthM, 2);
        $volume = round($area * $room->heightM, 3);
        $envelope = round(2 * $room->heightM * ($room->lengthM + $room->widthM) + $area, 3);

        // Transmission through the envelope at a fixed design ΔT.
        $ueqMap = $rules['u_equivalent_by_insulation'] ?? [];
        $ueq = (float) ($ueqMap[$room->insulation] ?? $ueqMap['average'] ?? 0.9);
        $deltaT = (float) ($rules['design_delta_t_k'] ?? 8.0);
        $qTransmission = $ueq * $envelope * $deltaT;

        // Solar gain through glass: orientation-specific irradiance × shading.
        $solarMap = $rules['solar_gain_w_per_m2_by_orientation'] ?? [];
        $solarPerM2 = (float) ($solarMap[$room->orientation] ?? $solarMap['unknown'] ?? 300);

        // The form never asks shading — the rule-set assumption applies.
        $shading = (string) ($rules['assumed_shading'] ?? 'none');
        $shadingFactor = (float) (($rules['shading_factors'] ?? [])[$shading] ?? 1.0);

        // A real window area always wins; otherwise derive it from the window
        // type as a ratio of the floor area (flagged as an assumption).
        $windowArea = $room->windowAreaM2;
        $windowAreaAssumed = false;
        if ($windowArea === null) {
            $ratioMap = $rules['window_area_ratio_by_window_type'] ?? [];
            $ratio = (float) ($ratioMap[$room->windowType] ?? $ratioMap['unknown'] ?? 0.10);
            $windowArea = round($area * $ratio, 2);
            $windowAreaAssumed = true;
        }
        $qSolar = $windowArea * $solarPerM2 * $shadingFactor;

        // Internal gains: every occupant counts, split sensible/latent.
        $peopleSensible = $room->occupants * (float) ($rules['people_sensible_w_per_person'] ?? 75.0);
        $peopleLatent = $room->occupants * (float) ($rules['people_latent_w_per_person'] ?? 55.0);

        $equipmentHeatMap = $rules['equipment_heat_w'] ?? [];
        $equipmentBreakdown = [];
        $equipmentHeat = 0.0;
        foreach ($room->equipment as $equipment) {
            $watts = (float) ($equipmentHeatMap[$equipment] ?? 0);
            $equipmentBreakdown[$equipment] = $watts;
            $equipmentHeat += $watts;
        }

        // Ventilation/infiltration at the assumed air-change rate.
        $ach = (float) ($rules['ventilation_ach_default'] ?? 0.5);
        $qVentSensible = (float) ($rules['ventilation_sensible_w_per_m3_ach'] ?? 2.67) * $ach * $volume;
        $qVentLatent = (float) ($rules['ventilation_latent_w_per_m3_ach'] ?? 1.3) * $ach * $volume;

        $qSensible = $qTransmission + $qSolar + $peopleSensible + $equipmentHeat + $qVentSensible;
        $qLatent = $peopleLatent + $qVentLatent;
        $qTotal = $qSensible + $qLatent;

        $safetyFactor = (float) ($rules['safety_factor'] ?? 1.1);
        $designLoad = $qTotal * $safetyFactor;

        return [
            'method'     => 'engineering_v2',
            'room_index' => $room->index,
            'room_name'  => $room->name,

            // Geometry
            'area_m2'          => round($area, 2),
            'height_m'         => $room->heightM,
            'volume_m3'        => round($volume, 2),
            'envelope_area_m2' => round($envelope, 2),

            // Transmission
            'insulation'       => $room->insulation,
            'u_equivalent'     => $ueq,
            'design_delta_t_k' => $deltaT,
            'q_transmission_w' => round($qTransmission, 2),

            // Solar gain
            'orientation'         => $room->orientation,
            'window_type'         => $room->windowType,
            'window_area_m2'      => round($windowArea, 2),
            'window_area_assumed' => $windowAreaAssumed,
            'solar_gain_w_per_m2' => $solarPerM2,
            'shading'             => $shading,
            'shading_assumed'     => true,
            'shading_factor'      => $shadingFactor,
            'q_solar_w'           => round($qSolar, 2),

            // Internal gains
            'occupants'         => $room->occupants,
            'occupants_assumed' => $room->occupantsAssumed,
            'people_sensible_w' => round($peopleSensible, 2),
            'people_latent_w'   => round($peopleLatent, 2),
            'equipment'         => $equipmentBreakdown,
            'equipment_assumed' => $room->equipmentAssumed,
            'equipment_heat_w'  => round($equipmentHeat, 2),

            // Ventilation
            'ach'               => $ach,
            'ach_assumed'       => true,
            'q_vent_sensible_w' => round($qVentSensible, 2),
            'q_vent_latent_w'   => round($qVentLatent, 2),

            // Totals
            'q_sensible_total_w' => round($qSensible, 2),
            'q_latent_total_w'   => round($qLatent, 2),
            'q_total_w'          => round($qTotal, 2),
            'safety_factor'      => $safetyFactor,
            'design_load_w'      => round($designLoad, 3),

            // Rounded outputs consumed downstream (class, overrides, quotes).
            'roof_type'   => $room->roofType,
            'final_watts' => (int) round($designLoad),
            'final_kw'    => round($designLoad / 1000, 2),
        ];
    }

    private function windowFactor(HvacRoomInput $room, array $rules): float
    {
        // A real window area takes precedence over the coarse window type.
        if ($room->windowAreaM2 !== null && $room->areaM2 > 0) {
            $ratio = $room->windowAreaM2 / $room->areaM2;
            foreach ($rules['window_ratio_factors'] ?? [] as $band) {
                if ($band['max_ratio'] === null || $ratio <= (float) $band['max_ratio']) {
                    return (float) $band['factor'];
                }
            }
        }

        return (float) ($rules['window_type_factors'][$room->windowType]
            ?? $rules['window_type_factors']['unknown'] ?? 1.05);
    }
}

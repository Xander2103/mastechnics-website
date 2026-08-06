<?php

namespace App\Services\Hvac;

use App\Services\Hvac\Input\HvacRoomInput;

/**
 * Deterministic cooling-load calculation for one room.
 *
 * base_watts = area × insulation W/m² × ceiling factor × orientation factor
 *              × window factor × roof factor
 * final_watts = base_watts + occupancy heat + equipment heat
 *
 * Every intermediate value is returned so the admin can always see exactly
 * how the final number was obtained. This is a pre-calculation aid, not
 * certified engineering software.
 */
class CoolingLoadCalculator
{
    public function calculateRoom(HvacRoomInput $room, array $rules): array
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

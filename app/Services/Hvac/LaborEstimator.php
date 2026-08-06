<?php

namespace App\Services\Hvac;

/**
 * Deterministic labor estimate from rule-set values. Every line carries its
 * assumption; all rates are placeholders until Martin validates them.
 */
class LaborEstimator
{
    /**
     * @param array $rooms room results from the calculation
     * @return array{lines: array, total_hours: float, hourly_rate: float, total_cost: float, travel_cost: float, warnings: array}
     */
    public function estimate(array $rooms, bool $includeCondensatePump, array $rules): array
    {
        $labor = $rules['labor'] ?? [];
        $indoorCount = count($rooms);
        $lines = [];
        $warnings = [];

        $base = (float) ($labor['base_installation_hours'] ?? 6.0);
        $lines[] = ['key' => 'base', 'hours' => $base, 'reason' => 'Basisinstallatie (1 binnenunit + buitenunit).'];

        if ($indoorCount > 1) {
            $extra = ($indoorCount - 1) * (float) ($labor['extra_indoor_unit_hours'] ?? 3.0);
            $lines[] = ['key' => 'extra_units', 'hours' => $extra, 'reason' => ($indoorCount - 1) . ' extra binnenunit(s).'];
        }

        $totalPipe = 0.0;
        $anyRoofRoom = false;
        foreach ($rooms as $room) {
            $totalPipe += (float) $room['pipe']['actual_length_m'];
            if (in_array($room['load']['roof_type'], ['flat_roof', 'attic_no_roof_window', 'attic_with_roof_window'], true)) {
                $anyRoofRoom = true;
            }
        }

        $pipeOver = max(0.0, $totalPipe - 5.0);
        if ($pipeOver > 0) {
            $pipeHours = round($pipeOver * (float) ($labor['hours_per_pipe_m_over_5'] ?? 0.25), 2);
            $lines[] = ['key' => 'pipe', 'hours' => $pipeHours, 'reason' => "Extra leidingwerk boven 5 m (geschat {$totalPipe} m totaal)."];
        }

        $drilling = $indoorCount * (float) ($labor['drilling_hours_per_room'] ?? 0.5);
        $lines[] = ['key' => 'drilling', 'hours' => $drilling, 'reason' => 'Muurdoorvoer per kamer.'];

        if ($includeCondensatePump) {
            $pump = (float) ($labor['condensate_pump_hours'] ?? 1.0);
            $lines[] = ['key' => 'condensate_pump', 'hours' => $pump, 'reason' => 'Plaatsing condensaatpomp (indien nodig).'];
        }

        if ($anyRoofRoom) {
            $surcharge = (float) ($labor['roof_access_surcharge_hours'] ?? 2.0);
            $lines[] = ['key' => 'roof_access', 'hours' => $surcharge, 'reason' => 'Toeslag zolder-/daksituatie (aanname — controleer bereikbaarheid).'];
            $warnings[] = [
                'code'    => 'roof_access_assumed',
                'message' => 'Toeslag voor dak-/zoldertoegang is een aanname. Controleer de plaatsingssituatie.',
            ];
        }

        $totalHours = round(array_sum(array_column($lines, 'hours')), 2);
        $rate = (float) ($labor['hourly_rate_excl_vat'] ?? 65.0);
        $travel = (float) ($labor['travel_flat_excl_vat'] ?? 35.0);

        $warnings[] = [
            'code'    => 'labor_rates_not_validated',
            'message' => 'Uurtarief en urenregels zijn standaardwaarden die Martin nog moet valideren.',
        ];

        return [
            'lines'       => $lines,
            'total_hours' => $totalHours,
            'hourly_rate' => $rate,
            'total_cost'  => round($totalHours * $rate, 2),
            'travel_cost' => $travel,
            'warnings'    => $warnings,
        ];
    }
}

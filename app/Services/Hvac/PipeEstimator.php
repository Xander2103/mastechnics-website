<?php

namespace App\Services\Hvac;

use App\Services\Hvac\Input\HvacRoomInput;

/**
 * Equivalent pipe-length estimate. Generic estimate only — the selected
 * product's manufacturer limits always take priority during selection.
 */
class PipeEstimator
{
    public function estimate(HvacRoomInput $room, array $rules): array
    {
        $pipeRules = $rules['pipe'] ?? [];
        $bendEquivalent = (float) ($pipeRules['bend_equivalent_m'] ?? 1.0);
        $riseFactor = (float) ($pipeRules['rise_equivalent_factor'] ?? 0.5);
        $threshold = (float) ($pipeRules['generic_warning_threshold_m'] ?? 15);

        $equivalent = round(
            $room->pipeLengthM
            + $room->pipeBends * $bendEquivalent
            + $room->pipeRiseM * $riseFactor,
            1
        );

        return [
            'actual_length_m'     => $room->pipeLengthM,
            'bends'               => $room->pipeBends,
            'vertical_rise_m'     => $room->pipeRiseM,
            'equivalent_length_m' => $equivalent,
            'assumed'             => $room->pipeAssumed,
            'exceeds_generic_threshold' => $equivalent > $threshold,
        ];
    }
}

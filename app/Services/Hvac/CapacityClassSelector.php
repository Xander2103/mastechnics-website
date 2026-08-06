<?php

namespace App\Services\Hvac;

/**
 * Maps a calculated cooling load to a target capacity class. The class is a
 * search target for the product catalog — never a final product choice.
 */
class CapacityClassSelector
{
    /**
     * @return array{class_kw: float|null, manual_review: bool}
     */
    public function select(float $finalLoadKw, array $rules): array
    {
        foreach ($rules['capacity_classes'] ?? [] as $band) {
            if ($finalLoadKw <= (float) $band['max_load_kw']) {
                return ['class_kw' => (float) $band['class_kw'], 'manual_review' => false];
            }
        }

        $maxClass = (float) ($rules['max_class_kw'] ?? 7.1);
        $reviewAbove = (float) ($rules['manual_review_above_kw'] ?? $maxClass);

        if ($finalLoadKw > $reviewAbove) {
            return ['class_kw' => null, 'manual_review' => true];
        }

        return ['class_kw' => $maxClass, 'manual_review' => false];
    }
}

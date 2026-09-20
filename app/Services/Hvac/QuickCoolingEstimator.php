<?php

namespace App\Services\Hvac;

/**
 * "Snelle inschatting": indicative cooling capacity from room volume.
 *
 *   volume = length × width × height
 *   watts  = volume × W/m³ of the ONE chosen situation
 *
 * The W/m³ values are rules of thumb supplied by Martin and live in the
 * rule-set configuration (`quick_estimate.w_per_m3`), so they are versioned
 * like every other rule. Situations are explicit choices — never multiplied
 * or summed.
 *
 * This is a side tool next to the detailed load methods in
 * CoolingLoadCalculator, which it never replaces. It is a pure function:
 * nothing is persisted, no product is selected and there is no path from
 * here to approval, quote conversion or mail. The capacity class it reports
 * is a non-binding indication only.
 */
class QuickCoolingEstimator
{
    /** Order and wording of the situations (values come from the rule set). */
    public const SITUATIONS = [
        'well_insulated'                   => 'Goed geïsoleerd',
        'south_facing'                     => 'Zuidgericht',
        'sunny_poor_insulation'            => 'Veel zon en slecht geïsoleerd',
        'sunny_poor_insulation_under_roof' => 'Veel zon, slechte isolatie en onder dak',
    ];

    /** Shown with every result: what the rule of thumb does not cover. */
    public const LIMITATION_WARNINGS = [
        'Dit is een indicatieve vuistregel per m³ — geen technische ontwerpberekening.',
        'Grote glaspartijen, interne warmtelasten (toestellen, veel personen), ventilatie, vochtbelasting en bijzondere ruimtes (veranda, serverlokaal, keuken, winkel …) kunnen een uitgebreidere beoordeling vereisen.',
        'De inschatting kiest geen toestel, keurt niets goed en maakt of verstuurt geen offerte.',
    ];

    public function __construct(private readonly CapacityClassSelector $classSelector)
    {
    }

    public function available(array $rules): bool
    {
        return $this->values($rules) !== [];
    }

    /**
     * Situation key => ['label' => string, 'w_per_m3' => float], in display
     * order, limited to situations the rule set actually defines.
     */
    public function situations(array $rules): array
    {
        $values = $this->values($rules);
        $situations = [];
        foreach (self::SITUATIONS as $key => $label) {
            if (isset($values[$key])) {
                $situations[$key] = ['label' => $label, 'w_per_m3' => $values[$key]];
            }
        }

        return $situations;
    }

    /**
     * @param array $input length_m, width_m, height_m, situation (raw form input)
     * @return array{
     *   available: bool, ok: bool, errors: array<string,string>, indicative: bool,
     *   length_m: ?float, width_m: ?float, height_m: ?float,
     *   situation: ?string, situation_label: ?string, w_per_m3: ?float,
     *   volume_m3: ?float, watts: ?int, kw: ?float,
     *   steps: string[], warnings: string[], class_indication: ?array
     * }
     */
    public function estimate(array $input, array $rules): array
    {
        $result = [
            'available'        => $this->available($rules),
            'ok'               => false,
            'errors'           => [],
            'indicative'       => true,
            'length_m'         => null,
            'width_m'          => null,
            'height_m'         => null,
            'situation'        => null,
            'situation_label'  => null,
            'w_per_m3'         => null,
            'volume_m3'        => null,
            'watts'            => null,
            'kw'               => null,
            'steps'            => [],
            'warnings'         => self::LIMITATION_WARNINGS,
            'class_indication' => null,
        ];

        if (! $result['available']) {
            $result['errors']['situation'] = 'De snelle inschatting is niet beschikbaar: de actieve instellingen bevatten geen waarden per m³.';

            return $result;
        }

        $limits = $rules['quick_estimate']['limits'] ?? [];
        $min = (float) ($limits['min_dimension_m'] ?? 0.5);
        $maxLength = (float) ($limits['max_length_m'] ?? 50);
        $maxHeight = (float) ($limits['max_height_m'] ?? 10);

        $dimensions = [
            'length_m' => ['Lengte', $maxLength],
            'width_m'  => ['Breedte', $maxLength],
            'height_m' => ['Hoogte', $maxHeight],
        ];

        $errors = [];
        foreach ($dimensions as $field => [$label, $max]) {
            $raw = $input[$field] ?? null;
            if ($raw === null || $raw === '') {
                $errors[$field] = "{$label} is verplicht.";
                continue;
            }

            $value = $this->toFloat($raw);
            if ($value === null) {
                $errors[$field] = "{$label} moet een getal zijn, bijvoorbeeld 4 of 2,5.";
                continue;
            }
            if ($value < $min || $value > $max) {
                $errors[$field] = "{$label} moet tussen " . $this->nl($min) . ' m en ' . $this->nl($max) . ' m liggen.';
                continue;
            }

            $result[$field] = $value;
        }

        $situations = $this->situations($rules);
        $situation = $input['situation'] ?? null;
        if ($situation === null || $situation === '') {
            $errors['situation'] = 'Kies de situatie van de ruimte.';
        } elseif (! is_string($situation) || ! isset($situations[$situation])) {
            $errors['situation'] = 'Kies een van de vier situaties.';
        }

        if ($errors !== []) {
            // Keep the documented field order so messages read top to bottom.
            $result['errors'] = array_replace(
                array_intersect_key(array_flip(['length_m', 'width_m', 'height_m', 'situation']), $errors),
                $errors
            );
            $result['length_m'] = $result['width_m'] = $result['height_m'] = null;

            return $result;
        }

        $wPerM3 = (float) $situations[$situation]['w_per_m3'];
        $volume = round($result['length_m'] * $result['width_m'] * $result['height_m'], 2);
        $watts = (int) round($volume * $wPerM3);
        $kw = round($watts / 1000, 2);

        $class = $this->classSelector->select($kw, $rules);

        $result['ok'] = true;
        $result['situation'] = $situation;
        $result['situation_label'] = $situations[$situation]['label'];
        $result['w_per_m3'] = $wPerM3;
        $result['volume_m3'] = $volume;
        $result['watts'] = $watts;
        $result['kw'] = $kw;
        $result['steps'] = [
            'Volume: ' . $this->nl($result['length_m']) . ' m × ' . $this->nl($result['width_m']) . ' m × '
                . $this->nl($result['height_m']) . ' m = ' . $this->nl($volume) . ' m³',
            'Situatie: ' . $situations[$situation]['label'] . ' → ' . $this->nl($wPerM3) . ' W/m³',
            'Indicatief koelvermogen: ' . $this->nl($volume) . ' m³ × ' . $this->nl($wPerM3) . ' W/m³ = '
                . number_format($watts, 0, ',', '.') . ' W',
            'Omgerekend: ' . number_format($watts, 0, ',', '.') . ' W ÷ 1.000 = ' . $this->nl($kw) . ' kW',
        ];
        $result['class_indication'] = [
            'class_kw'      => $class['class_kw'],
            'manual_review' => $class['manual_review'],
            'binding'       => false,
        ];

        return $result;
    }

    /** @return array<string, float> */
    private function values(array $rules): array
    {
        $values = $rules['quick_estimate']['w_per_m3'] ?? [];
        if (! is_array($values)) {
            return [];
        }

        return array_map(
            'floatval',
            array_filter($values, fn ($v) => is_numeric($v) && (float) $v > 0)
        );
    }

    private function toFloat(mixed $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            $value = (float) $raw;
        } elseif (is_string($raw)) {
            $normalized = str_replace(',', '.', trim($raw));
            if (! preg_match('/^-?\d+(\.\d+)?$/', $normalized)) {
                return null;
            }
            $value = (float) $normalized;
        } else {
            return null;
        }

        return is_finite($value) ? $value : null;
    }

    /** Dutch number formatting without trailing zeros (2,5 · 50 · 1,75). */
    private function nl(float $value): string
    {
        $formatted = number_format($value, 2, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }
}

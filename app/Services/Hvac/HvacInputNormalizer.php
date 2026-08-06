<?php

namespace App\Services\Hvac;

use App\Models\CustomerRequest;
use App\Services\Hvac\Input\HvacInputValidationResult;
use App\Services\Hvac\Input\HvacRequestInput;
use App\Services\Hvac\Input\HvacRoomInput;

/**
 * Turns the raw airco request answers into a strict, immutable input model.
 *
 * Principles:
 * - Critical values (dimensions, insulation) are never guessed: missing or
 *   impossible → blocker, no calculation.
 * - Optional values the form does not ask (occupancy, equipment, pipe runs,
 *   electrical supply, drainage) get rule-set assumptions, ALWAYS with a
 *   warning and an "assumed" flag.
 * - The original answers are preserved untouched in the snapshot.
 */
class HvacInputNormalizer
{
    private const ROOM_TYPE_NAMES = [
        'slaapkamer'  => 'Slaapkamer',
        'woonkamer'   => 'Woonkamer',
        'bureau'      => 'Bureau',
        'keuken'      => 'Keuken',
        'zolderkamer' => 'Zolderkamer',
        'andere'      => 'Andere ruimte',
    ];

    public function fromCustomerRequest(CustomerRequest $request, array $rules): HvacInputValidationResult
    {
        $warnings = [];
        $blockers = [];

        if ($request->service_category !== 'airco_offerte') {
            return new HvacInputValidationResult(null, [], [[
                'code'    => 'not_airco_installation',
                'message' => 'Deze aanvraag is geen airco-installatieaanvraag; voorcalculatie is niet van toepassing.',
            ]]);
        }

        $answers = $request->metadata['answers'] ?? [];
        $roomsRaw = $answers['rooms'] ?? [];

        if (! is_array($roomsRaw) || $roomsRaw === []) {
            return new HvacInputValidationResult(null, [], [[
                'code'    => 'no_rooms',
                'message' => 'Er is geen kamerinformatie aanwezig; berekening is niet mogelijk.',
            ]]);
        }

        // House-level insulation (critical).
        $insulation = $this->cleanEnum($answers['insulation_level'] ?? null);
        if ($insulation === null) {
            $blockers[] = [
                'code'    => 'missing_insulation',
                'message' => 'De isolatiegraad van de woning ontbreekt; berekening is niet mogelijk zonder deze waarde.',
            ];
        } elseif (in_array($insulation, $rules['insulation_fallback_values'] ?? ['other', 'unknown'], true)) {
            $warnings[] = [
                'code'    => 'insulation_fallback',
                'message' => 'De isolatiegraad is "andere" of "onbekend" — er wordt gerekend met een gemiddelde waarde. Controleer dit.',
            ];
        }

        $rooms = [];
        $anyOrientationFallback = false;
        $anyWindowFallback = false;
        $anyRoofWarning = false;

        foreach (array_values($roomsRaw) as $i => $room) {
            $width  = $this->cleanNumber($room['width'] ?? null);
            $length = $this->cleanNumber($room['length'] ?? null);
            $height = $this->cleanNumber($room['height'] ?? null);
            $number = $i + 1;

            if ($width === null || $length === null || $width <= 0 || $length <= 0) {
                $blockers[] = [
                    'code'    => 'invalid_dimensions',
                    'message' => "Kamer {$number}: breedte of lengte ontbreekt of is ongeldig.",
                ];
                continue;
            }

            if ($height === null || $height <= 0) {
                $blockers[] = [
                    'code'    => 'missing_height',
                    'message' => "Kamer {$number}: de hoogte ontbreekt (oudere aanvraag?). Vraag de hoogte op of voer ze handmatig in via een herberekening.",
                ];
                continue;
            }

            if ($width > 50 || $length > 50 || $height > 8 || $height < 1.5) {
                $blockers[] = [
                    'code'    => 'impossible_dimensions',
                    'message' => "Kamer {$number}: de afmetingen zijn onwaarschijnlijk (b {$width} m × l {$length} m × h {$height} m). Controleer de invoer.",
                ];
                continue;
            }

            $area = $this->cleanNumber($room['surface'] ?? null) ?? round($width * $length, 1);
            if ($area <= 0 || abs($area - $width * $length) > max(1.0, 0.1 * $width * $length)) {
                // Recompute rather than trust an inconsistent stored surface.
                $area = round($width * $length, 1);
            }

            $type = $this->cleanEnum($room['type'] ?? null) ?? 'andere';

            $orientation = $this->cleanEnum($room['orientation'] ?? null) ?? 'unknown';
            if (in_array($orientation, ['other', 'unknown'], true)) {
                $anyOrientationFallback = true;
            }

            $windowType = $this->cleanEnum($room['windows'] ?? null) ?? 'unknown';
            if (in_array($windowType, ['other', 'unknown'], true)) {
                $anyWindowFallback = true;
            }

            $roofType = $this->cleanEnum($room['roof_type'] ?? null) ?? 'unknown';
            if (in_array($roofType, $rules['roof_warning_values'] ?? [], true)) {
                $anyRoofWarning = true;
            }

            $occupants = $rules['occupancy']['default_occupants_by_room_type'][$type] ?? 2;
            $equipment = $rules['default_equipment_by_room_type'][$type] ?? [];

            $rooms[] = new HvacRoomInput(
                index: $i,
                name: (self::ROOM_TYPE_NAMES[$type] ?? ucfirst($type)) . ' ' . $number,
                type: $type,
                widthM: $width,
                lengthM: $length,
                heightM: $height,
                areaM2: $area,
                insulation: $insulation ?? 'unknown',
                insulationOther: $this->cleanString($answers['insulation_level_other'] ?? null),
                orientation: $orientation,
                orientationOther: $this->cleanString($room['orientation_other'] ?? null),
                roofType: $roofType,
                roofTypeOther: $this->cleanString($room['roof_type_other'] ?? null),
                windowType: $windowType,
                windowTypeOther: $this->cleanString($room['windows_other'] ?? null),
                windowAreaM2: null,
                occupants: (int) $occupants,
                occupantsAssumed: true,
                equipment: $equipment,
                equipmentAssumed: true,
                pipeLengthM: (float) ($rules['pipe']['assumed_length_m'] ?? 5.0),
                pipeBends: (int) ($rules['pipe']['assumed_bends'] ?? 4),
                pipeRiseM: (float) ($rules['pipe']['assumed_rise_m'] ?? 2.5),
                pipeAssumed: true,
                drainage: 'unknown',
            );
        }

        if ($blockers !== []) {
            return new HvacInputValidationResult(null, $warnings, $blockers);
        }

        // Warnings for values the form does not collect (assumptions).
        if ($anyOrientationFallback) {
            $warnings[] = [
                'code'    => 'orientation_fallback',
                'message' => 'Minstens één kamer heeft ligging "andere" of "onbekend" — er wordt een neutrale factor gebruikt.',
            ];
        }
        if ($anyWindowFallback) {
            $warnings[] = [
                'code'    => 'window_fallback',
                'message' => 'Minstens één kamer heeft raamtype "andere" of "onbekend" — er wordt een gemiddelde raamfactor gebruikt.',
            ];
        }
        if ($anyRoofWarning) {
            $warnings[] = [
                'code'    => 'roof_correction_not_validated',
                'message' => 'Zolder- of platdaksituatie gedetecteerd. Er is nog géén gevalideerde dakcorrectie ingesteld; het berekende vermogen kan te laag zijn.',
            ];
        }

        $warnings[] = [
            'code'    => 'occupancy_assumed',
            'message' => 'Het aantal personen per kamer wordt niet gevraagd in het formulier en is aangenomen per kamertype.',
        ];
        $warnings[] = [
            'code'    => 'equipment_assumed',
            'message' => 'Warmtebronnen (tv, pc, open keuken) worden niet gevraagd en zijn aangenomen per kamertype.',
        ];
        $warnings[] = [
            'code'    => 'pipe_assumed',
            'message' => 'Leidinglengte, bochten en hoogteverschil zijn een aanname (standaard installatieprofiel). Controleer ter plaatse.',
        ];
        $warnings[] = [
            'code'    => 'electrical_supply_unknown',
            'message' => 'De elektrische voeding (mono/tri, zekeringen) is niet gekend. Elektrische controle vereist.',
        ];
        $warnings[] = [
            'code'    => 'drainage_unknown',
            'message' => 'Afvoer voor condensaat is niet bevraagd; condensaatpomp mogelijk nodig.',
        ];

        $customerType = $this->cleanEnum($answers['customer_type'] ?? null);
        if ($customerType === null) {
            $customerType = 'residential';
            $warnings[] = [
                'code'    => 'customer_type_assumed',
                'message' => 'Klanttype ontbreekt; particulier aangenomen.',
            ];
        }

        $splitType = count($rooms) === 1 ? 'single_split' : 'multi_split';
        $warnings[] = [
            'code'    => 'split_type_derived',
            'message' => $splitType === 'single_split'
                ? 'Eén kamer opgegeven — single split afgeleid. Controleer of dit klopt.'
                : count($rooms) . ' kamers opgegeven — multi split afgeleid. Controleer of dit klopt (meerdere single splits kunnen ook).',
        ];

        $input = new HvacRequestInput(
            customerRequestId: $request->id,
            locale: in_array($request->locale, ['nl', 'fr', 'en'], true) ? $request->locale : 'nl',
            installationType: 'installation',
            splitType: $splitType,
            splitTypeDerived: true,
            customerType: $customerType,
            electricalSupply: 'unknown',
            hasExistingOutdoorUnit: $this->cleanEnum($answers['airco_has_outdoor_unit'] ?? null) ?? 'unknown',
            outdoorUnitLocation: 'unknown',
            houseOlderThan10y: $this->cleanEnum($answers['airco_house_age'] ?? null),
            preferredBrand: null,
            budgetPreference: null,
            noisePreference: null,
            wifiPreference: null,
            timeframe: $this->cleanString($answers['airco_installation_timing'] ?? null),
            comments: $this->cleanString($answers['airco_installation_timing_notes'] ?? null),
            photosCount: $request->relationLoaded('attachments')
                ? $request->attachments->count()
                : $request->attachments()->count(),
            rooms: $rooms,
            source: $answers,
        );

        return new HvacInputValidationResult($input, $warnings, []);
    }

    private function cleanNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function cleanEnum(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));

        return $value === '' ? null : $value;
    }

    private function cleanString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}

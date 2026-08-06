<?php

namespace App\Services\Hvac\Explanation;

use App\Models\HvacRecommendation;

/**
 * Strict validation of AI output before anything is stored.
 *
 * Rejects: unexpected fields, wrong locale, unknown product IDs, HTML or
 * script-like content, and oversized strings. AI output never contains
 * prices/capacities/warnings by design — any such field is "unexpected"
 * and rejects the whole output.
 */
class AiExplanationValidator
{
    private const ALLOWED_KEYS = [
        'locale', 'explanation', 'summary', 'comparison',
        'quote_introduction', 'missing_information_questions', 'product_ids',
    ];

    private const MAX_TEXT_LENGTH = 4000;

    /**
     * @return array{valid: bool, errors: string[]}
     */
    public function validate(array $output, HvacRecommendation $recommendation, string $expectedLocale): array
    {
        $errors = [];

        foreach (array_keys($output) as $key) {
            if (! in_array($key, self::ALLOWED_KEYS, true)) {
                $errors[] = "Onverwacht veld '{$key}' in AI-uitvoer.";
            }
        }

        if (($output['locale'] ?? null) !== $expectedLocale) {
            $errors[] = 'AI-uitvoer heeft een verkeerde of ontbrekende taal.';
        }

        foreach (['explanation', 'summary', 'comparison', 'quote_introduction'] as $field) {
            if (! isset($output[$field])) {
                continue;
            }
            if (! is_string($output[$field])) {
                $errors[] = "Veld '{$field}' is geen tekst.";
                continue;
            }
            if (mb_strlen($output[$field]) > self::MAX_TEXT_LENGTH) {
                $errors[] = "Veld '{$field}' is te lang.";
            }
            if ($this->containsMarkup($output[$field])) {
                $errors[] = "Veld '{$field}' bevat HTML of scriptachtige inhoud.";
            }
        }

        if (isset($output['missing_information_questions'])) {
            if (! is_array($output['missing_information_questions'])) {
                $errors[] = "Veld 'missing_information_questions' is geen lijst.";
            } else {
                foreach ($output['missing_information_questions'] as $question) {
                    if (! is_string($question) || $this->containsMarkup($question) || mb_strlen($question) > 500) {
                        $errors[] = 'Ongeldige vraag in missing_information_questions.';
                        break;
                    }
                }
            }
        }

        if (isset($output['product_ids'])) {
            $knownIds = $recommendation->items()->whereNotNull('hvac_product_id')
                ->pluck('hvac_product_id')->map(fn ($id) => (int) $id)->all();
            foreach ((array) $output['product_ids'] as $id) {
                if (! in_array((int) $id, $knownIds, true)) {
                    $errors[] = "AI verwijst naar onbekend product-id {$id}.";
                }
            }
        }

        return ['valid' => $errors === [], 'errors' => $errors];
    }

    private function containsMarkup(string $text): bool
    {
        return preg_match('/<[a-z\/!]|javascript:|data:text\/html/i', $text) === 1;
    }
}

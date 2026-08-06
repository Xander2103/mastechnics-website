<?php

namespace App\Services\Hvac\Explanation;

/**
 * Provider boundary for the optional AI explanation layer.
 *
 * A provider receives a fully validated, deterministic payload and may ONLY
 * produce customer-friendly text about it. It can never change products,
 * prices, capacities or warnings — output is validated against the
 * recommendation before anything is stored (see AiExplanationValidator).
 *
 * Expected output shape (all keys optional except locale):
 * [
 *   'locale'                         => 'nl'|'fr'|'en',   // must match the request
 *   'explanation'                    => string,           // plain text, no HTML
 *   'summary'                        => string,
 *   'comparison'                     => string,
 *   'quote_introduction'             => string,
 *   'missing_information_questions'  => string[],
 *   'product_ids'                    => int[],            // only ids present in the recommendation
 * ]
 *
 * Return null when no explanation could be generated. Throwing is allowed;
 * the caller treats it as a provider error and continues without AI.
 */
interface HvacExplanationGeneratorInterface
{
    public function generate(array $payload): ?array;

    public function name(): string;

    public function model(): ?string;

    public function promptVersion(): ?string;
}

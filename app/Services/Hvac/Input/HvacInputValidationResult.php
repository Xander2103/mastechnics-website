<?php

namespace App\Services\Hvac\Input;

/**
 * Result of normalizing a customer request into HVAC input.
 *
 * Warnings are informational (calculation continues, admin sees them).
 * Blockers prevent calculation entirely — the system never guesses
 * critical values.
 *
 * Each warning/blocker: ['code' => string, 'message' => string (NL)]
 */
final class HvacInputValidationResult
{
    public function __construct(
        public readonly ?HvacRequestInput $input,
        public readonly array $warnings,
        public readonly array $blockers,
    ) {
    }

    public function isCalculable(): bool
    {
        return $this->input !== null && $this->blockers === [];
    }
}

<?php

namespace App\Services\Hvac\Explanation;

/**
 * Default provider: no AI. The entire HVAC system functions fully without
 * an explanation — the admin panel simply shows the deterministic data.
 */
class NullHvacExplanationGenerator implements HvacExplanationGeneratorInterface
{
    public function generate(array $payload): ?array
    {
        return null;
    }

    public function name(): string
    {
        return 'null';
    }

    public function model(): ?string
    {
        return null;
    }

    public function promptVersion(): ?string
    {
        return null;
    }
}

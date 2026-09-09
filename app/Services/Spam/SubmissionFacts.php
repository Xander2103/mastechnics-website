<?php

namespace App\Services\Spam;

/**
 * The validated facts of a submission the anti-abuse layers reason about.
 * Only what the limiters, the fingerprint and the trust evaluator need;
 * nothing here is stored by the protection layers themselves.
 */
final class SubmissionFacts
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $message = null,
        public readonly ?string $name = null,
        public readonly ?string $locale = null,
        public readonly bool $hasAttachments = false,
        public readonly bool $hasRooms = false,
    ) {
    }
}

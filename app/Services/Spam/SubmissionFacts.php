<?php

namespace App\Services\Spam;

/**
 * The validated facts of a submission the anti-abuse layers reason about.
 * Only what the limiters and the fingerprint need; nothing is stored.
 */
final class SubmissionFacts
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone = null,
        public readonly ?string $message = null,
    ) {
    }
}

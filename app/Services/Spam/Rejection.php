<?php

namespace App\Services\Spam;

/**
 * Outcome of a failed screening. Nothing has been stored or mailed when a
 * Rejection exists. `silentSuccess` means the visitor gets the normal
 * success screen (honeypot: never tell a bot it was caught). `messageKind`
 * selects the localized message in PublicFormGuard::message().
 */
final class Rejection
{
    public function __construct(
        public readonly string $reason,
        public readonly string $messageKind,
        public readonly string $errorKey,
        public readonly bool $silentSuccess = false,
    ) {
    }
}

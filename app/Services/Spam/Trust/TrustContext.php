<?php

namespace App\Services\Spam\Trust;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\Rejection;
use App\Services\Spam\SubmissionFacts;
use Illuminate\Http\Request;

/**
 * Everything the TrustEvaluator needs to judge one submission. Built by
 * PublicFormGuard after validation; the hard checks (captcha, honeypot,
 * fill time, limits, duplicates) have already run and their outcome is
 * passed in, so signal providers never re-run network calls.
 */
final class TrustContext
{
    public function __construct(
        public readonly string $form,
        public readonly Request $request,
        public readonly SubmissionFacts $facts,
        public readonly CaptchaVerdict $captcha,
        public readonly string $timingStatus,
        public readonly ?int $fillSeconds,
        public readonly bool $honeypot,
        public readonly ?Rejection $hardRejection = null,
    ) {
    }
}

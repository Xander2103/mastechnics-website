<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\FormTimingToken;
use PHPUnit\Framework\TestCase;

class FormTimingTokenTest extends TestCase
{
    private FormTimingToken $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = new FormTimingToken('base64:testkey', 3, 12 * 3600);
    }

    public function test_a_token_older_than_the_minimum_and_younger_than_the_maximum_is_ok(): void
    {
        $now = 1_800_000_000;

        $this->assertSame(FormTimingToken::OK, $this->token->check('contact', $this->token->issue('contact', $now - 3), $now));
        $this->assertSame(FormTimingToken::OK, $this->token->check('contact', $this->token->issue('contact', $now - 3600), $now));
        $this->assertSame(FormTimingToken::OK, $this->token->check('contact', $this->token->issue('contact', $now - 12 * 3600), $now));
    }

    public function test_too_fast_and_expired_are_distinguished(): void
    {
        $now = 1_800_000_000;

        $this->assertSame(FormTimingToken::TOO_FAST, $this->token->check('contact', $this->token->issue('contact', $now), $now));
        $this->assertSame(FormTimingToken::TOO_FAST, $this->token->check('contact', $this->token->issue('contact', $now - 2), $now));
        $this->assertSame(FormTimingToken::EXPIRED, $this->token->check('contact', $this->token->issue('contact', $now - 12 * 3600 - 1), $now));
    }

    public function test_missing_forged_or_foreign_tokens_are_rejected(): void
    {
        $now = 1_800_000_000;

        $this->assertSame(FormTimingToken::MISSING, $this->token->check('contact', null, $now));
        $this->assertSame(FormTimingToken::MISSING, $this->token->check('contact', '', $now));
        $this->assertSame(FormTimingToken::MISSING, $this->token->check('contact', ['x'], $now));
        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', 'garbage', $now));
        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', ($now - 60) . '.deadbeef', $now));

        // A future timestamp with a valid-looking format but wrong signature.
        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', ($now - 60) . '.' . str_repeat('0', 64), $now));

        // Signed for another form.
        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', $this->token->issue('request', $now - 60), $now));

        // Signed with another key.
        $other = new FormTimingToken('base64:otherkey', 3, 12 * 3600);
        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', $other->issue('contact', $now - 60), $now));
    }

    public function test_tampering_with_the_timestamp_invalidates_the_signature(): void
    {
        $now = 1_800_000_000;
        [$ts, $sig] = explode('.', $this->token->issue('contact', $now));

        $this->assertSame(FormTimingToken::INVALID, $this->token->check('contact', ($ts - 600) . '.' . $sig, $now));
    }
}

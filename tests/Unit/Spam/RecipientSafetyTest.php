<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\RecipientSafety;
use PHPUnit\Framework\TestCase;

class RecipientSafetyTest extends TestCase
{
    public function test_normal_addresses_are_safe(): void
    {
        foreach ([
            'jan@example.com',
            'jan.janssens@mail.example.co.uk',
            'jan+tag@example.com',
            'martin@mastechnics.be',
            "o'neil@example.com",
        ] as $email) {
            $this->assertTrue(RecipientSafety::isSafe($email), $email);
        }
    }

    public function test_header_injection_and_malformed_addresses_are_unsafe(): void
    {
        foreach ([
            null,
            '',
            "jan@example.com\r\nBcc: victim@example.com",
            "jan@example.com\nCc: x@example.com",
            'jan@example.com, other@example.com',
            'jan@example.com;other@example.com',
            '"Jan" <jan@example.com>',
            'jan @example.com',
            'jan@localhost',
            'jan@example.',
            'jan@.example.com',
            'not-an-email',
            str_repeat('a', 250) . '@example.com',
        ] as $email) {
            $this->assertFalse(RecipientSafety::isSafe($email), var_export($email, true));
        }
    }

    public function test_normalization_folds_case_plus_tags_and_gmail_dots(): void
    {
        $this->assertSame('jan@gmail.com', RecipientSafety::normalize('J.A.N+promo@Gmail.com'));
        $this->assertSame('jan@gmail.com', RecipientSafety::normalize('jan@googlemail.com'));
        $this->assertSame('jan.janssens@example.com', RecipientSafety::normalize('  Jan.Janssens+x@Example.com '));
        $this->assertSame('nonsense', RecipientSafety::normalize('NONSENSE'));

        $this->assertSame(RecipientSafety::hash('Victim@Gmail.com'), RecipientSafety::hash('v.i.c.t.i.m+a@googlemail.com'));
        $this->assertNotSame(RecipientSafety::hash('a@example.com'), RecipientSafety::hash('b@example.com'));
    }
}

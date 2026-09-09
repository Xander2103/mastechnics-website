<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\Trust\SimHash;
use PHPUnit\Framework\TestCase;

class SimHashTest extends TestCase
{
    public function test_hash_is_16_hex_chars_and_deterministic(): void
    {
        $text = 'hallo ik zoek een goedkope airco voor mijn woning graag offerte';

        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', SimHash::of($text));
        $this->assertSame(SimHash::of($text), SimHash::of($text));
    }

    public function test_identical_text_has_distance_zero(): void
    {
        $text = 'kunnen jullie mijn ketel binnenkort eens nakijken hij maakt lawaai';

        $this->assertSame(0, SimHash::distance(SimHash::of($text), SimHash::of($text)));
    }

    public function test_near_identical_text_is_within_threshold(): void
    {
        $a = 'hallo ik zoek een goedkope airco voor mijn woning in gent graag een offerte';
        $b = 'hallo ik zoek een goedkope airco voor mijn woning in brugge graag een offerte';

        $this->assertLessThanOrEqual(12, SimHash::distance(SimHash::of($a), SimHash::of($b)));
    }

    public function test_unrelated_text_is_far_apart(): void
    {
        $a = 'hallo ik zoek een goedkope airco voor mijn woning in gent graag een offerte';
        $b = 'de verwarmingsketel in de kelder lekt water en de druk zakt elke dag verder weg';

        $this->assertGreaterThan(12, SimHash::distance(SimHash::of($a), SimHash::of($b)));
    }

    public function test_short_text_falls_back_to_words_and_empty_is_zero_hash(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', SimHash::of('twee woorden'));
        $this->assertSame(str_repeat('0', 16), SimHash::of('   '));
    }

    public function test_distance_counts_bits(): void
    {
        $this->assertSame(1, SimHash::distance('0000000000000000', '0000000000000001'));
        $this->assertSame(4, SimHash::distance('0000000000000000', '000000000000000f'));
        $this->assertSame(64, SimHash::distance('0000000000000000', 'ffffffffffffffff'));
    }
}

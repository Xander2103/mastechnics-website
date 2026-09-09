<?php

namespace App\Services\Spam\Trust;

/**
 * 64-bit SimHash over word 3-grams: near-identical texts (a spam template
 * with a few words swapped) land within a small Hamming distance of each
 * other, unrelated texts do not. Pure PHP, no gmp; hashes are 16 hex chars.
 */
final class SimHash
{
    public static function of(string $text): string
    {
        $features = self::features($text);

        if ($features === []) {
            return str_repeat('0', 16);
        }

        $vector = array_fill(0, 64, 0);

        foreach ($features as $feature) {
            $hash = md5($feature);

            // Use the first 16 hex chars as a 64-bit feature hash.
            for ($i = 0; $i < 16; $i++) {
                $nibble = hexdec($hash[$i]);

                for ($b = 0; $b < 4; $b++) {
                    $bit = ($nibble >> (3 - $b)) & 1;
                    $vector[$i * 4 + $b] += $bit === 1 ? 1 : -1;
                }
            }
        }

        $hex = '';

        for ($i = 0; $i < 16; $i++) {
            $nibble = 0;

            for ($b = 0; $b < 4; $b++) {
                $nibble = ($nibble << 1) | ($vector[$i * 4 + $b] > 0 ? 1 : 0);
            }

            $hex .= dechex($nibble);
        }

        return $hex;
    }

    /** Hamming distance between two hashes from of(). Unequal lengths count every extra nibble as 4. */
    public static function distance(string $a, string $b): int
    {
        $a = strtolower($a);
        $b = strtolower($b);
        $len = max(strlen($a), strlen($b));
        $distance = 0;

        for ($i = 0; $i < $len; $i++) {
            $x = isset($a[$i]) && ctype_xdigit($a[$i]) ? hexdec($a[$i]) : 0;
            $y = isset($b[$i]) && ctype_xdigit($b[$i]) ? hexdec($b[$i]) : 0;
            $xor = $x ^ $y;

            while ($xor > 0) {
                $distance += $xor & 1;
                $xor >>= 1;
            }
        }

        return $distance;
    }

    /**
     * Character 3-gram shingles of the whitespace-normalized text. Many
     * small features make the hash stable: swapping one word in a sentence
     * touches a handful of shingles out of dozens, so the distance stays
     * small, while unrelated texts share few shingles and land far apart.
     *
     * @return array<int, string>
     */
    private static function features(string $text): array
    {
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        if ($text === '') {
            return [];
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $count = count($chars);

        if ($count < 3) {
            return [$text];
        }

        $shingles = [];

        for ($i = 0; $i <= $count - 3; $i++) {
            $shingles[] = $chars[$i] . $chars[$i + 1] . $chars[$i + 2];
        }

        return $shingles;
    }
}

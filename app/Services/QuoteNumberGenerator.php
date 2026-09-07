<?php

namespace App\Services;

use App\Models\Quote;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of quote numbers ("OFF-2026-0001"). Used by the manual quote
 * editor and the HVAC quote conversion so both agree on the format and on
 * the concurrency guard.
 *
 * Two admins saving a first quote at the same time used to read the same
 * max() and collide on the unique index; the sequence number is now taken
 * under a short cache lock and passed into the caller's write. The number
 * is parsed after the "OFF-{year}-" prefix, so it keeps counting past 9999
 * instead of wrapping (substr(-4) turned OFF-2026-10000 into 0000 → 10000
 * again → unique-index error).
 */
class QuoteNumberGenerator
{
    public const PREFIX = 'OFF';

    private const LOCK_SECONDS = 10;

    /**
     * Reserve the next quote number and run $callback with it while the
     * lock is held, so the row that uses the number is written before the
     * next caller reads max().
     *
     * @template T
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withNextNumber(callable $callback): mixed
    {
        return Cache::lock('quote-number-sequence', self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, fn () => $callback($this->next()));
    }

    public function next(): string
    {
        $year = now()->year;
        $prefix = self::PREFIX . '-' . $year . '-';

        $sequence = Quote::query()
            ->where('quote_number', 'LIKE', $prefix . '%')
            ->pluck('quote_number')
            ->map(function (string $number) use ($prefix): int {
                $rest = substr($number, strlen($prefix));

                return ctype_digit($rest) ? (int) $rest : 0;
            })
            ->max();

        $next = ((int) $sequence) + 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

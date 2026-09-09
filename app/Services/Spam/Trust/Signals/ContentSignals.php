<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\SimHash;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * What was written, and how it compares to what was written recently.
 * The similarity check (SimHash within a Hamming distance of recent
 * accepted messages) catches a campaign that varies a template — the
 * exact-content fingerprint in PublicFormGuard only catches verbatim
 * repeats. Belgian phone numbers and wizard details (photos, rooms) are
 * things bots rarely bother with, so they count as human evidence.
 */
final class ContentSignals implements SignalProvider
{
    public const SIMHASH_KEY_PREFIX = 'form-protection:simhash:';

    public const NAME_KEY_PREFIX = 'form-protection:name-seen:';

    private const MIN_SIMILARITY_LENGTH = 20;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_CONTENT;
        $name = trim((string) $ctx->facts->name);
        $message = trim((string) $ctx->facts->message);
        $signals = [];

        if (preg_match_all('~https?://|www\.~i', $name . ' ' . $message) >= 2) {
            $signals[] = TrustSignal::risk('content_urls', $group, 2);
        }

        if ($name !== '' && preg_match('~https?://|www\.|http|@|\d{4,}~i', $name) === 1) {
            $signals[] = TrustSignal::risk('name_suspicious', $group, 2);
        }

        if ($message !== '' && preg_match('/\p{Cyrillic}|\p{Han}|\p{Arabic}|\p{Hangul}|\p{Thai}/u', $message) === 1) {
            $signals[] = TrustSignal::risk('content_non_latin', $group, 2);
        }

        if ($name !== '' && $message !== '' && mb_strtolower($name) === mb_strtolower($message) && mb_strlen($message) <= 20) {
            $signals[] = TrustSignal::risk('content_name_equals_message', $group, 2);
        }

        if ($this->similarToRecent($ctx->form, $message)) {
            $signals[] = TrustSignal::risk('content_similar_recent', $group, 3);
        }

        if ($this->nameRepeated($name)) {
            $signals[] = TrustSignal::risk('name_repeated_recent', $group, 2);
        }

        if (self::belgianPhone($ctx->facts->phone)) {
            $signals[] = TrustSignal::positive('phone_plausible', $group);
        }

        if ($ctx->facts->hasAttachments || $ctx->facts->hasRooms) {
            $signals[] = TrustSignal::positive('request_details_provided', $group);
        }

        return $signals;
    }

    public static function normalizeMessage(?string $message): string
    {
        $text = mb_strtolower(trim((string) $message));
        $text = preg_replace('/\d+/', '', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /** SimHash of the message when it is long enough to be meaningful, else null. */
    public static function messageHash(?string $message): ?string
    {
        $normalized = self::normalizeMessage($message);

        return mb_strlen($normalized) >= self::MIN_SIMILARITY_LENGTH ? SimHash::of($normalized) : null;
    }

    public static function nameKey(?string $name): ?string
    {
        $normalized = strtolower(trim(Str::ascii((string) $name)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized === '' ? null : self::NAME_KEY_PREFIX . hash('sha256', $normalized);
    }

    public static function belgianPhone(?string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits !== '' && preg_match('/^(0032|32|0)4?\d{8,9}$/', $digits) === 1;
    }

    private function similarToRecent(string $form, string $message): bool
    {
        $hash = self::messageHash($message);

        if ($hash === null) {
            return false;
        }

        $similarity = (array) ($this->config['similarity'] ?? []);
        $maxDistance = (int) ($similarity['max_hamming'] ?? 12);
        $window = (int) ($similarity['window_seconds'] ?? 3600);
        $since = time() - $window;

        try {
            $entries = Cache::get(self::SIMHASH_KEY_PREFIX . $form, []);
        } catch (\Throwable $e) {
            return false;
        }

        foreach (is_array($entries) ? $entries : [] as $entry) {
            if (! is_array($entry) || count($entry) < 2 || (int) $entry[1] < $since) {
                continue;
            }

            if (SimHash::distance($hash, (string) $entry[0]) <= $maxDistance) {
                return true;
            }
        }

        return false;
    }

    private function nameRepeated(string $name): bool
    {
        $key = self::nameKey($name);

        if ($key === null) {
            return false;
        }

        try {
            return (int) Cache::get($key, 0) >= 3;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

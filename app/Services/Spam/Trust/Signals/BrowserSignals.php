<?php

namespace App\Services\Spam\Trust\Signals;

use App\Services\Spam\Trust\SignalProvider;
use App\Services\Spam\Trust\TrustContext;
use App\Services\Spam\Trust\TrustSignal;

/**
 * Is the POST shaped like a real browser submitting a form? A scripted
 * client that solved the captcha through a service still tends to send a
 * library user agent, no Sec-Fetch-* headers, a bare Accept header or no
 * Accept-Language at all. Every modern browser sends all of them on a
 * same-origin form POST.
 */
final class BrowserSignals implements SignalProvider
{
    private const NON_BROWSER = '/curl|wget|python|go-http|okhttp|java\/|node-fetch|axios|httpclient|libwww|scrapy|headless|phantomjs|selenium|puppeteer/i';

    private const SITE_LANGUAGES = ['nl', 'fr', 'en', 'de'];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function collect(TrustContext $ctx): array
    {
        $group = TrustSignal::GROUP_BROWSER;
        $request = $ctx->request;
        $ua = trim((string) $request->userAgent());
        $signals = [];

        if ($ua === '' || preg_match(self::NON_BROWSER, $ua) === 1) {
            $signals[] = TrustSignal::risk('ua_non_browser', $group, 4);
        } else {
            $inconsistent = false;

            if (trim((string) $request->header('Sec-Fetch-Site')) === '' || trim((string) $request->header('Sec-Fetch-Mode')) === '') {
                $signals[] = TrustSignal::risk('sec_fetch_missing', $group, 2);
                $inconsistent = true;
            }

            $accept = strtolower((string) $request->header('Accept'));

            if (! str_contains($accept, 'text/html') && ! str_contains($accept, '*/*')) {
                $signals[] = TrustSignal::risk('accept_not_html', $group, 2);
                $inconsistent = true;
            }

            if (trim((string) $request->header('Accept-Language')) === '') {
                $signals[] = TrustSignal::risk('accept_language_missing', $group, 1);
                $inconsistent = true;
            }

            if (! $inconsistent) {
                $signals[] = TrustSignal::positive('browser_consistent', $group);
            }
        }

        $language = trim((string) $request->header('Accept-Language'));

        if ($language !== '' && ! $this->mentionsSiteLanguage($language)) {
            $signals[] = TrustSignal::risk('accept_language_mismatch', $group, 1);
        }

        return $signals;
    }

    private function mentionsSiteLanguage(string $header): bool
    {
        foreach (explode(',', strtolower($header)) as $part) {
            $tag = trim(explode(';', $part, 2)[0]);
            $primary = explode('-', $tag, 2)[0];

            if (in_array($primary, self::SITE_LANGUAGES, true)) {
                return true;
            }
        }

        return false;
    }
}

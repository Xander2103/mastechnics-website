<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\CaptchaVerdict;
use App\Services\Spam\FormTimingToken;
use App\Services\Spam\SubmissionFacts;
use App\Services\Spam\Trust\Signals\BrowserSignals;
use App\Services\Spam\Trust\TrustContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class BrowserSignalsTest extends TestCase
{
    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    /** @return array<int, string> */
    private function codes(array $server): array
    {
        $request = Request::create('/nl/contact', 'POST', [], [], [], $server);
        $ctx = new TrustContext('contact', $request, new SubmissionFacts('a@example.com'), CaptchaVerdict::disabled(), FormTimingToken::OK, 30, false);

        return array_map(fn ($s) => ($s->positive ? '+' : '') . $s->code, (new BrowserSignals([]))->collect($ctx));
    }

    public function test_consistent_browser_post_is_positive(): void
    {
        $this->assertSame(['+browser_consistent'], $this->codes([
            'HTTP_USER_AGENT' => self::CHROME,
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
            'HTTP_ACCEPT_LANGUAGE' => 'fr-BE,fr;q=0.9',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
        ]));
    }

    public function test_missing_user_agent_and_library_agents_are_non_browser(): void
    {
        // Request::create() fills in a default UA and Accept-Language; a real
        // scripted POST without them arrives with empty headers.
        $this->assertSame(['ua_non_browser'], $this->codes(['HTTP_USER_AGENT' => '', 'HTTP_ACCEPT_LANGUAGE' => '']));

        foreach (['curl/8.1', 'python-requests/2.31', 'Go-http-client/2.0', 'okhttp/4.9', 'axios/1.6', 'HeadlessChrome/120', 'Scrapy/2.11'] as $ua) {
            $this->assertContains('ua_non_browser', $this->codes(['HTTP_USER_AGENT' => $ua]), $ua);
        }
    }

    public function test_browser_agent_without_sec_fetch_accept_or_language_is_inconsistent(): void
    {
        $codes = $this->codes(['HTTP_USER_AGENT' => self::CHROME, 'HTTP_ACCEPT' => 'application/json', 'HTTP_ACCEPT_LANGUAGE' => '']);

        $this->assertContains('sec_fetch_missing', $codes);
        $this->assertContains('accept_not_html', $codes);
        $this->assertContains('accept_language_missing', $codes);
        $this->assertNotContains('+browser_consistent', $codes);
    }

    public function test_wildcard_accept_is_fine_but_foreign_language_is_flagged(): void
    {
        $codes = $this->codes([
            'HTTP_USER_AGENT' => self::CHROME,
            'HTTP_ACCEPT' => '*/*',
            'HTTP_ACCEPT_LANGUAGE' => 'ru-RU,ru;q=0.9',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
            'HTTP_SEC_FETCH_MODE' => 'navigate',
        ]);

        $this->assertContains('+browser_consistent', $codes);
        $this->assertContains('accept_language_mismatch', $codes);
        $this->assertNotContains('accept_not_html', $codes);
    }
}

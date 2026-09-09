<?php

namespace Tests\Unit\Spam;

use App\Services\Spam\SecurityEventRecorder;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecurityEventRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_masked_to_two_characters_and_domain(): void
    {
        $this->assertSame('xa***@gmail.com', SecurityEventRecorder::maskEmail('xander@Gmail.com'));
        $this->assertSame('j***@example.com', SecurityEventRecorder::maskEmail('jo@example.com'));
        $this->assertSame('a***@example.com', SecurityEventRecorder::maskEmail('a@example.com'));
        $this->assertNull(SecurityEventRecorder::maskEmail(''));
        $this->assertNull(SecurityEventRecorder::maskEmail('no-at-sign'));
    }

    public function test_email_hash_is_stable_across_spellings_and_short(): void
    {
        $this->assertSame(SecurityEventRecorder::hashEmail('Jan@Gmail.com'), SecurityEventRecorder::hashEmail('j.a.n+x@googlemail.com'));
        $this->assertSame(16, strlen((string) SecurityEventRecorder::hashEmail('jan@example.com')));
        $this->assertNull(SecurityEventRecorder::hashEmail(null));
    }

    public function test_user_agent_is_reduced_to_a_family(): void
    {
        $this->assertSame('Chrome 128 · Windows', SecurityEventRecorder::userAgentFamily('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'));
        $this->assertSame('Safari 17 · iOS', SecurityEventRecorder::userAgentFamily('Mozilla/5.0 (iPhone; CPU iPhone OS 17_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.6 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Firefox 130 · Windows', SecurityEventRecorder::userAgentFamily('Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:130.0) Gecko/20100101 Firefox/130.0'));
        $this->assertSame('Edge 127 · Windows', SecurityEventRecorder::userAgentFamily('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36 Edg/127.0.0.0'));
        $this->assertSame('curl', SecurityEventRecorder::userAgentFamily('curl/8.4.0'));
        $this->assertSame('python-requests', SecurityEventRecorder::userAgentFamily('python-requests/2.32 CPython/3.12'));
        $this->assertNull(SecurityEventRecorder::userAgentFamily(''));
        $this->assertLessThanOrEqual(60, strlen((string) SecurityEventRecorder::userAgentFamily(str_repeat('LongProductNameWithoutSlash', 5))));
    }

    public function test_simple_event_is_recorded_with_hashes_only(): void
    {
        $request = Request::create('/nl/contact', 'POST', [], [], [], ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'curl/8.4.0']);

        $event = (new SecurityEventRecorder())->recordSimple('contact', TrustDecision::BLOCKED, ['blocklist'], $request, 'victim@example.com');

        $this->assertNotNull($event);
        $this->assertSame('vi***@example.com', $event->email_masked);
        $this->assertSame('curl', $event->user_agent_family);
        $this->assertSame('nl', $event->locale);
        $this->assertSame(['blocklist'], $event->reasons);
        $this->assertStringNotContainsString('203.0.113.9', json_encode($event->toArray()));
    }

    public function test_write_failure_is_logged_and_never_thrown(): void
    {
        Schema::drop('form_security_events');
        Log::shouldReceive('error')->once()->withArgs(fn ($message) => $message === 'security_log.write_failed');

        $event = (new SecurityEventRecorder())->recordSimple('contact', TrustDecision::BLOCKED, ['honeypot']);

        $this->assertNull($event);
    }
}

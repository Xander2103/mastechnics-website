<?php

namespace Tests\Unit;

use App\Models\CustomerRequest;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $attrs = []): CustomerRequest
    {
        return CustomerRequest::create(array_merge([
            'locale'         => 'nl',
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Test',
            'customer_email' => 'test@example.com',
            'description'    => 'Test',
            'status'         => 'new',
        ], $attrs));
    }

    public function test_new_request_is_not_flagged_before_threshold(): void
    {
        $request = $this->makeRequest();

        $this->assertFalse(ReminderService::isNewNotViewed($request));
    }

    public function test_new_request_is_flagged_after_24_hours(): void
    {
        $request = $this->makeRequest();
        $request->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->assertTrue(ReminderService::isNewNotViewed($request));
    }

    public function test_viewed_request_flagged_waiting_after_48_hours(): void
    {
        $request = $this->makeRequest(['status' => 'viewed', 'viewed_at' => now()->subHours(49)]);

        $this->assertTrue(ReminderService::isWaitingContact($request));
    }

    public function test_quote_sent_flagged_follow_up_after_configured_days(): void
    {
        $request = $this->makeRequest([
            'status'        => 'quote_sent',
            'quote_sent_at' => now()->subDays(8),
        ]);

        $this->assertTrue(ReminderService::isQuoteAwaitingReply($request));
    }

    public function test_urgent_category_flags_urgent_regardless_of_urgency_level(): void
    {
        $request = $this->makeRequest(['service_category' => 'dringend_lek']);

        $this->assertTrue(ReminderService::isUrgent($request));
    }

    public function test_won_or_lost_requests_are_never_urgent(): void
    {
        $request = $this->makeRequest(['service_category' => 'dringend_lek', 'status' => 'won']);

        $this->assertFalse(ReminderService::isUrgent($request));
    }

    public function test_primary_badge_prioritizes_urgent_over_other_reminders(): void
    {
        $request = $this->makeRequest([
            'service_category' => 'dringend_lek',
            'status'            => 'quote_sent',
            'quote_sent_at'     => now()->subDays(10),
        ]);

        $badge = ReminderService::primaryBadge($request);

        $this->assertSame(ReminderService::URGENT, $badge['code']);
    }

    public function test_no_badge_when_nothing_applies(): void
    {
        $request = $this->makeRequest(['status' => 'contacted']);

        $this->assertNull(ReminderService::primaryBadge($request));
    }
}

<?php

namespace Tests\Feature\Spam;

use App\Models\FormSecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule;
use Tests\TestCase;

class PruneSecurityLogTest extends TestCase
{
    use RefreshDatabase;

    private function event(int $daysAgo): FormSecurityEvent
    {
        return FormSecurityEvent::create([
            'occurred_at' => now()->subDays($daysAgo),
            'form' => 'contact',
            'decision' => 'blocked',
            'reasons' => ['honeypot'],
            'signals' => [],
        ]);
    }

    public function test_events_older_than_the_retention_window_are_deleted(): void
    {
        config(['form-protection.security_log.retention_days' => 30]);

        $this->event(1);
        $this->event(29);
        $this->event(31);
        $this->event(120);

        $this->artisan('forms:prune-security-log')
            ->expectsOutputToContain('2 event(s) ouder dan 30 dagen')
            ->assertSuccessful();

        $this->assertDatabaseCount('form_security_events', 2);
        $this->assertSame(0, FormSecurityEvent::where('occurred_at', '<', now()->subDays(30))->count());
    }

    public function test_days_option_overrides_the_configured_retention(): void
    {
        config(['form-protection.security_log.retention_days' => 90]);
        $this->event(10);
        $this->event(5);

        $this->artisan('forms:prune-security-log', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseCount('form_security_events', 1);
    }

    public function test_retention_below_one_day_is_refused(): void
    {
        $this->event(3);

        $this->artisan('forms:prune-security-log', ['--days' => 0])->assertFailed();

        $this->assertDatabaseCount('form_security_events', 1);
    }

    public function test_prune_is_scheduled_daily(): void
    {
        $events = collect(Schedule::events())->filter(fn ($event) => str_contains((string) $event->command, 'forms:prune-security-log'));

        $this->assertCount(1, $events, 'forms:prune-security-log must be scheduled exactly once');
        $this->assertSame('30 3 * * *', $events->first()->expression);
    }
}

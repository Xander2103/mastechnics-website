<?php

namespace Tests\Feature\Spam;

use App\Mail\ContactMessageMail;
use App\Services\Spam\FormMailer;
use App\Services\Spam\FormProtectionLog;
use App\Services\Spam\MailBudget;
use App\Services\Spam\PublicFormGuard;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\InteractsWithFormProtection;
use Tests\TestCase;

/**
 * The joint external-mail circuit breaker and the trust requirement of
 * FormMailer, proven at the transport layer: SpyMailTransport counts every
 * call that would reach Brevo/SMTP. No controller involved — this is the
 * last gate before the provider, whatever the caller did.
 */
class MailCircuitBreakerTest extends TestCase
{
    use InteractsWithFormProtection;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'form-protection.mail.guard_enabled' => true,
            'form-protection.mail.customer_confirmation_enabled' => true,
            'form-protection.mail.customer_confirmation_daily_limit' => 500,
            'form-protection.mail.admin_notification_daily_limit' => 500,
            'form-protection.mail.burst_limit_per_hour' => 500,
            'form-protection.mail.external_daily_limit' => 20,
            'form-protection.mail.external_limit_per_10_minutes' => 4,
        ]);

        $this->useSpyMailTransport();
    }

    private function mailer(): FormMailer
    {
        // Fresh instances so the budget reads the config set in the test.
        return new FormMailer(
            new MailBudget((array) config('form-protection.mail')),
            $this->app->make(FormProtectionLog::class)
        );
    }

    private function mailable(int $i = 0): ContactMessageMail
    {
        return new ContactMessageMail([
            'name' => "Klant {$i}",
            'email' => "klant{$i}@example.com",
            'phone' => null,
            'subject' => "Vraag {$i}",
            'message' => "Bericht nummer {$i}",
            'locale' => 'nl',
            'submitted_at' => now(),
            'source_url' => 'https://mastechnics.test/nl/contact',
        ]);
    }

    private function trusted(): TrustDecision
    {
        return TrustDecision::trustedForTests();
    }

    public function test_burst_limit_stops_the_fifth_external_mail_within_ten_minutes(): void
    {
        $mailer = $this->mailer();

        for ($i = 1; $i <= 4; $i++) {
            $this->assertTrue($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable($i), $this->trusted()));
        }

        $this->assertFalse($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(5), $this->trusted()));
        $this->assertFalse($mailer->sendCustomer(PublicFormGuard::FORM_REQUEST, 'klant6@example.com', $this->mailable(6), $this->trusted()));

        $this->assertProviderCalls(4, 'the burst limit must stop every further provider call');
        $this->assertDatabaseHas('mail_logs', ['status' => 'skipped', 'error' => FormProtectionLog::MAIL_CIRCUIT_BURST]);

        $state = $this->app->make(MailBudget::class)->circuitState();
        $this->assertTrue($state['open']);
        $this->assertSame(FormProtectionLog::MAIL_CIRCUIT_BURST, $state['reason']);
        $this->assertSame(4, $state['burst_used']);
    }

    public function test_daily_limit_is_one_counter_for_admin_and_customer_mails_across_both_forms(): void
    {
        config(['form-protection.mail.external_limit_per_10_minutes' => 500]);
        $mailer = $this->mailer();

        for ($i = 1; $i <= 20; $i++) {
            $form = $i % 2 === 0 ? PublicFormGuard::FORM_CONTACT : PublicFormGuard::FORM_REQUEST;

            $sent = $i % 3 === 0
                ? $mailer->sendCustomer($form, "klant{$i}@example.com", $this->mailable($i), $this->trusted())
                : $mailer->sendAdmin($form, 'martin@mastechnics.be', $this->mailable($i), $this->trusted());

            $this->assertTrue($sent, "mail {$i} is within the daily limit");
        }

        $this->assertFalse($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(21), $this->trusted()));
        $this->assertFalse($mailer->sendCustomer(PublicFormGuard::FORM_REQUEST, 'klant22@example.com', $this->mailable(22), $this->trusted()));

        $this->assertProviderCalls(20);
        $this->assertDatabaseHas('mail_logs', ['status' => 'skipped', 'error' => FormProtectionLog::MAIL_CIRCUIT_DAILY]);

        $state = $this->app->make(MailBudget::class)->circuitState();
        $this->assertTrue($state['open']);
        $this->assertSame(FormProtectionLog::MAIL_CIRCUIT_DAILY, $state['reason']);
        $this->assertSame(20, $state['daily_used']);
        $this->assertSame(20, $state['daily_limit']);
    }

    public function test_circuit_state_is_closed_below_the_limits(): void
    {
        $this->mailer()->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(), $this->trusted());

        $state = $this->app->make(MailBudget::class)->circuitState();

        $this->assertFalse($state['open']);
        $this->assertNull($state['reason']);
        $this->assertSame(1, $state['daily_used']);
        $this->assertSame(1, $state['burst_used']);
    }

    public function test_disabled_customer_confirmation_never_builds_the_mailable_nor_calls_the_transport(): void
    {
        config(['form-protection.mail.customer_confirmation_enabled' => false]);
        $mailer = $this->mailer();
        $built = false;

        $result = $mailer->sendCustomer(PublicFormGuard::FORM_CONTACT, 'klant@example.com', function () use (&$built) {
            $built = true;

            return $this->mailable();
        }, $this->trusted());

        $this->assertFalse($result);
        $this->assertFalse($built, 'the customer mailable must not even be constructed');
        $this->assertProviderCalls(0);
        $this->assertSame(FormMailer::OUTCOME_SKIPPED, $mailer->lastOutcome()['customer']);
        $this->assertSame(FormProtectionLog::MAIL_CUSTOMER_DISABLED, $mailer->lastOutcome()['reason']);

        // The admin mail is unaffected by the customer switch.
        $this->assertTrue($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(), $this->trusted()));
        $this->assertProviderCalls(1);
    }

    public function test_a_decision_that_is_not_trusted_causes_zero_transport_calls_and_no_budget_use(): void
    {
        $mailer = $this->mailer();
        $review = new TrustDecision(TrustDecision::NEEDS_REVIEW, 5, 1);

        $this->assertFalse($mailer->sendAdmin(PublicFormGuard::FORM_REQUEST, 'martin@mastechnics.be', $this->mailable(), $review));
        $this->assertFalse($mailer->sendCustomer(PublicFormGuard::FORM_REQUEST, 'klant@example.com', $this->mailable(), $review));

        $this->assertProviderCalls(0);
        $this->assertDatabaseCount('mail_logs', 2);
        $this->assertDatabaseHas('mail_logs', ['status' => 'skipped', 'error' => FormProtectionLog::MAIL_NOT_TRUSTED, 'recipient' => 'martin@mastechnics.be']);

        $state = $this->app->make(MailBudget::class)->circuitState();
        $this->assertSame(0, $state['daily_used'], 'a skipped mail must not consume the external budget');

        $outcome = $mailer->lastOutcome();
        $this->assertSame(FormMailer::OUTCOME_SKIPPED, $outcome['admin']);
        $this->assertSame(FormMailer::OUTCOME_SKIPPED, $outcome['customer']);
        $this->assertSame(FormProtectionLog::MAIL_NOT_TRUSTED, $outcome['reason']);
    }

    public function test_outcome_reports_sent_mails_for_the_security_log(): void
    {
        $mailer = $this->mailer();
        $mailer->beginSubmission();

        $mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(), $this->trusted());
        $mailer->sendCustomer(PublicFormGuard::FORM_CONTACT, 'klant@example.com', $this->mailable(), $this->trusted());

        $this->assertSame([
            'admin' => FormMailer::OUTCOME_SENT,
            'customer' => FormMailer::OUTCOME_SENT,
            'reason' => null,
            'sent' => 2,
            'skipped' => 0,
        ], $mailer->lastOutcome());

        $mailer->beginSubmission();
        $this->assertSame(FormMailer::OUTCOME_NOT_APPLICABLE, $mailer->lastOutcome()['admin']);
    }

    public function test_reservation_waits_for_the_cache_lock_and_refuses_when_it_cannot_get_it(): void
    {
        // Another worker holds the budget lock for longer than the wait
        // window: the reservation must refuse (fail closed), not send
        // unbudgeted and not throw.
        $lock = Cache::lock('form-protection:mail-budget', 30);
        $this->assertTrue($lock->get(), 'test could not take the lock');

        try {
            $mailer = $this->mailer();
            $this->assertFalse($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(), $this->trusted()));
            $this->assertProviderCalls(0);
            $this->assertSame(FormProtectionLog::MAIL_BUDGET_ADMIN, $mailer->lastOutcome()['reason']);
        } finally {
            $lock->release();
        }

        // Lock released: the next reservation goes through.
        $this->assertTrue($this->mailer()->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(), $this->trusted()));
        $this->assertProviderCalls(1);
    }

    public function test_guard_disabled_bypasses_only_the_counters_not_the_trust_requirement(): void
    {
        config(['form-protection.mail.guard_enabled' => false, 'form-protection.mail.external_limit_per_10_minutes' => 1]);
        $mailer = $this->mailer();

        $this->assertTrue($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(1), $this->trusted()));
        $this->assertTrue($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(2), $this->trusted()));
        $this->assertFalse($mailer->sendAdmin(PublicFormGuard::FORM_CONTACT, 'martin@mastechnics.be', $this->mailable(3), new TrustDecision(TrustDecision::BLOCKED, 0, 0)));

        $this->assertProviderCalls(2);
        $this->assertFalse($this->app->make(MailBudget::class)->circuitState()['enabled']);
    }
}

<?php

namespace Tests\Unit;

use App\Mail\CustomerRequestConfirmationMail;
use App\Models\CustomerRequest;
use App\Models\MailLog;
use App\Services\MailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(): CustomerRequest
    {
        return CustomerRequest::create([
            'locale'         => 'nl',
            'service_slug'   => 'heating',
            'request_type'   => 'repair',
            'customer_name'  => 'Test Klant',
            'customer_email' => 'klant@example.com',
            'description'    => 'Test',
            'status'         => 'new',
        ]);
    }

    public function test_successful_send_is_logged_with_sent_status(): void
    {
        Mail::fake();

        $customerRequest = $this->makeRequest();

        $result = MailDispatcher::send(
            'klant@example.com',
            new CustomerRequestConfirmationMail($customerRequest),
            $customerRequest
        );

        $this->assertTrue($result);

        $this->assertDatabaseHas('mail_logs', [
            'customer_request_id' => $customerRequest->id,
            'recipient'           => 'klant@example.com',
            'mailable'            => 'CustomerRequestConfirmationMail',
            'status'              => 'sent',
        ]);
    }

    public function test_failed_send_is_logged_with_error_and_does_not_throw(): void
    {
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP timeout'));

        $customerRequest = $this->makeRequest();

        $result = MailDispatcher::send(
            'klant@example.com',
            new CustomerRequestConfirmationMail($customerRequest),
            $customerRequest
        );

        $this->assertFalse($result);

        $log = MailLog::where('customer_request_id', $customerRequest->id)->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('SMTP timeout', $log->error);
    }
}

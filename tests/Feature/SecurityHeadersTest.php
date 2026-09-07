<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_responses_are_not_cacheable(): void
    {
        $response = $this->withSession($this->adminSession())->get(route('admin.requests.index'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_public_pages_stay_cacheable(): void
    {
        $response = $this->get('/nl');

        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_public_pages_have_security_headers(): void
    {
        $response = $this->get('/nl');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );
    }

    public function test_admin_pages_have_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );
    }
}

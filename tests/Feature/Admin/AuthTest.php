<?php

namespace Tests\Feature\Admin;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_log_the_admin_in(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@mastechnics.be',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post(route('admin.login.submit'), [
            'email' => 'martin@mastechnics.be',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.requests.index'));
        $this->assertTrue(session()->has('admin_user_email'));
    }

    public function test_sixth_failed_login_attempt_in_a_minute_is_throttled(): void
    {
        AdminUser::create([
            'name' => 'Martin',
            'email' => 'martin@mastechnics.be',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post(route('admin.login.submit'), [
                'email' => 'martin@mastechnics.be',
                'password' => 'wrong-password',
            ]);

            $response->assertSessionHasErrors('email');
        }

        $sixth = $this->post(route('admin.login.submit'), [
            'email' => 'martin@mastechnics.be',
            'password' => 'wrong-password',
        ]);

        $sixth->assertStatus(429);
    }
}

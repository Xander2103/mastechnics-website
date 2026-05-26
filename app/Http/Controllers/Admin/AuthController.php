<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session()->has('admin_user_email')) {
            return redirect()->route('admin.requests.index');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validatedData = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $adminUser = $this->findAdminUser(
            $validatedData['email'],
            $validatedData['password']
        );

        if ($adminUser === null) {
            return back()
                ->withErrors([
                    'email' => 'De login gegevens zijn niet correct.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        session([
            'admin_user_name' => $adminUser['name'],
            'admin_user_email' => $adminUser['email'],
        ]);

        return redirect()->intended(route('admin.requests.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'admin_user_name',
            'admin_user_email',
        ]);

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function findAdminUser(string $email, string $password): ?array
    {
        foreach (config('admin.users', []) as $adminUser) {
            if (
                strtolower($adminUser['email']) === strtolower($email)
                && $adminUser['password'] === $password
            ) {
                return $adminUser;
            }
        }

        return null;
    }
}
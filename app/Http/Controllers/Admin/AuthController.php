<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

        // admin:create lowercases the stored address; match the same way so
        // a mixed-case login works on case-sensitive databases (SQLite).
        $adminUser = AdminUser::where('email', Str::lower($validatedData['email']))->first();

        if (
            $adminUser === null
            || ! Hash::check($validatedData['password'], $adminUser->password)
        ) {
            return back()
                ->withErrors([
                    'email' => 'De login gegevens zijn niet correct.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        session($adminUser->sessionPayload());

        return redirect()->intended(route('admin.requests.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        // Invalidate the whole session (new id, all data dropped) instead of
        // only forgetting the admin keys, so nothing survives a logout.
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

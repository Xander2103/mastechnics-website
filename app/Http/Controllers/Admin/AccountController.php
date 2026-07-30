<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\BlockedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.edit', [
            'admins' => AdminUser::orderBy('created_at')->get(['id', 'name', 'email', 'created_at']),
            'activeBlockCount' => BlockedEmail::where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->count(),
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $adminUser = $this->currentAdmin();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('admin_users', 'email')->ignore($adminUser->id),
            ],
        ]);

        if (! Hash::check($validated['current_password'], $adminUser->password)) {
            return back()
                ->withErrors(['current_password' => 'Het huidige wachtwoord is niet correct.'])
                ->onlyInput('email');
        }

        $adminUser->update(['email' => $validated['email']]);

        // Keep the active session working; historical records such as
        // standard_reply_sent_by and note author_email keep the old address.
        session(['admin_user_email' => $adminUser->email]);

        return back()->with('success', 'account_email_updated');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $adminUser = $this->currentAdmin();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        if (! Hash::check($validated['current_password'], $adminUser->password)) {
            return back()->withErrors(['current_password' => 'Het huidige wachtwoord is niet correct.']);
        }

        $adminUser->update(['password' => Hash::make($validated['password'])]);

        $request->session()->regenerate();

        return back()->with('success', 'account_password_updated');
    }

    private function currentAdmin(): AdminUser
    {
        $adminUser = AdminUser::where('email', session('admin_user_email'))->first();

        abort_if($adminUser === null, 403);

        return $adminUser;
    }
}

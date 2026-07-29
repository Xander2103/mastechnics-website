<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.edit');
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
            'password' => ['required', 'string', 'min:12', 'confirmed'],
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

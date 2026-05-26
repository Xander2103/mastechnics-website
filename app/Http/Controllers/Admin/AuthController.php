<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (session()->has('admin_user_id')) {
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

        $adminUser = AdminUser::where('email', $validatedData['email'])->first();

        if (!$adminUser || !Hash::check($validatedData['password'], $adminUser->password)) {
            return back()
                ->withErrors([
                    'email' => 'De login gegevens zijn niet correct.',
                ])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        session([
            'admin_user_id' => $adminUser->id,
            'admin_user_name' => $adminUser->name,
        ]);

        return redirect()->intended(route('admin.requests.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'admin_user_id',
            'admin_user_name',
        ]);

        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->session()->get('admin_user_email');

        if (! is_string($email) || $email === '') {
            return redirect()->route('admin.login');
        }

        // Re-validate the session against the account on every request: a
        // deleted admin or a changed password must revoke every existing
        // session immediately, not only after SESSION_LIFETIME expires.
        $adminUser = AdminUser::where('email', $email)->first();

        if (
            $adminUser === null
            || ! hash_equals(
                $adminUser->sessionFingerprint(),
                (string) $request->session()->get(AdminUser::SESSION_FINGERPRINT_KEY, '')
            )
        ) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}

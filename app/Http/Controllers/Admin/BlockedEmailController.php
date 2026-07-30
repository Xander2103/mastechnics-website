<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlockedEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlockedEmailController extends Controller
{
    public function index(): View
    {
        return view('admin.blocked-emails.index', [
            'blockedEmails' => BlockedEmail::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
            'duration' => ['required', 'in:permanent,24h,7d,30d'],
        ]);

        $expiresAt = match ($validated['duration']) {
            'permanent' => null,
            '24h' => now()->addHours(24),
            '7d' => now()->addDays(7),
            '30d' => now()->addDays(30),
        };

        // One row per address: re-blocking a previously unblocked or expired
        // address reactivates it with the new reason/duration.
        BlockedEmail::updateOrCreate(
            ['email' => BlockedEmail::normalizeEmail($validated['email'])],
            [
                'reason' => $validated['reason'] ?? null,
                'blocked_by' => (string) session('admin_user_email'),
                'is_active' => true,
                'expires_at' => $expiresAt,
            ]
        );

        return redirect()
            ->route('admin.blocked-emails.index')
            ->with('success', 'email_blocked');
    }

    public function unblock(BlockedEmail $blockedEmail): RedirectResponse
    {
        $blockedEmail->update(['is_active' => false]);

        return redirect()
            ->route('admin.blocked-emails.index')
            ->with('success', 'email_unblocked');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FormSecurityEvent;
use App\Services\Spam\SecurityDashboard;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin view on the form security log (form_security_events): what the
 * trust gate decided, why, and what happened to the mails — without
 * touching server logs. Read-only; rows contain hashes and labels only.
 */
class SecurityLogController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'form' => ['nullable', Rule::in(array_keys(FormSecurityEvent::FORM_LABELS))],
            'decision' => ['nullable', Rule::in(array_keys(FormSecurityEvent::DECISION_LABELS))],
            'reason' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'mail' => ['nullable', Rule::in(['sent', 'skipped'])],
        ]);

        $filters = array_merge([
            'date_from' => '', 'date_to' => '', 'form' => '', 'decision' => '', 'reason' => '', 'mail' => '',
        ], array_map(fn ($v) => (string) ($v ?? ''), $filters));

        $events = FormSecurityEvent::query()
            ->when($filters['date_from'] !== '', fn ($q) => $q->where('occurred_at', '>=', $filters['date_from'] . ' 00:00:00'))
            ->when($filters['date_to'] !== '', fn ($q) => $q->where('occurred_at', '<=', $filters['date_to'] . ' 23:59:59'))
            ->when($filters['form'] !== '', fn ($q) => $q->where('form', $filters['form']))
            ->when($filters['decision'] !== '', fn ($q) => $q->where('decision', $filters['decision']))
            // reasons is a JSON array of codes; a LIKE on the serialized text
            // works identically on SQLite (tests) and MySQL (production).
            ->when($filters['reason'] !== '', fn ($q) => $q->where('reasons', 'like', '%"' . $filters['reason'] . '"%'))
            ->when($filters['mail'] === 'sent', fn ($q) => $q->where(fn ($w) => $w->where('mail_admin', 'sent')->orWhere('mail_customer', 'sent')))
            ->when($filters['mail'] === 'skipped', fn ($q) => $q
                ->where('mail_admin', '!=', 'sent')
                ->where('mail_customer', '!=', 'sent')
                ->where(fn ($w) => $w->where('mail_admin', 'skipped')->orWhere('mail_customer', 'skipped')))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.security.index', [
            'events' => $events,
            'filters' => $filters,
            'security' => app(SecurityDashboard::class)->summary(),
            'reasonLabels' => FormSecurityEvent::REASON_LABELS,
            'decisionLabels' => FormSecurityEvent::DECISION_LABELS,
            'formLabels' => FormSecurityEvent::FORM_LABELS,
            'needsReviewVerdict' => TrustDecision::NEEDS_REVIEW,
        ]);
    }
}

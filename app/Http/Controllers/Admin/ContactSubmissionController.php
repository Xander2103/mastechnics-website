<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactSubmission;
use App\Services\Spam\Trust\TrustDecision;
use App\Services\TrustReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only admin view on the contact form submissions, mainly so a
 * submission the trust gate stored as needs_review can be inspected and
 * released or marked as spam by hand. No editing.
 */
class ContactSubmissionController extends Controller
{
    public const PER_PAGE = 25;

    public const TRUST_FILTERS = [
        TrustDecision::NEEDS_REVIEW => 'Te controleren',
        TrustDecision::TRUSTED => 'Vertrouwd',
        TrustReviewService::VERDICT_SPAM => 'Spam',
    ];

    public function index(Request $request): View
    {
        $trust = $request->string('trust')->toString();

        if (! array_key_exists($trust, self::TRUST_FILTERS)) {
            $trust = '';
        }

        $submissions = ContactSubmission::query()
            ->when($trust !== '', fn ($q) => $q->where('trust_verdict', $trust))
            ->latest()
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.contact-submissions.index', [
            'submissions' => $submissions,
            'trust' => $trust,
            'trustFilters' => self::TRUST_FILTERS,
        ]);
    }

    public function show(ContactSubmission $contactSubmission): View
    {
        return view('admin.contact-submissions.show', [
            'submission' => $contactSubmission,
        ]);
    }

    public function updateTrust(Request $request, ContactSubmission $contactSubmission, TrustReviewService $review): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['release', 'spam'])],
        ]);

        if (! $contactSubmission->needsReview()) {
            return redirect()
                ->route('admin.contact-submissions.show', $contactSubmission)
                ->with('success', 'trust_not_reviewable');
        }

        $adminEmail = (string) session('admin_user_email');

        if ($validated['action'] === 'release') {
            $outcome = $review->release($contactSubmission, $adminEmail, $request);

            return redirect()
                ->route('admin.contact-submissions.show', $contactSubmission)
                ->with('success', ($outcome['sent'] ?? 0) > 0 ? 'trust_released' : 'trust_released_no_mail');
        }

        $review->markSpam($contactSubmission, $adminEmail, $request);

        return redirect()
            ->route('admin.contact-submissions.show', $contactSubmission)
            ->with('success', 'trust_marked_spam');
    }
}

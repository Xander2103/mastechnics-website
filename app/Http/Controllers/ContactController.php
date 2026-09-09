<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactMessageMail;
use App\Models\BlockedEmail;
use App\Models\ContactSubmission;
use App\Services\Spam\FormMailer;
use App\Services\Spam\PublicFormGuard;
use App\Services\Spam\Rejection;
use App\Services\Spam\SubmissionFacts;
use App\Services\Spam\Trust\TrustDecision;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    /**
     * Name of the (first) honeypot field rendered visually hidden in the
     * form. Humans never see or fill it; a submission that does carry a
     * value is answered with the normal success message but stored and
     * mailed nowhere.
     */
    public const HONEYPOT_FIELD = PublicFormGuard::HONEYPOT_FIELD;

    public function __construct(
        private readonly PublicFormGuard $guard,
        private readonly FormMailer $mailer,
    ) {
    }

    /**
     * Anti-abuse order (see PublicFormGuard): kill switch → idempotency →
     * attempt limits → validation → captcha → honeypot → fill time → accepted
     * limits → fingerprint → trust evaluation → blocklist → store → mail
     * (trusted only) → security event. No mail transport is reached before
     * the row exists, and never for a needs_review submission.
     */
    public function store(Request $request, string $locale): RedirectResponse
    {
        app()->setLocale($locale);
        $form = PublicFormGuard::FORM_CONTACT;

        if (! $this->guard->formEnabled($form)) {
            $this->guard->noteDisabled($form, $request);

            return $this->refuseWith($form, 'disabled', 'form_disabled', $locale);
        }

        // A fresh random token is rendered into a hidden field on every GET
        // of the contact page. A request replaying that same token (double
        // click, refresh, client retry) is not a new attempt at all — it's
        // handled before validation and before the rate limiter even sees
        // it, so a retry can never be wrongly rejected as "too many
        // attempts" and never eats into the visitor's quota.
        $token = $request->string('submission_token')->toString();
        if ($token === '') {
            $token = (string) Str::uuid();
        }

        if ($this->submissionAlreadyProcessed($token)) {
            return back()->with('success', 'contact_message_sent');
        }

        // Per-client + site-wide attempt limiter: bounds validation and
        // captcha checks a flood can trigger. Aborts with 429.
        $this->guard->enforceAttemptLimits($form, $request);

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email:rfc', 'max:254'],
            'phone'   => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\s().-]+$/'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ], [], $this->validationAttributes($locale));

        $facts = new SubmissionFacts(
            email: $validated['email'],
            phone: $validated['phone'] ?? null,
            message: $validated['message'],
            name: $validated['name'],
            locale: $locale,
        );

        // Captcha, honeypot, fill time, IP/e-mail/form/global limits, the
        // fingerprint and the trust evaluation. Nothing is stored or mailed
        // for a blocked decision; needs_review is stored but never mailed.
        $decision = $this->guard->screen($form, $request, $facts, 'email');

        if ($decision->blocked()) {
            return $this->refuse($form, $decision, $locale);
        }

        // Blocklist check happens after validation but before the submission
        // is claimed or the rate limiter is hit: a blocked sender consumes no
        // quota, stores nothing and triggers no mail (admin nor confirmation).
        // The message is deliberately neutral so the block itself is not
        // revealed. Applies only to this contact form, not the request wizard.
        if (BlockedEmail::isBlocked($validated['email'])) {
            $this->guard->noteBlocked($form, $request, $validated['email']);

            return back()
                ->withErrors(['blocked' => $this->blockedMessage($locale)])
                ->withInput();
        }

        $subject = trim($validated['subject'] ?? '') !== ''
            ? $validated['subject']
            : $this->defaultSubject($locale);

        // Header-injection defense: name and subject flow into mail headers
        // (Reply-To display name / subject line), so strip any CR/LF a
        // client could smuggle in even though the "string" rule allows them.
        $data = [
            'name'       => $this->stripHeaderControlChars($validated['name']),
            'email'      => $validated['email'],
            'phone'      => $validated['phone'] ?? null,
            'subject'    => $this->stripHeaderControlChars($subject),
            'message'    => $validated['message'],
            'locale'     => $locale,
            'submitted_at' => now(),
            'source_url' => url()->previous(),
        ];

        [$submission, $isNewSubmission] = $this->claimSubmission($token, $data, $decision);

        if (!$isNewSubmission) {
            // Either lost a race against another request with the same
            // token between the check above and this insert, or the
            // contact_submissions table itself is unavailable and
            // claimSubmission() already logged that — either way, the
            // idempotent outcome here is the same: don't send twice.
            return back()->with('success', 'contact_message_sent');
        }

        // Stored. Only now do counters move and may mail go out — and only
        // for a trusted decision (FormMailer refuses anything else).
        $this->guard->recordAccepted($form, $request, $facts, $decision);
        $this->mailer->beginSubmission();

        $this->mailer->sendAdmin(
            $form,
            config('site.contact_notification_email'),
            fn () => new ContactMessageMail($data),
            $decision
        );

        $this->mailer->sendCustomer(
            $form,
            $data['email'],
            fn () => new ContactMessageConfirmationMail($data),
            $decision
        );

        $outcome = $this->mailer->lastOutcome();

        if (($outcome['sent'] ?? 0) > 0) {
            $submission?->update(['mail_sent_at' => now()]);
        }

        $this->guard->recordEvent($decision, $submission, $outcome);

        return back()->with('success', 'contact_message_sent');
    }

    /**
     * Answer a rejected submission. Nothing has been stored or mailed when
     * this is called. A silent rejection (honeypot) shows the normal
     * success message so a bot learns nothing.
     */
    private function refuse(string $form, TrustDecision $decision, string $locale): RedirectResponse
    {
        $rejection = $decision->rejection ?? new Rejection('trust_score', 'captcha', 'captcha', true);

        if ($rejection->silentSuccess) {
            return back()->with('success', 'contact_message_sent');
        }

        return $this->refuseWith($form, $rejection->messageKind, $rejection->errorKey, $locale);
    }

    private function refuseWith(string $form, string $messageKind, string $errorKey, string $locale): RedirectResponse
    {
        return back()
            ->withErrors([$errorKey => $this->guard->message($form, $messageKind, $locale)])
            ->withInput();
    }

    /**
     * Storing the submission before sending mail is the actual idempotency
     * guard, but that supporting table must never be able to take the
     * contact form down the way mail_logs did — a missing/unavailable
     * contact_submissions table degrades to "always treat as new" (no
     * dedup possible, but mail still sends) rather than a 500.
     */
    private function submissionAlreadyProcessed(string $token): bool
    {
        try {
            return ContactSubmission::where('token', $token)->exists();
        } catch (QueryException $e) {
            Log::error('Contact submission idempotency check failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Atomically claim this submission token via the DB unique index — the
     * real guard against a race between two near-simultaneous requests
     * that both pass the exists() check in store() before either inserts.
     *
     * @return array{0: ?ContactSubmission, 1: bool} the submission row (null
     *   if it couldn't be persisted at all) and whether this call is the
     *   one that "won" and should proceed to send mail
     */
    private function claimSubmission(string $token, array $data, TrustDecision $decision): array
    {
        try {
            $submission = ContactSubmission::create([
                'token'   => $token,
                'name'    => $data['name'],
                'email'   => $data['email'],
                'phone'   => $data['phone'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'locale'  => $data['locale'],
                'trust_verdict' => $decision->verdict,
                'trust_score'   => $decision->risk,
                'trust_reasons' => $decision->reasons(),
            ]);

            return [$submission, true];
        } catch (QueryException $e) {
            try {
                $existing = ContactSubmission::where('token', $token)->first();
            } catch (QueryException) {
                // The table is unavailable altogether — the duplicate lookup
                // can't work either, so fall through to the always-new path.
                $existing = null;
            }

            if ($existing) {
                // Genuine duplicate: another request already claimed this
                // token (the race the exists() check in store() can't
                // fully close on its own).
                return [$existing, false];
            }

            // Not a duplicate — contact_submissions is unavailable for some
            // other reason (missing table, disk full, ...). Storing the
            // submission is not the goal in itself, sending mail once is;
            // fall back to always-new so the contact form keeps working.
            Log::error('Contact submission storage failed', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return [null, true];
        }
    }

    private function stripHeaderControlChars(string $value): string
    {
        return trim(str_replace(["\r", "\n"], ' ', $value));
    }

    private function defaultSubject(string $locale): string
    {
        $subjects = [
            'nl' => 'Contactaanvraag via website',
            'fr' => 'Demande de contact via le site web',
            'en' => 'Contact request via website',
        ];

        return $subjects[$locale] ?? $subjects['nl'];
    }

    private function blockedMessage(string $locale): string
    {
        $messages = [
            'nl' => 'Uw bericht kon niet worden verwerkt. Neem bij een dringende vraag telefonisch contact met ons op.',
            'fr' => "Votre message n'a pas pu être traité. Pour une demande urgente, contactez-nous par téléphone.",
            'en' => 'Your message could not be processed. For urgent enquiries, please contact us by phone.',
        ];

        return $messages[$locale] ?? $messages['nl'];
    }

    private function validationAttributes(string $locale): array
    {
        $labels = [
            'nl' => [
                'name' => 'naam',
                'email' => 'e-mailadres',
                'phone' => 'telefoonnummer',
                'subject' => 'onderwerp',
                'message' => 'bericht',
            ],
            'fr' => [
                'name' => 'nom',
                'email' => 'adresse e-mail',
                'phone' => 'numéro de téléphone',
                'subject' => 'sujet',
                'message' => 'message',
            ],
            'en' => [
                'name' => 'name',
                'email' => 'email address',
                'phone' => 'phone number',
                'subject' => 'subject',
                'message' => 'message',
            ],
        ];

        return $labels[$locale] ?? $labels['nl'];
    }
}

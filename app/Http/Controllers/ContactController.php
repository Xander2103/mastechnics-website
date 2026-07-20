<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactMessageMail;
use App\Models\ContactSubmission;
use App\Services\MailDispatcher;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        app()->setLocale($locale);

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

        $ip = $request->ip();
        $dailyKey = "contact-form-daily:{$ip}";
        $burstKey = "contact-form-burst:{$ip}";
        $dailyLimit = (int) config('site.contact_daily_limit', 10);
        $burstLimit = (int) config('site.contact_burst_limit_per_hour', 20);

        if (RateLimiter::tooManyAttempts($dailyKey, $dailyLimit)
            || RateLimiter::tooManyAttempts($burstKey, $burstLimit)
        ) {
            return back()
                ->withErrors(['rate_limit' => $this->rateLimitMessage($locale)])
                ->withInput();
        }

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\s().-]+$/'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ], [], $this->validationAttributes($locale));

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

        [$submission, $isNewSubmission] = $this->claimSubmission($token, $data);

        if (!$isNewSubmission) {
            // Either lost a race against another request with the same
            // token between the check above and this insert, or the
            // contact_submissions table itself is unavailable and
            // claimSubmission() already logged that — either way, the
            // idempotent outcome here is the same: don't send twice.
            return back()->with('success', 'contact_message_sent');
        }

        RateLimiter::hit($dailyKey, 86400);
        RateLimiter::hit($burstKey, 3600);

        MailDispatcher::send(
            config('site.contact_notification_email'),
            new ContactMessageMail($data)
        );

        MailDispatcher::send(
            $data['email'],
            new ContactMessageConfirmationMail($data)
        );

        $submission?->update(['mail_sent_at' => now()]);

        return back()->with('success', 'contact_message_sent');
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
    private function claimSubmission(string $token, array $data): array
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
            ]);

            return [$submission, true];
        } catch (QueryException $e) {
            $existing = ContactSubmission::where('token', $token)->first();

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

    private function rateLimitMessage(string $locale): string
    {
        $messages = [
            'nl' => 'U heeft al meerdere berichten verstuurd. Probeer later opnieuw of neem rechtstreeks contact op.',
            'fr' => "Vous avez déjà envoyé plusieurs messages. Veuillez réessayer plus tard ou nous contacter directement.",
            'en' => 'You have already sent several messages. Please try again later or contact us directly.',
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

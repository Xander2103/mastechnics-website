<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageConfirmationMail;
use App\Mail\ContactMessageMail;
use App\Services\MailDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        app()->setLocale($locale);

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

        RateLimiter::hit($dailyKey, 86400);
        RateLimiter::hit($burstKey, 3600);

        MailDispatcher::send(
            config('site.contact.email'),
            new ContactMessageMail($data)
        );

        MailDispatcher::send(
            $data['email'],
            new ContactMessageConfirmationMail($data)
        );

        return back()->with('success', 'contact_message_sent');
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

<?php

namespace App\Services;

use App\Models\CustomerRequest;

class StandardReplyService
{
    private const SUPPORTED_LOCALES = ['nl', 'fr', 'en'];

    private const TEXTS = [
        'nl' => [
            'subject' => 'Uw aanvraag bij Mastechnics — wij nemen binnenkort contact op',
            'greeting' => 'Dag :name,',
            'intro_with_category' => 'Bedankt voor uw aanvraag (:category) via Mastechnics.',
            'intro_without_category' => 'Bedankt voor uw aanvraag via Mastechnics.',
            'body' => 'We hebben uw aanvraag goed ontvangen en nemen zo snel mogelijk contact met u op om de details te bespreken.',
            'questions' => 'Heeft u intussen vragen? U kan ons bereiken op :phone.',
            'signoff' => "Met vriendelijke groeten\nMastechnics",
        ],
        'fr' => [
            'subject' => 'Votre demande chez Mastechnics — nous vous contactons bientôt',
            'greeting' => 'Bonjour :name,',
            'intro_with_category' => 'Merci pour votre demande (:category) via Mastechnics.',
            'intro_without_category' => 'Merci pour votre demande via Mastechnics.',
            'body' => 'Nous avons bien reçu votre demande et nous vous contacterons au plus vite pour discuter des détails.',
            'questions' => 'Vous avez des questions entre-temps ? Vous pouvez nous joindre au :phone.',
            'signoff' => "Cordialement\nMastechnics",
        ],
        'en' => [
            'subject' => 'Your request at Mastechnics — we will contact you soon',
            'greeting' => 'Hello :name,',
            'intro_with_category' => 'Thank you for your request (:category) via Mastechnics.',
            'intro_without_category' => 'Thank you for your request via Mastechnics.',
            'body' => 'We have received your request and will contact you as soon as possible to discuss the details.',
            'questions' => 'Any questions in the meantime? You can reach us at :phone.',
            'signoff' => "Kind regards\nMastechnics",
        ],
    ];

    public static function locale(CustomerRequest $customerRequest): string
    {
        $locale = $customerRequest->locale ?? 'nl';

        return in_array($locale, self::SUPPORTED_LOCALES, true) ? $locale : 'nl';
    }

    public static function subject(CustomerRequest $customerRequest): string
    {
        return self::TEXTS[self::locale($customerRequest)]['subject'];
    }

    public static function message(CustomerRequest $customerRequest): string
    {
        $locale = self::locale($customerRequest);
        $text = self::TEXTS[$locale];

        $category = self::categoryLabel($customerRequest, $locale);

        $intro = $category !== null
            ? str_replace(':category', $category, $text['intro_with_category'])
            : $text['intro_without_category'];

        $greeting = str_replace(':name', (string) $customerRequest->customer_name, $text['greeting']);
        $questions = str_replace(':phone', (string) config('site.contact.phone_display'), $text['questions']);

        return $greeting . "\n\n"
            . $intro . ' ' . $text['body'] . "\n\n"
            . $questions . "\n\n"
            . $text['signoff'];
    }

    public static function whatsappUrl(CustomerRequest $customerRequest): ?string
    {
        $number = self::normalizePhone($customerRequest->customer_phone);

        if ($number === null) {
            return null;
        }

        return 'https://wa.me/' . $number . '?text=' . rawurlencode(self::message($customerRequest));
    }

    private static function categoryLabel(CustomerRequest $customerRequest, string $locale): ?string
    {
        if (! $customerRequest->service_category) {
            return null;
        }

        foreach (config('request-flow.service_categories', []) as $category) {
            if (($category['value'] ?? null) === $customerRequest->service_category) {
                return $category['labels'][$locale] ?? $category['labels']['nl'] ?? null;
            }
        }

        return null;
    }

    private static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $number = preg_replace('/[\s\.\-\/\(\)]/', '', trim($phone));

        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        } elseif (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '32' . substr($number, 1);
        }

        $number = preg_replace('/\D/', '', $number);

        return strlen($number) > 5 ? $number : null;
    }
}

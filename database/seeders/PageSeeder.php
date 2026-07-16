<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $this->createHomePage();
        $this->createRequestPage();
        $this->createContactPage();
        $this->createServicePages();
    }

    private function createHomePage(): void
    {
        $home = Page::create([
            'code' => 'home',
            'type' => 'home',
            'is_active' => true,
        ]);

        $home->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'home',
                'title' => 'Technische service voor verwarming, airco, sanitair en koeling',
                'intro' => 'Start een slimme aanvraag voor herstelling, onderhoud of installatie. Vul meteen de juiste informatie in en ontvang sneller een richtprijs of duidelijke vervolgstap.',
                'content' => null,
                'meta_title' => 'Mastechnics — Technische service voor verwarming, airco, sanitair en meer',
                'meta_description' => 'Mastechnics biedt technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koeling. Vraag online een offerte of interventie aan.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'accueil',
                'title' => 'Service technique pour chauffage, climatisation, plomberie et réfrigération',
                'intro' => 'Démarrez une demande intelligente pour une réparation, un entretien ou une installation. Ajoutez directement les bonnes informations et recevez plus rapidement une estimation ou une prochaine étape claire.',
                'content' => null,
                'meta_title' => 'Mastechnics — Service technique pour chauffage, climatisation, plomberie et plus',
                'meta_description' => 'Mastechnics propose un service technique pour chauffage, climatisation, plomberie, ventilation, adoucisseurs et réfrigération. Demandez un devis en ligne.',
            ],
            [
                'locale' => 'en',
                'slug' => 'home',
                'title' => 'Technical service for heating, air conditioning, plumbing and refrigeration',
                'intro' => 'Start a smart request for repair, maintenance or installation. Add the right information from the start and receive a faster estimate or clear next step.',
                'content' => null,
                'meta_title' => 'Mastechnics — Technical service for heating, air conditioning, plumbing and more',
                'meta_description' => 'Mastechnics provides technical services for heating, air conditioning, plumbing, ventilation, water softeners and refrigeration. Request a quote online.',
            ],
        ]);
    }

    private function createRequestPage(): void
    {
        $request = Page::create([
            'code' => 'request',
            'type' => 'request',
            'is_active' => true,
        ]);

        $request->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'aanvraag',
                'title' => 'Start je technische aanvraag',
                'intro' => 'Vul stap voor stap de juiste informatie in over je installatie, probleem of project. Zo kan mastechnics sneller inschatten wat nodig is en indien mogelijk een richtprijs of duidelijke vervolgstap voorstellen.',
                'content' => null,
                'meta_title' => 'Start aanvraag | mastechnics',
                'meta_description' => 'Start een slimme technische aanvraag voor verwarming, airco, sanitair, ventilatie, waterverzachters of koeling.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'demande',
                'title' => 'Démarrer une demande technique',
                'intro' => 'Remplissez étape par étape les bonnes informations concernant votre installation, problème ou projet. mastechnics peut ainsi estimer plus rapidement ce qui est nécessaire et proposer une estimation ou une prochaine étape claire.',
                'content' => null,
                'meta_title' => 'Démarrer une demande | mastechnics',
                'meta_description' => 'Démarrez une demande technique intelligente pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau ou réfrigération.',
            ],
            [
                'locale' => 'en',
                'slug' => 'request',
                'title' => 'Start your technical request',
                'intro' => 'Fill in the right information step by step about your installation, issue or project. This helps mastechnics estimate what is needed faster and provide an estimate or clear next step when possible.',
                'content' => null,
                'meta_title' => 'Start request | mastechnics',
                'meta_description' => 'Start a smart technical request for heating, air conditioning, plumbing, ventilation, water softeners or refrigeration.',
            ],
        ]);
    }

    private function createContactPage(): void
    {
        $contact = Page::create([
            'code' => 'contact',
            'type' => 'contact',
            'is_active' => true,
        ]);

        $contact->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'contact',
                'title' => 'Contacteer mastechnics',
                'intro' => 'Heb je een algemene vraag of wil je snel contact opnemen? Gebruik het contactformulier of bereik ons rechtstreeks via telefoon, e-mail of WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | mastechnics',
                'meta_description' => 'Contacteer mastechnics via telefoon, e-mail, WhatsApp of het contactformulier.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'contact',
                'title' => 'Contacter mastechnics',
                'intro' => 'Vous avez une question générale ou souhaitez nous contacter rapidement ? Utilisez le formulaire de contact ou contactez-nous directement par téléphone, e-mail ou WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | mastechnics',
                'meta_description' => 'Contactez mastechnics par téléphone, e-mail, WhatsApp ou via le formulaire de contact.',
            ],
            [
                'locale' => 'en',
                'slug' => 'contact',
                'title' => 'Contact mastechnics',
                'intro' => 'Do you have a general question or want to get in touch quickly? Use the contact form or contact us directly by phone, email or WhatsApp.',
                'content' => null,
                'meta_title' => 'Contact | mastechnics',
                'meta_description' => 'Contact mastechnics by phone, email, WhatsApp or through the contact form.',
            ],
        ]);
    }

    private function createServicePages(): void
    {
        $services = config('services');

        foreach ($services as $serviceCode => $service) {
            if (!($service['is_active'] ?? false)) {
                continue;
            }

            $page = Page::create([
                'code' => $serviceCode,
                'type' => 'service',
                'is_active' => true,
            ]);

            $translations = [];

            foreach ($service['translations'] as $locale => $translation) {
                $translations[] = [
                    'locale' => $locale,
                    'slug' => $translation['slug'],
                    'title' => $translation['title'],
                    'intro' => $translation['description'],
                    'content' => $this->getServiceContent($locale, $translation['title']),
                    'meta_title' => $translation['title'] . ' | mastechnics',
                    'meta_description' => $translation['description'],
                ];
            }

            $page->translations()->createMany($translations);
        }
    }

    private function getServiceContent(string $locale, string $serviceTitle): string
    {
        return match ($locale) {
            'fr' => "Nous vous aidons avec les demandes liées à {$serviceTitle}. Grâce à une prise d’informations structurée, nous pouvons mieux comprendre votre situation et proposer une prochaine étape claire.",
            'en' => "We help with requests related to {$serviceTitle}. With a structured intake, we can better understand your situation and suggest a clear next step.",
            default => "Wij helpen met aanvragen rond {$serviceTitle}. Door de informatie gestructureerd te verzamelen, kunnen we de situatie sneller begrijpen en een duidelijke volgende stap voorstellen.",
        };
    }
}
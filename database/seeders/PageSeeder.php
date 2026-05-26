<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
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
                'meta_title' => 'Mastechnics | Technische service en interventies',
                'meta_description' => 'Professionele technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koelcellen.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'accueil',
                'title' => 'Service technique pour chauffage, climatisation, plomberie et réfrigération',
                'intro' => 'Démarrez une demande intelligente pour une réparation, un entretien ou une installation. Ajoutez directement les bonnes informations et recevez plus rapidement une estimation ou une prochaine étape claire.',
                'content' => null,
                'meta_title' => 'Mastechnics | Service technique et interventions',
                'meta_description' => 'Service technique professionnel pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau et chambres froides.',
            ],
            [
                'locale' => 'en',
                'slug' => 'home',
                'title' => 'Technical service for heating, air conditioning, plumbing and refrigeration',
                'intro' => 'Start a smart request for repair, maintenance or installation. Add the right information from the start and receive a faster estimate or clear next step.',
                'content' => null,
                'meta_title' => 'Mastechnics | Technical service and interventions',
                'meta_description' => 'Professional technical service for heating, air conditioning, plumbing, ventilation, water softeners and cold rooms.',
            ],
        ]);

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

        $heating = Page::create([
            'code' => 'heating',
            'type' => 'service',
            'is_active' => true,
        ]);

        $heating->translations()->createMany([
            [
                'locale' => 'nl',
                'slug' => 'verwarming',
                'title' => 'Verwarming',
                'intro' => 'Herstelling, onderhoud en installatie van verwarmingssystemen voor particulieren en bedrijven.',
                'content' => 'Wij helpen met verwarmingsketels, storingen, onderhoud en professionele opvolging van uw verwarmingsinstallatie.',
                'meta_title' => 'Verwarming herstellen en onderhouden',
                'meta_description' => 'Professionele service voor verwarming, onderhoud en herstellingen.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'chauffage',
                'title' => 'Chauffage',
                'intro' => 'Réparation, entretien et installation de systèmes de chauffage pour particuliers et entreprises.',
                'content' => 'Nous intervenons pour les chaudières, les pannes, l’entretien et le suivi professionnel de votre installation de chauffage.',
                'meta_title' => 'Réparation et entretien de chauffage',
                'meta_description' => 'Service professionnel pour le chauffage, l’entretien et les réparations.',
            ],
            [
                'locale' => 'en',
                'slug' => 'heating',
                'title' => 'Heating',
                'intro' => 'Repair, maintenance and installation of heating systems for residential and commercial clients.',
                'content' => 'We help with boilers, breakdowns, maintenance and professional follow-up of your heating installation.',
                'meta_title' => 'Heating repair and maintenance',
                'meta_description' => 'Professional service for heating, maintenance and repairs.',
            ],
        ]);
    }
}

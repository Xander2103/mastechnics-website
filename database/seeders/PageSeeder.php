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
                'intro' => 'Professionele ondersteuning voor particulieren en bedrijven, met een snelle intake en duidelijke opvolging van elke aanvraag.',
                'content' => null,
                'meta_title' => 'Website Martin | Technische service en interventies',
                'meta_description' => 'Professionele technische service voor verwarming, airco, sanitair, ventilatie, waterverzachters en koelcellen.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'accueil',
                'title' => 'Service technique pour chauffage, climatisation, plomberie et réfrigération',
                'intro' => 'Un accompagnement professionnel pour particuliers et entreprises, avec une prise de contact rapide et un suivi structuré.',
                'content' => null,
                'meta_title' => 'Website Martin | Service technique et interventions',
                'meta_description' => 'Service technique professionnel pour chauffage, climatisation, plomberie, ventilation, adoucisseurs d’eau et chambres froides.',
            ],
            [
                'locale' => 'en',
                'slug' => 'home',
                'title' => 'Technical service for heating, air conditioning, plumbing and refrigeration',
                'intro' => 'Professional support for residential and commercial clients, with fast intake and structured follow-up for every request.',
                'content' => null,
                'meta_title' => 'Website Martin | Technical service and interventions',
                'meta_description' => 'Professional technical service for heating, air conditioning, plumbing, ventilation, water softeners and cold rooms.',
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
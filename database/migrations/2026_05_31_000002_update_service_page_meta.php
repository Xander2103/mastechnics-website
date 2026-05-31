<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $updates = [
            // Heating
            ['slug' => 'verwarming', 'locale' => 'nl',
                'meta_title'       => 'Verwarmingsservice in Belgie | mastechnics',
                'meta_description' => 'Onderhoud, herstelling en installatie van verwarmingssystemen. Snel, vakkundig en op maat voor particulieren en bedrijven in Belgie.'],
            ['slug' => 'chauffage', 'locale' => 'fr',
                'meta_title'       => 'Service chauffage en Belgique | mastechnics',
                'meta_description' => 'Entretien, reparation et installation de systemes de chauffage. Rapide, professionnel et sur mesure pour particuliers et entreprises en Belgique.'],
            ['slug' => 'heating', 'locale' => 'en',
                'meta_title'       => 'Heating service in Belgium | mastechnics',
                'meta_description' => 'Maintenance, repair and installation of heating systems. Fast, professional and tailored for homes and businesses in Belgium.'],

            // Airco
            ['slug' => 'airco', 'locale' => 'nl',
                'meta_title'       => 'Airconditioning service Belgie | mastechnics',
                'meta_description' => 'Installatie, onderhoud en herstelling van airco-systemen. Erkend vakman voor particulieren en bedrijven.'],
            ['slug' => 'climatisation', 'locale' => 'fr',
                'meta_title'       => 'Service climatisation Belgique | mastechnics',
                'meta_description' => 'Installation, entretien et reparation de systemes de climatisation. Technicien agree pour particuliers et entreprises.'],
            ['slug' => 'air-conditioning', 'locale' => 'en',
                'meta_title'       => 'Air conditioning service Belgium | mastechnics',
                'meta_description' => 'Installation, maintenance and repair of air conditioning systems. Certified technician for homes and businesses in Belgium.'],

            // Plumbing
            ['slug' => 'sanitair', 'locale' => 'nl',
                'meta_title'       => 'Sanitair installateur Belgie | mastechnics',
                'meta_description' => 'Professionele hulp bij sanitaire installaties en herstellingen. Van eenvoudige herstelling tot volledige badkamerrenovatie.'],
            ['slug' => 'plomberie', 'locale' => 'fr',
                'meta_title'       => 'Plombier professionnel Belgique | mastechnics',
                'meta_description' => 'Aide professionnelle pour les installations et reparations sanitaires. De la simple reparation a la renovation complete.'],
            ['slug' => 'plumbing', 'locale' => 'en',
                'meta_title'       => 'Plumbing service Belgium | mastechnics',
                'meta_description' => 'Professional help with plumbing installations and repairs. From simple fixes to complete bathroom renovations in Belgium.'],

            // Ventilation
            ['slug' => 'ventilatie', 'locale' => 'nl',
                'meta_title'       => 'Ventilatiesystemen Belgie | mastechnics',
                'meta_description' => 'Ventilatie-oplossingen voor woningen, appartementen en bedrijven. Installatie en onderhoud van WTW en mechanische ventilatie.'],
            ['slug' => 'ventilation', 'locale' => 'fr',
                'meta_title'       => 'Systemes de ventilation Belgique | mastechnics',
                'meta_description' => 'Solutions de ventilation pour habitations, appartements et entreprises. Installation et entretien VMC et ventilation mecanique.'],
            ['slug' => 'ventilation', 'locale' => 'en',
                'meta_title'       => 'Ventilation systems Belgium | mastechnics',
                'meta_description' => 'Ventilation solutions for homes, apartments and businesses in Belgium. Installation and maintenance of HRV and mechanical ventilation.'],

            // Water softeners
            ['slug' => 'waterverzachters', 'locale' => 'nl',
                'meta_title'       => 'Waterverzachter installatie Belgie | mastechnics',
                'meta_description' => 'Advies, installatie en onderhoud van waterverzachters. Bescherm je leidingen en huishoudtoestellen tegen kalkaanslag.'],
            ['slug' => 'adoucisseurs-eau', 'locale' => 'fr',
                'meta_title'       => 'Adoucisseur eau installation Belgique | mastechnics',
                'meta_description' => 'Conseil, installation et entretien d\'adoucisseurs d\'eau. Protegez vos canalisations et appareils menagers contre le calcaire.'],
            ['slug' => 'water-softeners', 'locale' => 'en',
                'meta_title'       => 'Water softener installation Belgium | mastechnics',
                'meta_description' => 'Advice, installation and maintenance of water softeners. Protect your pipes and appliances from limescale build-up.'],

            // Cold rooms
            ['slug' => 'koelcellen', 'locale' => 'nl',
                'meta_title'       => 'Koelcellen installatie en onderhoud | mastechnics',
                'meta_description' => 'Koeling en koelcellen voor commerciele en industriele toepassingen. Installatie, onderhoud en dringende herstellingen in Belgie.'],
            ['slug' => 'chambres-froides', 'locale' => 'fr',
                'meta_title'       => 'Chambres froides installation et entretien | mastechnics',
                'meta_description' => 'Refrigeration et chambres froides pour applications commerciales et industrielles. Installation, entretien et interventions urgentes en Belgique.'],
            ['slug' => 'cold-rooms', 'locale' => 'en',
                'meta_title'       => 'Cold room installation and maintenance | mastechnics',
                'meta_description' => 'Refrigeration and cold rooms for commercial and industrial applications. Installation, maintenance and emergency repairs in Belgium.'],
        ];

        foreach ($updates as $row) {
            DB::table('page_translations')
                ->where('locale', $row['locale'])
                ->where('slug', $row['slug'])
                ->update([
                    'meta_title'       => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                    'updated_at'       => now(),
                ]);
        }
    }

    public function down(): void
    {
        $originals = [
            ['slug' => 'verwarming',      'locale' => 'nl', 'meta_title' => 'Verwarming | mastechnics',      'meta_description' => 'Onderhoud, herstelling en installatie van verwarmingssystemen.'],
            ['slug' => 'chauffage',        'locale' => 'fr', 'meta_title' => 'Chauffage | mastechnics',        'meta_description' => 'Entretien, reparation et installation de systemes de chauffage.'],
            ['slug' => 'heating',          'locale' => 'en', 'meta_title' => 'Heating | mastechnics',          'meta_description' => 'Maintenance, repair and installation of heating systems.'],
            ['slug' => 'airco',            'locale' => 'nl', 'meta_title' => 'Airco | mastechnics',            'meta_description' => 'Installatie, onderhoud en herstelling van airconditioningsystemen.'],
            ['slug' => 'climatisation',    'locale' => 'fr', 'meta_title' => 'Climatisation | mastechnics',    'meta_description' => 'Installation, entretien et reparation de systemes de climatisation.'],
            ['slug' => 'air-conditioning', 'locale' => 'en', 'meta_title' => 'Air conditioning | mastechnics', 'meta_description' => 'Installation, maintenance and repair of air conditioning systems.'],
            ['slug' => 'sanitair',         'locale' => 'nl', 'meta_title' => 'Sanitair | mastechnics',         'meta_description' => 'Professionele hulp bij sanitaire installaties en herstellingen.'],
            ['slug' => 'plomberie',        'locale' => 'fr', 'meta_title' => 'Plomberie | mastechnics',        'meta_description' => 'Aide professionnelle pour les installations et reparations sanitaires.'],
            ['slug' => 'plumbing',         'locale' => 'en', 'meta_title' => 'Plumbing | mastechnics',         'meta_description' => 'Professional help with plumbing installations and repairs.'],
            ['slug' => 'ventilatie',       'locale' => 'nl', 'meta_title' => 'Ventilatie | mastechnics',       'meta_description' => 'Ventilatie-oplossingen voor woningen, appartementen en bedrijven.'],
            ['slug' => 'ventilation',      'locale' => 'fr', 'meta_title' => 'Ventilation | mastechnics',      'meta_description' => 'Solutions de ventilation pour habitations, appartements et entreprises.'],
            ['slug' => 'ventilation',      'locale' => 'en', 'meta_title' => 'Ventilation | mastechnics',      'meta_description' => 'Ventilation solutions for homes, apartments and businesses.'],
            ['slug' => 'waterverzachters', 'locale' => 'nl', 'meta_title' => 'Waterverzachters | mastechnics', 'meta_description' => 'Advies, installatie en onderhoud van waterverzachters.'],
            ['slug' => 'adoucisseurs-eau', 'locale' => 'fr', 'meta_title' => 'Adoucisseurs d\'eau | mastechnics', 'meta_description' => 'Conseil, installation et entretien d\'adoucisseurs d\'eau.'],
            ['slug' => 'water-softeners',  'locale' => 'en', 'meta_title' => 'Water softeners | mastechnics',  'meta_description' => 'Advice, installation and maintenance of water softeners.'],
            ['slug' => 'koelcellen',       'locale' => 'nl', 'meta_title' => 'Koelcellen | mastechnics',       'meta_description' => 'Koeling en koelcellen voor commerciele en industriele toepassingen.'],
            ['slug' => 'chambres-froides', 'locale' => 'fr', 'meta_title' => 'Chambres froides | mastechnics', 'meta_description' => 'Refrigeration et chambres froides pour applications commerciales et industrielles.'],
            ['slug' => 'cold-rooms',       'locale' => 'en', 'meta_title' => 'Cold rooms | mastechnics',       'meta_description' => 'Refrigeration and cold rooms for commercial and industrial applications.'],
        ];

        foreach ($originals as $row) {
            DB::table('page_translations')
                ->where('locale', $row['locale'])
                ->where('slug', $row['slug'])
                ->update([
                    'meta_title'       => $row['meta_title'],
                    'meta_description' => $row['meta_description'],
                    'updated_at'       => now(),
                ]);
        }
    }
};

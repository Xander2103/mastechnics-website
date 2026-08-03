<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds the service-area hub plus one landing page per municipality where
 * Mastechnics actually works.
 *
 * The site previously carried no geographic signal at all: not a single
 * municipality name appeared in any title, heading or body text, while the
 * business is a service-area business in the Druivenstreek. These pages give
 * "verwarming Tervuren" style queries something to match, and give the
 * LocalBusiness areaServed markup a set of real landing pages to point at.
 *
 * Body copy lives in config/service-areas.php — each entry describes something
 * genuinely different about that municipality (housing stock, sub-villages, gas
 * grid coverage, language). Only slugs and meta live in the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createHub();
        $this->createLocationPages();
    }

    public function down(): void
    {
        $codes = ['service-area'];

        foreach (array_keys(config('service-areas', [])) as $slug) {
            $codes[] = 'location-' . $slug;
        }

        $pageIds = DB::table('pages')->whereIn('code', $codes)->pluck('id');

        DB::table('page_translations')->whereIn('page_id', $pageIds)->delete();
        DB::table('pages')->whereIn('id', $pageIds)->delete();
    }

    private function createHub(): void
    {
        if (DB::table('pages')->where('code', 'service-area')->exists()) {
            return;
        }

        $pageId = DB::table('pages')->insertGetId([
            'code' => 'service-area',
            'type' => 'service_area',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [
            [
                'locale' => 'nl',
                'slug' => 'werkgebied',
                'title' => 'Werkgebied: de Druivenstreek en oostelijk Vlaams-Brabant',
                'intro' => 'Mastechnics werkt vanuit de Druivenstreek. Dat is een bewuste keuze: door het werkgebied compact te houden, blijven verplaatsingen kort, kunnen onderhoudsbeurten per streek geclusterd worden en is een dringende interventie realistisch in te plannen.',
                'content' => 'Voor gemeenten met een eigen pagina staat hieronder wat er praktisch anders is aan het werken op die plek — van woningen zonder aardgasaansluiting in de Dijlevallei tot appartementen met een syndicus aan de Brusselse rand. Ligt uw adres net buiten deze lijst? Vraag het gerust: aansluitende gemeenten worden geval per geval bekeken.',
                'meta_title' => 'Werkgebied — Druivenstreek & Vlaams-Brabant | Mastechnics',
                'meta_description' => 'Mastechnics werkt in Tervuren, Overijse, Hoeilaart, Huldenberg, Bertem, Wezembeek-Oppem en omgeving voor verwarming, airco, sanitair, ventilatie en koeling.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'zone-intervention',
                'title' => 'Zone d\'intervention : le Druivenstreek et l\'est du Brabant flamand',
                'intro' => 'Mastechnics travaille depuis le Druivenstreek. C\'est un choix délibéré : en gardant une zone d\'intervention compacte, les déplacements restent courts, les entretiens peuvent être regroupés par secteur et une intervention urgente reste réellement planifiable.',
                'content' => 'Pour les communes disposant de leur propre page, vous trouverez ci-dessous ce qui change concrètement sur place — des habitations sans raccordement au gaz dans la vallée de la Dyle aux appartements avec syndic en périphérie bruxelloise. Votre adresse se situe juste en dehors de cette liste ? Posez la question : les communes limitrophes sont examinées au cas par cas.',
                'meta_title' => 'Zone d\'intervention — Druivenstreek & Brabant | Mastechnics',
                'meta_description' => 'Mastechnics intervient à Tervuren, Overijse, Hoeilaart, Huldenberg, Bertem, Wezembeek-Oppem et environs : chauffage, climatisation, sanitaire, ventilation.',
            ],
            [
                'locale' => 'en',
                'slug' => 'service-area',
                'title' => 'Service area: the Druivenstreek and eastern Flemish Brabant',
                'intro' => 'Mastechnics works out of the Druivenstreek region. That is a deliberate choice: keeping the service area compact keeps travel short, lets maintenance visits be clustered per area, and makes an urgent call-out realistic to schedule.',
                'content' => 'For municipalities with their own page, you will find below what is practically different about working there — from homes with no gas connection in the Dijle valley to apartments with a building manager on the Brussels periphery. Is your address just outside this list? Do ask: neighbouring municipalities are considered case by case.',
                'meta_title' => 'Service area — Druivenstreek & Flemish Brabant | Mastechnics',
                'meta_description' => 'Mastechnics covers Tervuren, Overijse, Hoeilaart, Huldenberg, Bertem, Wezembeek-Oppem and nearby for heating, air conditioning, plumbing and ventilation.',
            ],
        ];

        DB::table('page_translations')->insert(array_map(fn (array $row): array => $row + [
            'page_id' => $pageId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows));
    }

    private function createLocationPages(): void
    {
        $titles = [
            'nl' => 'Verwarming, sanitair en airco in :name',
            'fr' => 'Chauffage, sanitaire et climatisation à :name',
            'en' => 'Heating, plumbing and air conditioning in :name',
        ];

        $metaTitles = [
            'nl' => 'Verwarming, airco & sanitair in :name | Mastechnics',
            'fr' => 'Chauffage, climatisation & sanitaire à :name | Mastechnics',
            'en' => 'Heating, air conditioning & plumbing in :name | Mastechnics',
        ];

        $metaDescriptions = [
            'nl' => 'Mastechnics is uw installateur voor verwarming, airco, sanitair, ventilatie en koeling in :name (:postal). Vraag online een offerte of interventie aan.',
            'fr' => 'Mastechnics, votre installateur chauffage, climatisation, sanitaire, ventilation et froid à :name (:postal). Demandez un devis ou une intervention en ligne.',
            'en' => 'Mastechnics is your installer for heating, air conditioning, plumbing, ventilation and refrigeration in :name (:postal). Request a quote or call-out online.',
        ];

        foreach (config('site.service_areas', []) as $area) {
            if (!($area['page'] ?? false)) {
                continue;
            }

            $slug = Str::slug($area['name']);
            $code = 'location-' . $slug;

            if (DB::table('pages')->where('code', $code)->exists()) {
                continue;
            }

            $pageId = DB::table('pages')->insertGetId([
                'code' => $code,
                'type' => 'location',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = [];

            foreach (['nl', 'fr', 'en'] as $locale) {
                $replacements = [
                    ':name' => $area['name'],
                    ':postal' => $area['postal_code'],
                ];

                $rows[] = [
                    'page_id' => $pageId,
                    'locale' => $locale,
                    // Place names are not translated, so the slug is the same
                    // in every locale; the locale prefix keeps the URLs unique.
                    'slug' => $slug,
                    'title' => strtr($titles[$locale], $replacements),
                    // The lead paragraph is the municipality-specific text from
                    // config/service-areas.php — kept out of the database so it
                    // stays reviewable next to the rest of the local copy.
                    'intro' => null,
                    'content' => null,
                    'meta_title' => strtr($metaTitles[$locale], $replacements),
                    'meta_description' => strtr($metaDescriptions[$locale], $replacements),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('page_translations')->insert($rows);
        }
    }
};

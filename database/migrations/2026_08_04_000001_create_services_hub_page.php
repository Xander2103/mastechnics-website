<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the "Diensten / Services" hub page.
 *
 * Before this, the six service pages were only reachable from the homepage
 * anchor and the header dropdown — no crawlable category level existed between
 * the homepage and a service. The hub gives the site a real two-level
 * hierarchy, a breadcrumb parent, and one page that can rank for the broad
 * "technische diensten" style queries the individual pages cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('pages')->where('code', 'services')->exists()) {
            return;
        }

        $pageId = DB::table('pages')->insertGetId([
            'code' => 'services',
            'type' => 'services',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows = [
            [
                'locale' => 'nl',
                'slug' => 'diensten',
                'title' => 'Onze technische diensten',
                'intro' => 'Mastechnics verzorgt installatie, onderhoud en herstelling voor zes technische disciplines onder één dak: verwarming, airco, sanitair, ventilatie, waterverzachters en koelcellen. Eén aanspreekpunt voor particulieren en bedrijven in Tervuren, Overijse, Hoeilaart, Huldenberg en de rest van de Druivenstreek.',
                'content' => 'Omdat verwarming, ventilatie, sanitair en koeling in een gebouw op elkaar inwerken, loont het om ze door dezelfde partij te laten opvolgen. Een ketel die te vaak herstart, een badkamer die niet droog raakt of een airco die water lekt hebben vaak een oorzaak in een aanpalende installatie. Wij kijken daarom altijd naar het geheel voordat we een oplossing voorstellen.',
                'meta_title' => 'Technische diensten in de Druivenstreek | Mastechnics',
                'meta_description' => 'Verwarming, airco, sanitair, ventilatie, waterverzachters en koelcellen. Installatie, onderhoud en herstelling in Tervuren, Overijse en de Druivenstreek.',
            ],
            [
                'locale' => 'fr',
                'slug' => 'services',
                'title' => 'Nos services techniques',
                'intro' => 'Mastechnics assure l\'installation, l\'entretien et la réparation dans six disciplines techniques sous un même toit : chauffage, climatisation, plomberie, ventilation, adoucisseurs d\'eau et chambres froides. Un seul interlocuteur pour les particuliers et les entreprises à Tervuren, Overijse, Hoeilaart, Huldenberg et dans tout le Druivenstreek.',
                'content' => 'Le chauffage, la ventilation, le sanitaire et le froid interagissent dans un bâtiment : il est donc utile de les faire suivre par le même intervenant. Une chaudière qui redémarre trop souvent, une salle de bain qui ne sèche pas ou une climatisation qui fuit ont souvent leur cause dans une installation voisine. Nous examinons donc toujours l\'ensemble avant de proposer une solution.',
                'meta_title' => 'Services techniques dans le Druivenstreek | Mastechnics',
                'meta_description' => 'Chauffage, climatisation, plomberie, ventilation, adoucisseurs et chambres froides. Installation, entretien et réparation à Tervuren, Overijse et environs.',
            ],
            [
                'locale' => 'en',
                'slug' => 'services',
                'title' => 'Our technical services',
                'intro' => 'Mastechnics handles installation, maintenance and repair across six technical disciplines under one roof: heating, air conditioning, plumbing, ventilation, water softeners and cold rooms. A single point of contact for homes and businesses in Tervuren, Overijse, Hoeilaart, Huldenberg and the wider Druivenstreek region.',
                'content' => 'Heating, ventilation, plumbing and refrigeration all interact inside a building, so there is real value in having one party look after them. A boiler that restarts too often, a bathroom that never dries out or an air conditioner that leaks water frequently has its root cause in an adjacent installation. That is why we always look at the whole picture before proposing a solution.',
                'meta_title' => 'Technical services in the Druivenstreek | Mastechnics',
                'meta_description' => 'Heating, air conditioning, plumbing, ventilation, water softeners and cold rooms. Installation, maintenance and repair in Tervuren, Overijse and nearby.',
            ],
        ];

        DB::table('page_translations')->insert(array_map(fn (array $row): array => $row + [
            'page_id' => $pageId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $rows));
    }

    public function down(): void
    {
        $pageId = DB::table('pages')->where('code', 'services')->value('id');

        if ($pageId === null) {
            return;
        }

        DB::table('page_translations')->where('page_id', $pageId)->delete();
        DB::table('pages')->where('id', $pageId)->delete();
    }
};

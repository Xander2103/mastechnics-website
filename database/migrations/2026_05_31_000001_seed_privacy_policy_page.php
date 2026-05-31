<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('pages')->where('code', 'privacy')->exists()) {
            return;
        }

        $pageId = DB::table('pages')->insertGetId([
            'code'       => 'privacy',
            'type'       => 'privacy',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('page_translations')->insert([
            [
                'page_id'          => $pageId,
                'locale'           => 'nl',
                'slug'             => 'privacybeleid',
                'title'            => 'Privacybeleid',
                'intro'            => 'mastechnics respecteert je privacy. Op deze pagina lees je welke gegevens wij verzamelen, waarom, en welke rechten je hebt.',
                'content'          => null,
                'meta_title'       => 'Privacybeleid | mastechnics',
                'meta_description' => 'Lees hoe mastechnics omgaat met je persoonsgegevens: wat wij verzamelen, waarom, en jouw rechten conform de AVG/GDPR.',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'page_id'          => $pageId,
                'locale'           => 'fr',
                'slug'             => 'politique-confidentialite',
                'title'            => 'Politique de confidentialite',
                'intro'            => 'mastechnics respecte votre vie privee. Sur cette page, vous trouverez quelles donnees nous collectons, pourquoi, et quels sont vos droits.',
                'content'          => null,
                'meta_title'       => 'Politique de confidentialite | mastechnics',
                'meta_description' => 'Lisez comment mastechnics traite vos donnees personnelles : ce que nous collectons, pourquoi, et vos droits conformement au RGPD.',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
            [
                'page_id'          => $pageId,
                'locale'           => 'en',
                'slug'             => 'privacy-policy',
                'title'            => 'Privacy Policy',
                'intro'            => 'mastechnics respects your privacy. On this page you can read what data we collect, why, and what rights you have.',
                'content'          => null,
                'meta_title'       => 'Privacy Policy | mastechnics',
                'meta_description' => 'Read how mastechnics handles your personal data: what we collect, why, and your rights under GDPR.',
                'created_at'       => now(),
                'updated_at'       => now(),
            ],
        ]);
    }

    public function down(): void
    {
        $page = DB::table('pages')->where('code', 'privacy')->first();
        if ($page) {
            DB::table('page_translations')->where('page_id', $page->id)->delete();
            DB::table('pages')->where('id', $page->id)->delete();
        }
    }
};

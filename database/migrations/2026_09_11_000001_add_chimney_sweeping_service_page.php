<?php

use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the seventh public service, "Schoorsteenvegen / Ramonage / Chimney
 * sweeping", to existing installs.
 *
 * Service pages are DB rows (pages + page_translations) whose `code` equals
 * the key in config/services.php; PageSeeder only creates missing pages on a
 * fresh install, so a live database needs this migration. Slugs, titles and
 * intro come from config so the sitemap, navigation and this page can never
 * disagree; meta and body copy are shared with PageSeeder.
 *
 * The services hub intro/meta enumerated "six disciplines"; it is rewritten
 * in place (string replacement, so later manual edits survive) to name the
 * seventh.
 */
return new class extends Migration
{
    private const CODE = 'chimney-sweeping';

    public function up(): void
    {
        $this->createServicePage();
        $this->updateServicesHubCopy();
    }

    public function down(): void
    {
        $pageId = DB::table('pages')->where('code', self::CODE)->value('id');

        if ($pageId !== null) {
            DB::table('page_translations')->where('page_id', $pageId)->delete();
            DB::table('pages')->where('id', $pageId)->delete();
        }

        foreach ($this->hubReplacements() as $locale => $pairs) {
            $this->replaceInHubTranslation($locale, array_flip($pairs));
        }
    }

    private function createServicePage(): void
    {
        if (DB::table('pages')->where('code', self::CODE)->exists()) {
            return;
        }

        $service = config('services.' . self::CODE);

        if ($service === null) {
            return;
        }

        $pageId = DB::table('pages')->insertGetId([
            'code' => self::CODE,
            'type' => 'service',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $meta = PageSeeder::chimneySweepingMeta();
        $content = PageSeeder::chimneySweepingContent();
        $rows = [];

        foreach ($service['translations'] as $locale => $translation) {
            $rows[] = [
                'page_id' => $pageId,
                'locale' => $locale,
                'slug' => $translation['slug'],
                'title' => $translation['title'],
                'intro' => $translation['description'],
                'content' => $content[$locale] ?? $content['nl'],
                'meta_title' => $meta[$locale]['meta_title'] ?? $meta['nl']['meta_title'],
                'meta_description' => $meta[$locale]['meta_description'] ?? $meta['nl']['meta_description'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('page_translations')->insert($rows);
    }

    private function updateServicesHubCopy(): void
    {
        foreach ($this->hubReplacements() as $locale => $pairs) {
            $this->replaceInHubTranslation($locale, $pairs);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function hubReplacements(): array
    {
        return [
            'nl' => [
                'zes technische disciplines' => 'zeven technische disciplines',
                'waterverzachters en koelcellen' => 'waterverzachters, koelcellen en schoorsteenvegen',
            ],
            'fr' => [
                'six disciplines techniques' => 'sept disciplines techniques',
                'adoucisseurs d\'eau et chambres froides' => 'adoucisseurs d\'eau, chambres froides et ramonage',
                'adoucisseurs et chambres froides' => 'adoucisseurs, chambres froides et ramonage',
            ],
            'en' => [
                'six technical disciplines' => 'seven technical disciplines',
                'water softeners and cold rooms' => 'water softeners, cold rooms and chimney sweeping',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $pairs
     */
    private function replaceInHubTranslation(string $locale, array $pairs): void
    {
        $pageId = DB::table('pages')->where('code', 'services')->value('id');

        if ($pageId === null) {
            return;
        }

        $row = DB::table('page_translations')
            ->where('page_id', $pageId)
            ->where('locale', $locale)
            ->first(['id', 'intro', 'meta_description']);

        if ($row === null) {
            return;
        }

        $updates = [];

        foreach (['intro', 'meta_description'] as $column) {
            $updated = str_replace(array_keys($pairs), array_values($pairs), (string) $row->{$column});

            if ($updated !== (string) $row->{$column}) {
                $updates[$column] = $updated;
            }
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            DB::table('page_translations')->where('id', $row->id)->update($updates);
        }
    }
};

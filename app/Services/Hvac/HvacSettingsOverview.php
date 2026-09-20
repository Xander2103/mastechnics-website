<?php

namespace App\Services\Hvac;

use App\Models\HvacProduct;
use App\Models\HvacRuleSet;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Read model behind the admin screen "Offerte-instellingen": rules of a rule
 * set decorated with their plain-language guide, progress of the important
 * confirmations, what the catalog can currently offer per capacity class,
 * the honest status of automatic recommendations and the next sensible step.
 *
 * Read-only: nothing here changes a rule, a validation or a product.
 */
class HvacSettingsOverview
{
    public function __construct(private readonly HvacCatalogQuality $catalogQuality)
    {
    }

    /**
     * Every catalog rule that exists in this rule set, with value, validation
     * state and guide. A v1 set hides v2-only rules and vice versa.
     */
    public function entries(HvacRuleSet $ruleSet): Collection
    {
        $configuration = $ruleSet->configuration ?? [];
        $validations = $ruleSet->validations()->get()->keyBy('rule_key');

        return collect(HvacRuleCatalog::entries())
            ->filter(fn (array $entry) => HvacRuleCatalog::value($configuration, $entry['key']) !== null)
            ->values()
            ->map(function (array $entry) use ($configuration, $validations) {
                $validation = $validations->get($entry['key']);
                $currentValue = HvacRuleCatalog::value($configuration, $entry['key']);
                // A validation whose stored value differs from the current
                // value no longer counts (the approval gate applies the same
                // test), so the rule shows as not confirmed.
                $stillValid = $validation !== null && $validation->appliesTo($currentValue);
                $valueChanged = $validation !== null && ! $stillValid;
                $status = $stillValid ? 'validated' : $entry['default_status'];

                return $entry + [
                    'raw_value'     => $currentValue,
                    'value'         => HvacRuleCatalog::formatValue($currentValue),
                    'value_lines'   => HvacSettingsGuide::valueLines($entry, $currentValue),
                    'validation'    => $validation,
                    'value_changed' => $valueChanged,
                    'status'        => $status,
                    'friendly'      => HvacSettingsGuide::friendlyStatus($status, $valueChanged),
                    'status_reason' => HvacSettingsGuide::statusReason($status, $valueChanged),
                    'section'       => HvacSettingsGuide::sectionFor($entry),
                    'group'         => HvacSettingsGuide::groupFor($entry),
                    'guide'         => HvacSettingsGuide::guide($entry, $currentValue, $configuration),
                    'edit'          => HvacSettingsGuide::editSpec($entry, $currentValue),
                ];
            });
    }

    /**
     * @return array{critical_total: int, critical_done: int, critical_open: int, open: Collection}
     */
    public function progress(Collection $entries): array
    {
        $critical = $entries->filter(fn ($e) => $e['critical']);
        $open = $critical->filter(fn ($e) => $e['status'] !== 'validated')->values();

        return [
            'critical_total' => $critical->count(),
            'critical_done'  => $critical->count() - $open->count(),
            'critical_open'  => $open->count(),
            'open'           => $open,
        ];
    }

    /**
     * Status per section. Important (critical) settings decide the status;
     * a section without any decides on all of its settings.
     *
     * @return array<string, array>
     */
    public function sections(Collection $entries): array
    {
        $sections = [];
        foreach (HvacSettingsGuide::SECTIONS as $key => $definition) {
            $own = $entries->where('section', $key);
            $critical = $own->filter(fn ($e) => $e['critical']);
            $deciding = $critical->isNotEmpty() ? $critical : $own;

            $approved = $deciding->where('status', 'validated')->count();
            $status = match (true) {
                $deciding->isEmpty()            => ['key' => 'unavailable', 'label' => 'Niet beschikbaar'],
                $approved === $deciding->count() => ['key' => 'approved', 'label' => 'Goedgekeurd'],
                $approved === 0 && $deciding->contains(fn ($e) => $e['friendly']['key'] === 'todo')
                                                => ['key' => 'todo', 'label' => 'Nog instellen'],
                default                         => ['key' => 'review', 'label' => 'Controleren'],
            };

            $sections[$key] = $definition + [
                'key'            => $key,
                'status'         => $status,
                'total'          => $own->count(),
                'critical_total' => $critical->count(),
                'critical_done'  => $critical->where('status', 'validated')->count(),
                'confirmed'      => $own->where('status', 'validated')->count(),
            ];
        }

        return $sections;
    }

    /**
     * What the CURRENT catalog can offer: real selectable products per
     * capacity class (the same window the selector searches) and the data
     * gaps that keep products out of recommendations.
     */
    public function catalog(array $rules): array
    {
        $coolingTypes = ['indoor_unit', 'single_split_set'];

        $capacities = HvacProduct::selectable()
            ->whereIn('product_type', $coolingTypes)
            ->whereNotNull('cooling_capacity_kw')
            ->pluck('cooling_capacity_kw')
            ->map(fn ($kw) => (float) $kw);

        $withoutCapacity = HvacProduct::selectable()
            ->whereIn('product_type', $coolingTypes)
            ->whereNull('cooling_capacity_kw')
            ->count();

        $needsReview = HvacProduct::selectable()
            ->get(['id', 'metadata'])
            ->filter(fn (HvacProduct $product) => $product->needsImportReview())
            ->count();

        $oversize = (float) ($rules['capacity_match']['max_oversize_factor'] ?? 1.30);
        $bands = $rules['capacity_classes'] ?? [];
        $maxClass = (float) ($rules['max_class_kw'] ?? 0);
        $reviewAbove = (float) ($rules['manual_review_above_kw'] ?? $maxClass);

        $classes = [];
        $lower = 0.0;
        foreach ($bands as $band) {
            $classes[] = $this->classRow($lower, (float) $band['max_load_kw'], (float) $band['class_kw'], $oversize, $capacities);
            $lower = (float) $band['max_load_kw'];
        }
        if ($maxClass > 0 && $reviewAbove > $lower) {
            $classes[] = $this->classRow($lower, $reviewAbove, $maxClass, $oversize, $capacities);
        }

        $testCatalogActive = HvacProduct::active()
            ->where(fn ($q) => $q->where('sku', 'like', 'TEST%')->orWhere('name', 'like', 'TEST%'))
            ->exists();

        return [
            'classes'            => $classes,
            'manual_above_kw'    => $reviewAbove > 0 ? $reviewAbove : null,
            'oversize_factor'    => $oversize,
            'cooling_products'   => $capacities->count(),
            'without_capacity'   => $withoutCapacity,
            'needs_review'       => $needsReview,
            'quality'            => $this->catalogQuality->counts(),
            'test_catalog'       => $testCatalogActive,
        ];
    }

    /**
     * Honest status of automatic recommendations.
     *
     * @return array{key: string, label: string, text: string}
     */
    public function recommendationStatus(array $progress, array $catalog): array
    {
        if ($catalog['cooling_products'] === 0) {
            return [
                'key'   => 'unavailable',
                'label' => 'Niet beschikbaar',
                'text'  => 'Er staan nog geen toestellen met een koelvermogen in uw catalogus. Een voorcalculatie berekent wel het koelvermogen, maar kan geen toestel voorstellen.',
            ];
        }

        if ($progress['critical_open'] > 0) {
            return [
                'key'   => 'review',
                'label' => 'Controleren',
                'text'  => 'Aanbevelingen worden berekend en getoond, maar goedkeuren en omzetten naar een offerte is geblokkeerd tot alle belangrijke instellingen bevestigd zijn.',
            ];
        }

        return [
            'key'   => 'approved',
            'label' => 'Goedgekeurd',
            'text'  => 'Alle belangrijke instellingen zijn bevestigd. Elke aanbeveling blijft een voorstel dat u zelf controleert en goedkeurt — er wordt nooit automatisch een offerte verstuurd.',
        ];
    }

    /**
     * @return array{text: string, label: string, url: string}
     */
    public function nextAction(array $progress, array $sections, array $catalog, Collection $drafts): array
    {
        if ($progress['critical_open'] > 0) {
            $first = $progress['open']->first();
            $section = $sections[$first['section']];

            return [
                'text'  => 'Voordat automatische offertes gebruikt kunnen worden, moet u nog '
                    . $progress['critical_open'] . ' belangrijke '
                    . ($progress['critical_open'] === 1 ? 'instelling' : 'instellingen') . ' controleren.',
                'label' => 'Ga verder met ' . $section['title'],
                'url'   => route('admin.hvac.rules.section', $section['key']),
            ];
        }

        if ($catalog['cooling_products'] === 0) {
            return [
                'text'  => 'Uw instellingen zijn bevestigd. Voeg nu uw productcatalogus toe, zodat het systeem toestellen kan voorstellen.',
                'label' => 'Productbestand importeren',
                'url'   => route('admin.hvac.import.index'),
            ];
        }

        if ($catalog['test_catalog']) {
            return [
                'text'  => 'Er staan nog actieve TEST-producten in de catalogus. Zet ze inactief voordat u echte offertes maakt.',
                'label' => 'Producten bekijken',
                'url'   => route('admin.hvac.products.index', ['view' => 'all', 'q' => 'TEST']),
            ];
        }

        if ($drafts->isNotEmpty()) {
            return [
                'text'  => 'U hebt een concept met wijzigingen dat nog niet in gebruik is.',
                'label' => 'Concept bekijken',
                'url'   => route('admin.hvac.rules.index') . '#concept',
            ];
        }

        return [
            'text'  => 'Alles is ingesteld. Open een airco-aanvraag en voer de voorcalculatie uit.',
            'label' => 'Naar de aanvragen',
            'url'   => route('admin.requests.index'),
        ];
    }

    /**
     * What a draft would change compared with the active settings, and which
     * important settings are not confirmed inside the draft.
     *
     * @return array{changes: array, other_changes: int, open_confirmations: Collection, ready: bool}
     */
    public function draftSummary(HvacRuleSet $draft, HvacRuleSet $active): array
    {
        $draftFlat = Arr::dot($draft->configuration ?? []);
        $activeFlat = Arr::dot($active->configuration ?? []);

        $changedPaths = [];
        foreach (array_unique(array_merge(array_keys($draftFlat), array_keys($activeFlat))) as $path) {
            if (($draftFlat[$path] ?? null) != ($activeFlat[$path] ?? null)) {
                $changedPaths[] = (string) $path;
            }
        }

        $draftEntries = $this->entries($draft)->keyBy('key');
        $catalog = collect(HvacRuleCatalog::entries())->keyBy('key');

        $changes = [];
        $other = 0;
        foreach ($changedPaths as $path) {
            $ruleKey = $catalog->keys()->first(
                fn (string $key) => $path === $key || str_starts_with($path, $key . '.')
            );
            if ($ruleKey === null) {
                $other++;
                continue;
            }
            if (isset($changes[$ruleKey])) {
                continue;
            }

            $entry = $catalog[$ruleKey];
            $changes[$ruleKey] = [
                'key'       => $ruleKey,
                'name'      => HvacSettingsGuide::guide($entry, HvacRuleCatalog::value($draft->configuration ?? [], $ruleKey), $draft->configuration ?? [])['name'],
                'critical'  => $entry['critical'],
                'old_lines' => HvacSettingsGuide::valueLines($entry, HvacRuleCatalog::value($active->configuration ?? [], $ruleKey)),
                'new_lines' => HvacSettingsGuide::valueLines($entry, HvacRuleCatalog::value($draft->configuration ?? [], $ruleKey)),
                'confirmed' => ($draftEntries[$ruleKey]['status'] ?? null) === 'validated',
            ];
        }

        $open = $draftEntries->filter(fn ($e) => $e['critical'] && $e['status'] !== 'validated')->values();

        return [
            'changes'            => array_values($changes),
            'other_changes'      => $other,
            'open_confirmations' => $open,
            'ready'              => $open->isEmpty(),
        ];
    }

    private function classRow(float $lowerLoad, float $upperLoad, float $classKw, float $oversize, Collection $capacities): array
    {
        $maxCapacity = round($classKw * $oversize, 2);

        return [
            'class_kw'        => $classKw,
            'load_from_kw'    => $lowerLoad,
            'load_to_kw'      => $upperLoad,
            'max_capacity_kw' => $maxCapacity,
            // A product can serve some load in this band when its capacity
            // lies above the band's lower edge and within the oversize cap.
            'products'        => $capacities->filter(fn (float $kw) => $kw > $lowerLoad && $kw <= $maxCapacity)->count(),
        ];
    }
}

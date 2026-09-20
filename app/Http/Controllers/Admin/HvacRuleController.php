<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacRuleSet;
use App\Models\HvacRuleValidation;
use App\Services\Hvac\HvacRuleCatalog;
use App\Services\Hvac\HvacRuleSetResolver;
use App\Services\Hvac\HvacSettingsExample;
use App\Services\Hvac\HvacSettingsGuide;
use App\Services\Hvac\HvacSettingsOverview;
use App\Services\Hvac\QuickCoolingEstimator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin screen "Offerte-instellingen" (route names keep the historical
 * `admin.hvac.rules.*` prefix). A guided layer over the versioned rule sets:
 * the rule architecture, the approval gate and the snapshots are unchanged.
 */
class HvacRuleController extends Controller
{
    /** Dashboard: progress, five sections, concept and next action. */
    public function index(HvacRuleSetResolver $resolver, HvacSettingsOverview $overview): View
    {
        $active = $resolver->active();
        $entries = $overview->entries($active);
        $progress = $overview->progress($entries);
        $sections = $overview->sections($entries);
        $catalog = $overview->catalog($active->configuration ?? []);
        $drafts = $this->drafts();

        return view('admin.hvac.settings.index', [
            'ruleSet'              => $active,
            'progress'             => $progress,
            'sections'             => $sections,
            'catalog'              => $catalog,
            'recommendationStatus' => $overview->recommendationStatus($progress, $catalog),
            'nextAction'           => $overview->nextAction($progress, $sections, $catalog, $drafts),
            'drafts'               => $drafts->map(fn (HvacRuleSet $draft) => [
                'ruleSet' => $draft,
                'summary' => $overview->draftSummary($draft, $active),
            ]),
        ]);
    }

    /** One of the five sections, for the active settings or for a concept. */
    public function section(
        Request $request,
        string $section,
        HvacRuleSetResolver $resolver,
        HvacSettingsOverview $overview,
        QuickCoolingEstimator $quickEstimator,
    ): View {
        abort_unless(isset(HvacSettingsGuide::SECTIONS[$section]), 404);

        $active = $resolver->active();
        $concept = $this->conceptFromRequest($request);
        $ruleSet = $concept ?? $active;

        $allEntries = $overview->entries($ruleSet);
        $entries = $allEntries->where('section', $section)->values();

        return view('admin.hvac.settings.section', [
            'sectionKey'      => $section,
            'section'         => $overview->sections($allEntries)[$section],
            'sections'        => HvacSettingsGuide::SECTIONS,
            'ruleSet'         => $ruleSet,
            'active'          => $active,
            'concept'         => $concept,
            'existingDraft'   => $this->drafts()->firstWhere('name', $active->name),
            'groups'          => $entries->groupBy('group'),
            'progress'        => $overview->progress($allEntries),
            'catalog'         => $section === 'toestellen' ? $overview->catalog($ruleSet->configuration ?? []) : null,
            'loadMethod'      => ($ruleSet->configuration['load_method'] ?? 'simple_v1') === 'engineering_v2' ? 'engineering_v2' : 'simple_v1',
            'quickSituations' => $section === 'koelvermogen' ? $quickEstimator->situations($ruleSet->configuration ?? []) : [],
        ]);
    }

    /** "Geavanceerde instellingen": the complete technical rule table. */
    public function advanced(HvacRuleSetResolver $resolver, HvacSettingsOverview $overview): View
    {
        $active = $resolver->active();
        $entries = $overview->entries($active);
        $progress = $overview->progress($entries);

        return view('admin.hvac.settings.advanced', [
            'ruleSet'       => $active,
            'entriesByCat'  => $entries->groupBy('category'),
            'criticalTotal' => $progress['critical_total'],
            'criticalDone'  => $progress['critical_done'],
        ]);
    }

    /** "Bekijk voorbeeld": in-memory demonstration, writes nothing. */
    public function example(HvacRuleSetResolver $resolver, HvacSettingsExample $example): View
    {
        $active = $resolver->active();

        return view('admin.hvac.settings.example', [
            'ruleSet' => $active,
            'example' => $example->build($active->configuration ?? []),
        ]);
    }

    /**
     * Explicit business confirmation of one setting. Never automatic: a value
     * being numerically valid proves nothing — an admin must tick the
     * confirmation. Stored: who, when, which value, which rule version.
     */
    public function validateRule(Request $request, HvacRuleSetResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'rule_key' => ['required', 'string', Rule::in(HvacRuleCatalog::keys())],
            'note'     => ['nullable', 'string', 'max:1000'],
            'confirm'  => ['accepted'],
            'rule_set' => ['nullable', 'integer'],
        ], [
            'confirm.accepted' => 'Vink aan dat u deze waarde bevestigt. Zonder uw uitdrukkelijke bevestiging wordt niets goedgekeurd.',
        ]);

        $ruleSet = $this->confirmableRuleSet($data['rule_set'] ?? null, $resolver);
        $value = HvacRuleCatalog::value($ruleSet->configuration ?? [], $data['rule_key']);

        if ($value === null) {
            return back()->withErrors(['rule_key' => 'Deze instelling bestaat niet in deze versie van de instellingen.']);
        }

        $ruleSet->validations()->updateOrCreate(
            ['rule_key' => $data['rule_key']],
            [
                'status'          => 'validated',
                'note'            => $data['note'] ?? null,
                // Snapshot of the validated value: the validation stops
                // counting as soon as the rule carries a different value.
                'validated_value' => HvacRuleValidation::encodeValue($value),
                'validated_by'    => (string) session('admin_user_email'),
                'validated_at'    => now(),
            ]
        );

        return back()->with('success', 'hvac_rule_validated');
    }

    public function unvalidateRule(Request $request, HvacRuleSetResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'rule_key' => ['required', 'string', Rule::in(HvacRuleCatalog::keys())],
            'rule_set' => ['nullable', 'integer'],
        ]);

        $this->confirmableRuleSet($data['rule_set'] ?? null, $resolver)
            ->validations()->where('rule_key', $data['rule_key'])->delete();

        return back()->with('success', 'hvac_rule_unvalidated');
    }

    /**
     * Copy the active rule set into a new draft version ("concept"). Values
     * are only ever changed in a draft — never by a silent edit of the active
     * set — and historical calculations keep their snapshots either way.
     */
    public function createDraft(Request $request, HvacRuleSetResolver $resolver): RedirectResponse
    {
        $active = $resolver->active();
        $section = $request->string('section')->toString();
        $section = isset(HvacSettingsGuide::SECTIONS[$section]) ? $section : null;

        // One concept per settings family keeps the screen understandable.
        $existing = HvacRuleSet::where('status', 'draft')->where('name', $active->name)->orderByDesc('version')->first();
        if ($existing !== null) {
            return $this->toConcept($existing, $section)->with('success', 'hvac_rule_draft_exists');
        }

        $draft = DB::transaction(function () use ($active) {
            $nextVersion = (int) HvacRuleSet::where('name', $active->name)->max('version') + 1;

            $draft = HvacRuleSet::create([
                'name'          => $active->name,
                'version'       => $nextVersion,
                'status'        => 'draft',
                'configuration' => $active->configuration,
                'created_by'    => (string) session('admin_user_email'),
            ]);

            // Validations carry over: the copied values are identical at this
            // point. The validated value is stored with the copy so a value
            // changed later in the draft invalidates that rule again.
            foreach ($active->validations as $validation) {
                $draft->validations()->create($validation->only([
                    'rule_key', 'status', 'note', 'validated_by', 'validated_at',
                ]) + [
                    'validated_value' => $validation->validated_value
                        ?? HvacRuleValidation::encodeValue(
                            HvacRuleCatalog::value($active->configuration, $validation->rule_key)
                        ),
                ]);
            }

            return $draft;
        });

        $response = $section !== null ? $this->toConcept($draft, $section) : back();

        return $response->with('success', 'hvac_rule_draft_created')->with('draft_version', $draft->version);
    }

    /**
     * Change one simple numeric setting — in a DRAFT only. The active
     * settings and every existing calculation or quote stay untouched; the
     * previous confirmation of this setting stops counting automatically.
     */
    public function updateValue(Request $request, HvacRuleSet $ruleSet): RedirectResponse
    {
        if ($ruleSet->status !== 'draft') {
            return back()->withErrors(['value' => 'Waarden kunnen alleen in een concept gewijzigd worden. Maak eerst een concept aan.']);
        }

        $data = $request->validate([
            'rule_key' => ['required', 'string', Rule::in(HvacRuleCatalog::keys())],
            'value'    => ['required', 'string', 'max:20'],
        ], [
            'value.required' => 'Vul een waarde in.',
        ]);

        $entry = collect(HvacRuleCatalog::entries())->firstWhere('key', $data['rule_key']);
        $configuration = $ruleSet->configuration ?? [];
        $current = HvacRuleCatalog::value($configuration, $data['rule_key']);
        $spec = $current === null ? ['editable' => false] : HvacSettingsGuide::editSpec($entry, $current);

        if (! $spec['editable']) {
            return back()->withErrors(['value' => $spec['reason'] ?? 'Deze instelling kan hier niet gewijzigd worden.']);
        }

        $normalized = str_replace(',', '.', trim($data['value']));
        if (! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            return back()->withErrors(['value' => 'Vul een getal in, bijvoorbeeld 65 of 2,5.'])->withInput();
        }
        $new = (float) $normalized;

        if (isset($spec['choices'])) {
            if (! in_array($new, $spec['choices'], true)) {
                return back()->withErrors(['value' => 'Kies een van de toegelaten tarieven: '
                    . implode(', ', array_map(fn ($c) => HvacSettingsGuide::nl($c) . '%', $spec['choices'])) . '.'])->withInput();
            }
        } elseif ($new < $spec['min'] || $new > $spec['max']) {
            return back()->withErrors(['value' => $entry['label'] . ': de waarde moet tussen '
                . HvacSettingsGuide::nl($spec['min']) . ' en ' . HvacSettingsGuide::nl($spec['max']) . ' liggen.'])->withInput();
        } elseif (($spec['integer'] ?? false) && floor($new) !== $new) {
            return back()->withErrors(['value' => $entry['label'] . ': vul een geheel getal in.'])->withInput();
        }

        // Keep whole numbers as integers where the rule was an integer.
        $typed = is_int($current) && floor($new) === $new ? (int) $new : $new;

        if (HvacRuleValidation::encodeValue($typed) === HvacRuleValidation::encodeValue($current)) {
            return back()->with('success', 'hvac_rule_value_unchanged');
        }

        DB::transaction(function () use ($ruleSet, $configuration, $data, $current, $typed) {
            Arr::set($configuration, $data['rule_key'], $typed);
            $ruleSet->update(['configuration' => $configuration]);

            $ruleSet->changes()->create([
                'rule_key'   => $data['rule_key'],
                'old_value'  => HvacRuleValidation::encodeValue($current),
                'new_value'  => HvacRuleValidation::encodeValue($typed),
                'changed_by' => (string) session('admin_user_email'),
                'created_at' => now(),
            ]);
        });

        return back()->with('success', 'hvac_rule_value_updated');
    }

    /** Put a concept aside. Status change only — nothing is deleted. */
    public function discard(HvacRuleSet $ruleSet): RedirectResponse
    {
        if ($ruleSet->status !== 'draft') {
            return back()->withErrors(['confirm' => 'Alleen een concept kan geannuleerd worden.']);
        }

        $ruleSet->update(['status' => 'archived']);

        return redirect()->route('admin.hvac.rules.index')->with('success', 'hvac_rule_draft_discarded');
    }

    public function activate(Request $request, HvacRuleSet $ruleSet): RedirectResponse
    {
        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Bevestig expliciet dat u deze regelset wil activeren.',
        ]);

        if ($ruleSet->status !== 'draft') {
            return back()->withErrors(['confirm' => 'Alleen een conceptregelset kan geactiveerd worden.']);
        }

        DB::transaction(function () use ($ruleSet) {
            HvacRuleSet::where('status', 'active')->update(['status' => 'archived']);
            $ruleSet->update([
                'status'         => 'active',
                'effective_from' => now()->toDateString(),
                'approved_by'    => (string) session('admin_user_email'),
            ]);
        });

        return redirect()->route('admin.hvac.rules.index')->with('success', 'hvac_rule_set_activated');
    }

    private function drafts()
    {
        return HvacRuleSet::where('status', 'draft')->orderByDesc('version')->get();
    }

    private function conceptFromRequest(Request $request): ?HvacRuleSet
    {
        if (! $request->filled('concept')) {
            return null;
        }

        $concept = HvacRuleSet::where('status', 'draft')->find((int) $request->query('concept'));
        abort_if($concept === null, 404);

        return $concept;
    }

    /** Confirmations apply to the active settings or to a concept — never to history. */
    private function confirmableRuleSet(?int $ruleSetId, HvacRuleSetResolver $resolver): HvacRuleSet
    {
        if ($ruleSetId === null) {
            return $resolver->active();
        }

        $ruleSet = HvacRuleSet::whereIn('status', ['active', 'draft'])->find($ruleSetId);
        abort_if($ruleSet === null, 404);

        return $ruleSet;
    }

    private function toConcept(HvacRuleSet $draft, ?string $section): RedirectResponse
    {
        return $section !== null
            ? redirect()->route('admin.hvac.rules.section', ['section' => $section, 'concept' => $draft->id])
            : redirect()->route('admin.hvac.rules.index');
    }
}

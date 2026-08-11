<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HvacRuleSet;
use App\Services\Hvac\HvacRuleCatalog;
use App\Services\Hvac\HvacRuleSetResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HvacRuleController extends Controller
{
    public function index(HvacRuleSetResolver $resolver): View
    {
        $active = $resolver->active();
        $validations = $active->validations()->get()->keyBy('rule_key');

        // Only rules that exist in the active configuration apply: a v1 set
        // hides v2-only rules and vice versa.
        $entries = collect(HvacRuleCatalog::entries())
            ->filter(fn (array $entry) => HvacRuleCatalog::value($active->configuration, $entry['key']) !== null)
            ->values()
            ->map(function (array $entry) use ($active, $validations) {
                $validation = $validations->get($entry['key']);

                return $entry + [
                    'value'      => HvacRuleCatalog::formatValue(HvacRuleCatalog::value($active->configuration, $entry['key'])),
                    'validation' => $validation,
                    'status'     => $validation !== null ? 'validated' : $entry['default_status'],
                ];
            });

        return $this->view($active, $entries);
    }

    private function view(HvacRuleSet $active, $entries): View
    {
        return view('admin.hvac.rules.index', [
            'ruleSet'        => $active,
            'entriesByCat'   => $entries->groupBy('category'),
            'criticalTotal'  => $entries->filter(fn ($e) => $e['critical'])->count(),
            'criticalDone'   => $entries->filter(fn ($e) => $e['critical'] && $e['status'] === 'validated')->count(),
            'drafts'         => HvacRuleSet::where('status', 'draft')->orderByDesc('version')->get(),
        ]);
    }

    public function validateRule(Request $request, HvacRuleSetResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'rule_key' => ['required', 'string', Rule::in(HvacRuleCatalog::keys())],
            'note'     => ['nullable', 'string', 'max:1000'],
        ]);

        $active = $resolver->active();

        $active->validations()->updateOrCreate(
            ['rule_key' => $data['rule_key']],
            [
                'status'       => 'validated',
                'note'         => $data['note'] ?? null,
                'validated_by' => (string) session('admin_user_email'),
                'validated_at' => now(),
            ]
        );

        return back()->with('success', 'hvac_rule_validated');
    }

    public function unvalidateRule(Request $request, HvacRuleSetResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'rule_key' => ['required', 'string', Rule::in(HvacRuleCatalog::keys())],
        ]);

        $resolver->active()->validations()->where('rule_key', $data['rule_key'])->delete();

        return back()->with('success', 'hvac_rule_unvalidated');
    }

    /**
     * Copy the active rule set into a new draft version. Rule VALUES are
     * changed by the developer in that draft (config/database), never by a
     * silent edit of the active set — historical calculations keep their
     * snapshots either way.
     */
    public function createDraft(HvacRuleSetResolver $resolver): RedirectResponse
    {
        $active = $resolver->active();

        $draft = DB::transaction(function () use ($active) {
            $nextVersion = (int) HvacRuleSet::where('name', $active->name)->max('version') + 1;

            $draft = HvacRuleSet::create([
                'name'          => $active->name,
                'version'       => $nextVersion,
                'status'        => 'draft',
                'configuration' => $active->configuration,
                'created_by'    => (string) session('admin_user_email'),
            ]);

            // Validations carry over: the copied values are identical.
            foreach ($active->validations as $validation) {
                $draft->validations()->create($validation->only([
                    'rule_key', 'status', 'note', 'validated_by', 'validated_at',
                ]));
            }

            return $draft;
        });

        return back()->with('success', 'hvac_rule_draft_created')->with('draft_version', $draft->version);
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

        return back()->with('success', 'hvac_rule_set_activated');
    }
}

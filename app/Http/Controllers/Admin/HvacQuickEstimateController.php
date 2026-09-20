<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Hvac\HvacRuleSetResolver;
use App\Services\Hvac\HvacSettingsOverview;
use App\Services\Hvac\QuickCoolingEstimator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Snelle inschatting": a read-only calculator. GET only — the page never
 * stores a result, touches a request or a quote, selects a product or sends
 * anything. It shows an indication and nothing else.
 */
class HvacQuickEstimateController extends Controller
{
    public function show(
        Request $request,
        HvacRuleSetResolver $resolver,
        QuickCoolingEstimator $estimator,
        HvacSettingsOverview $overview,
    ): View {
        $active = $resolver->active();
        $rules = $active->configuration ?? [];

        $input = [
            'length_m'  => $request->query('length_m'),
            'width_m'   => $request->query('width_m'),
            'height_m'  => $request->query('height_m'),
            'situation' => $request->query('situation'),
        ];
        $submitted = collect($input)->contains(fn ($value) => $value !== null && $value !== '');

        $entries = $overview->entries($active);
        $quickEntries = $entries->where('category', 'Snelle inschatting');
        $classEntry = $entries->firstWhere('key', 'capacity_classes');

        return view('admin.hvac.settings.quick-estimate', [
            'ruleSet'         => $active,
            'available'       => $estimator->available($rules),
            'situations'      => $estimator->situations($rules),
            'input'           => array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $input),
            'submitted'       => $submitted,
            'result'          => $submitted ? $estimator->estimate($input, $rules) : null,
            'warnings'        => QuickCoolingEstimator::LIMITATION_WARNINGS,
            // Until Martin confirms his four values, every result says so.
            'valuesConfirmed' => $quickEntries->isNotEmpty() && $quickEntries->every(fn ($e) => $e['status'] === 'validated'),
            // A class is only ever an indication here; while the class table
            // itself is unconfirmed the page says that too.
            'classesConfirmed' => $classEntry !== null && $classEntry['status'] === 'validated',
        ]);
    }
}

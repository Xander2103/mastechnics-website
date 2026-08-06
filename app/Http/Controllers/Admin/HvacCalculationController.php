<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\HvacProduct;
use App\Models\HvacRecommendation;
use App\Models\HvacRecommendationItem;
use App\Services\Hvac\HvacCalculationService;
use App\Services\Hvac\HvacManualOverrideService;
use App\Services\Hvac\HvacQuoteConversionService;
use App\Services\Hvac\HvacRecommendationBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HvacCalculationController extends Controller
{
    public function calculate(
        Request $request,
        CustomerRequest $customerRequest,
        HvacCalculationService $calculationService,
        HvacRecommendationBuilder $builder
    ): RedirectResponse {
        abort_unless($customerRequest->service_category === 'airco_offerte', 404);

        // Idempotency: one calculation at a time per request.
        $lock = Cache::lock("hvac-calculate:{$customerRequest->id}", 30);
        if (! $lock->get()) {
            return back()->with('success', 'hvac_calculation_in_progress');
        }

        try {
            $calculation = $calculationService->run($customerRequest, session('admin_user_email'));

            if ($calculation->status === 'calculated') {
                $result = $builder->buildForCalculation($calculation);

                // Persist selection warnings with the calculation so the
                // panel can show them after redirect.
                $warnings = $calculation->warnings ?? [];
                $warnings['selection'] = $result['selection_warnings'];
                $warnings['invalid_candidates'] = count($result['invalid_candidates']);
                $calculation->update(['warnings' => $warnings]);
            }
        } finally {
            $lock->release();
        }

        return redirect()
            ->route('admin.requests.show', $customerRequest)
            ->with('success', $calculation->status === 'blocked' ? 'hvac_calculation_blocked' : 'hvac_calculated');
    }

    public function approve(Request $request, CustomerRequest $customerRequest, HvacRecommendation $recommendation): RedirectResponse
    {
        $this->guardScope($customerRequest, $recommendation);

        if ($recommendation->status === 'approved') {
            return back()->with('success', 'hvac_already_approved');
        }
        if ($recommendation->status !== 'draft') {
            return back()->with('success', 'hvac_not_approvable');
        }

        $recommendation->update([
            'status'      => 'approved',
            'approved_by' => session('admin_user_email'),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'hvac_recommendation_approved');
    }

    public function reject(Request $request, CustomerRequest $customerRequest, HvacRecommendation $recommendation): RedirectResponse
    {
        $this->guardScope($customerRequest, $recommendation);

        if (! in_array($recommendation->status, ['draft', 'manual_review', 'approved'], true)) {
            return back()->with('success', 'hvac_not_rejectable');
        }

        $recommendation->update(['status' => 'rejected']);

        return back()->with('success', 'hvac_recommendation_rejected');
    }

    public function acknowledgeWarnings(
        Request $request,
        CustomerRequest $customerRequest,
        HvacRecommendation $recommendation
    ): RedirectResponse {
        $this->guardScope($customerRequest, $recommendation);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($recommendation->status !== 'manual_review') {
            return back()->with('success', 'hvac_not_acknowledgeable');
        }

        $recommendation->calculation->overrides()->create([
            'field'            => "recommendation:{$recommendation->id}:warnings_acknowledged",
            'original_value'   => 'manual_review',
            'overridden_value' => 'draft',
            'reason'           => $data['reason'],
            'overridden_by'    => (string) session('admin_user_email'),
            'created_at'       => now(),
        ]);

        $recommendation->update(['status' => 'draft']);

        return back()->with('success', 'hvac_warnings_acknowledged');
    }

    public function overrideVat(
        Request $request,
        CustomerRequest $customerRequest,
        HvacRecommendation $recommendation,
        HvacManualOverrideService $overrideService
    ): RedirectResponse {
        $this->guardScope($customerRequest, $recommendation);

        $data = $request->validate([
            'vat_rate' => ['required', 'numeric', 'in:6,21'],
            'reason'   => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $overrideService->overrideVatRate(
            $recommendation,
            (float) $data['vat_rate'],
            $data['reason'],
            (string) session('admin_user_email')
        );

        return back()->with('success', 'hvac_override_applied');
    }

    public function overrideItem(
        Request $request,
        CustomerRequest $customerRequest,
        HvacRecommendationItem $item,
        HvacManualOverrideService $overrideService
    ): RedirectResponse {
        $this->guardItemScope($customerRequest, $item);

        $data = $request->validate([
            'quantity'        => ['nullable', 'numeric', 'min:0.01', 'max:9999'],
            'sale_unit_price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'reason'          => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if (($data['quantity'] ?? null) === null && ($data['sale_unit_price'] ?? null) === null) {
            return back()->withErrors(['quantity' => 'Geef een nieuwe hoeveelheid of prijs op.']);
        }

        $overrideService->overrideItem(
            $item,
            isset($data['quantity']) ? (float) $data['quantity'] : null,
            isset($data['sale_unit_price']) ? (float) $data['sale_unit_price'] : null,
            $data['reason'],
            (string) session('admin_user_email')
        );

        return back()->with('success', 'hvac_override_applied');
    }

    public function changeProduct(
        Request $request,
        CustomerRequest $customerRequest,
        HvacRecommendationItem $item,
        HvacManualOverrideService $overrideService
    ): RedirectResponse {
        $this->guardItemScope($customerRequest, $item);

        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:hvac_products,id'],
            'reason'     => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $product = HvacProduct::findOrFail($data['product_id']);

        try {
            $overrideService->changeItemProduct(
                $item,
                $product,
                $data['reason'],
                (string) session('admin_user_email')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['product_id' => $e->getMessage()]);
        }

        return back()->with('success', 'hvac_override_applied');
    }

    public function convert(
        Request $request,
        CustomerRequest $customerRequest,
        HvacRecommendation $recommendation,
        HvacQuoteConversionService $conversionService
    ): RedirectResponse {
        $this->guardScope($customerRequest, $recommendation);

        // Idempotency: block double-submits creating duplicate quotes.
        $lock = Cache::lock("hvac-convert:{$recommendation->id}", 30);
        if (! $lock->get()) {
            return back()->with('success', 'hvac_conversion_in_progress');
        }

        try {
            $conversionService->convert($recommendation, (string) session('admin_user_email'));
        } catch (\DomainException $e) {
            return back()->withErrors(['hvac_convert' => $e->getMessage()]);
        } finally {
            $lock->release();
        }

        return redirect()
            ->route('admin.requests.show', $customerRequest)
            ->with('success', 'hvac_converted');
    }

    private function guardScope(CustomerRequest $customerRequest, HvacRecommendation $recommendation): void
    {
        abort_unless(
            $recommendation->calculation !== null
            && $recommendation->calculation->customer_request_id === $customerRequest->id,
            404
        );
    }

    private function guardItemScope(CustomerRequest $customerRequest, HvacRecommendationItem $item): void
    {
        abort_unless(
            $item->recommendation?->calculation !== null
            && $item->recommendation->calculation->customer_request_id === $customerRequest->id,
            404
        );
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\Quote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function edit(CustomerRequest $customerRequest): View
    {
        return view('admin.quotes.edit', [
            'customerRequest' => $customerRequest,
            'quote'           => $customerRequest->quote,
        ]);
    }

    public function store(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'title'           => ['nullable', 'string', 'max:200'],
            'description'     => ['nullable', 'string'],
            'amount_excl_vat' => ['nullable', 'numeric', 'min:0'],
            'vat_rate'        => ['nullable', 'numeric', 'min:0'],
            'valid_until'     => ['nullable', 'date'],
        ]);

        $amountExclVat = isset($validated['amount_excl_vat']) ? (float) $validated['amount_excl_vat'] : null;
        $vatRate       = isset($validated['vat_rate']) ? (float) $validated['vat_rate'] : 21.0;
        $amountVat     = null;
        $amountInclVat = null;

        if ($amountExclVat !== null) {
            $amountVat     = round($amountExclVat * ($vatRate / 100), 2);
            $amountInclVat = round($amountExclVat + $amountVat, 2);
        }

        $existingQuote = $customerRequest->quote;
        $quoteNumber   = $existingQuote?->quote_number ?? $this->generateQuoteNumber();

        Quote::updateOrCreate(
            ['customer_request_id' => $customerRequest->id],
            [
                'quote_number'    => $quoteNumber,
                'title'           => $validated['title'] ?? null,
                'description'     => $validated['description'] ?? null,
                'amount_excl_vat' => $amountExclVat,
                'vat_rate'        => $vatRate,
                'amount_vat'      => $amountVat,
                'amount_incl_vat' => $amountInclVat,
                'valid_until'     => $validated['valid_until'] ?? null,
            ]
        );

        return redirect()->route('admin.requests.show', $customerRequest)
            ->with('success', 'quote_saved');
    }

    public function performAction(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:mark_sent,mark_accepted,mark_rejected'],
        ]);

        $quote = $customerRequest->quote;

        if (! $quote) {
            return back()->withErrors(['action' => 'Geen offerte gevonden voor deze aanvraag.']);
        }

        match ($validated['action']) {
            'mark_sent'     => $this->applyMarkSent($quote, $customerRequest),
            'mark_accepted' => $this->applyMarkAccepted($quote, $customerRequest),
            'mark_rejected' => $this->applyMarkRejected($quote, $customerRequest),
        };

        return back()->with('success', 'quote_action_applied');
    }

    private function applyMarkSent(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'sent',
            'sent_at'      => $quote->sent_at ?? now(),
        ]);

        $customerRequest->update([
            'status'        => 'quote_sent',
            'quote_sent_at' => $customerRequest->quote_sent_at ?? now(),
        ]);
    }

    private function applyMarkAccepted(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'accepted',
            'accepted_at'  => $quote->accepted_at ?? now(),
        ]);

        $customerRequest->update([
            'status' => 'won',
            'won_at' => $customerRequest->won_at ?? now(),
        ]);
    }

    private function applyMarkRejected(Quote $quote, CustomerRequest $customerRequest): void
    {
        $quote->update([
            'quote_status' => 'rejected',
            'rejected_at'  => $quote->rejected_at ?? now(),
        ]);

        $customerRequest->update([
            'status'  => 'lost',
            'lost_at' => $customerRequest->lost_at ?? now(),
        ]);
    }

    private function generateQuoteNumber(): string
    {
        $year = now()->year;
        $max  = Quote::where('quote_number', 'LIKE', "OFF-{$year}-%")->max('quote_number');
        $next = $max ? ((int) substr($max, -4)) + 1 : 1;

        return 'OFF-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

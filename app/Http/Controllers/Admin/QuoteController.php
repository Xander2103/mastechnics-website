<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function edit(CustomerRequest $customerRequest): View
    {
        $quote = $customerRequest->quote;

        if ($quote) {
            $quote->ensureDefaultItem();
            $quote->load('items');
        }

        return view('admin.quotes.edit', [
            'customerRequest' => $customerRequest,
            'quote'           => $quote,
        ]);
    }

    public function store(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $validated = $request->validate([
            'title'                           => ['nullable', 'string', 'max:200'],
            'description'                     => ['nullable', 'string'],
            'valid_until'                     => ['nullable', 'date'],
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.description'             => ['required', 'string', 'max:500'],
            'items.*.quantity'                => ['required', 'numeric', 'min:0'],
            'items.*.unit_price_excl_vat'     => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate'                => ['required', 'numeric', 'min:0'],
        ]);

        $existingQuote = $customerRequest->quote;
        $quoteNumber   = $existingQuote?->quote_number ?? $this->generateQuoteNumber();

        $quote = Quote::updateOrCreate(
            ['customer_request_id' => $customerRequest->id],
            [
                'quote_number' => $quoteNumber,
                'title'        => $validated['title'] ?? null,
                'description'  => $validated['description'] ?? null,
                'valid_until'  => $validated['valid_until'] ?? null,
            ]
        );

        // Sync items: delete all existing, recreate in submitted order
        $quote->items()->delete();

        foreach ($validated['items'] as $index => $itemData) {
            $lineTotals = QuoteItem::calculateLine(
                (float) $itemData['quantity'],
                (float) $itemData['unit_price_excl_vat'],
                (float) $itemData['vat_rate']
            );

            $quote->items()->create([
                'position'            => $index + 1,
                'description'         => $itemData['description'],
                'quantity'            => $itemData['quantity'],
                'unit_price_excl_vat' => $itemData['unit_price_excl_vat'],
                'vat_rate'            => $itemData['vat_rate'],
                ...$lineTotals,
            ]);
        }

        $quote->recalculateTotals();

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

    public function pdf(CustomerRequest $customerRequest): Response
    {
        $quote = $customerRequest->quote;

        abort_if(! $quote, 404, 'Geen offerte gevonden.');

        $quote->load('items');

        $pdf = Pdf::loadView('admin.quotes.pdf', [
            'quote'           => $quote,
            'customerRequest' => $customerRequest,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = strtolower($quote->quote_number ?? 'offerte') . '-mastechnics-offerte.pdf';

        return $pdf->stream($filename);
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

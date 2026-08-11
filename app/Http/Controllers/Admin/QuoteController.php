<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteSentMail;
use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\MailDispatcher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
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
            'items.*.quantity'                => ['required', 'numeric', 'min:0', 'max:9999'],
            'items.*.unit_price_excl_vat'     => ['required', 'numeric', 'min:0', 'max:1000000'],
            'items.*.vat_rate'                => ['required', 'numeric', 'in:0,6,12,21'],
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

        $pdf = $this->renderQuotePdf($quote, $customerRequest);

        $filename = strtolower($quote->quote_number ?? 'offerte') . '-mastechnics-offerte.pdf';

        return $pdf->stream($filename);
    }

    public function sendEmail(Request $request, CustomerRequest $customerRequest): RedirectResponse
    {
        $quote = $customerRequest->quote;

        abort_if(! $quote, 404, 'Geen offerte gevonden.');

        $validated = $request->validate([
            'to'      => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:5000'],
        ]);

        // Double-click / concurrent-request guard: only one send per quote
        // may run at a time. The UI also disables the submit button, but the
        // lock closes the server-side race.
        $lock = Cache::lock('quote-email-send-' . $customerRequest->id, 15);

        if (! $lock->get()) {
            return back()->with('success', 'quote_email_in_progress');
        }

        try {
            // The send form only renders for draft quotes; a replayed POST
            // against an already-sent quote must not email the customer again.
            if ($quote->quote_status !== 'draft') {
                return back()->with('success', 'quote_email_already_sent');
            }

            $quote->load('items');

            $pdfBinary = $this->renderQuotePdf($quote, $customerRequest)->output();

            $sent = MailDispatcher::send(
                $validated['to'],
                new QuoteSentMail($customerRequest, $quote, $validated['subject'], $validated['body'], $pdfBinary),
                $customerRequest
            );

            // Status and quote_sent_at only advance when the mail actually
            // went out; a failed send leaves the quote in draft so the admin
            // can retry. (MailDispatcher already isolates mail-log failures
            // from the send outcome, so a log hiccup never causes a resend.)
            if ($sent) {
                $this->applyMarkSent($quote, $customerRequest);
            }

            return back()->with(
                'success',
                $sent ? 'quote_email_sent' : 'quote_email_failed'
            );
        } finally {
            $lock->release();
        }
    }

    private function renderQuotePdf(Quote $quote, CustomerRequest $customerRequest)
    {
        $pdf = Pdf::loadView('admin.quotes.pdf', [
            'quote'           => $quote,
            'customerRequest' => $customerRequest,
        ]);

        $pdf->setPaper('A4', 'portrait');

        return $pdf;
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

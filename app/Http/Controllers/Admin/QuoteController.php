<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\QuoteSentMail;
use App\Models\CustomerRequest;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\MailDispatcher;
use App\Services\QuoteNumberGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Illuminate\View\View;

class QuoteController extends Controller
{
    /**
     * Largest amount the quote/quote_items decimal(10,2) columns can hold.
     * Validation keeps every line and the quote total below it so a save
     * can never fail half-way with an out-of-range error on MySQL.
     */
    private const MAX_AMOUNT = 99999999.99;

    /**
     * Which quote status may move to which. Anything else is refused server
     * side, matching the buttons the detail page shows.
     */
    private const TRANSITIONS = [
        'mark_sent'     => ['draft'],
        'mark_accepted' => ['sent'],
        'mark_rejected' => ['sent'],
    ];

    public function __construct(private readonly QuoteNumberGenerator $quoteNumbers)
    {
    }

    public function edit(CustomerRequest $customerRequest): View|RedirectResponse
    {
        $quote = $customerRequest->quote;

        if ($quote !== null && $quote->quote_status !== 'draft') {
            return redirect()->route('admin.requests.show', $customerRequest)
                ->withErrors(['quote' => 'Een verstuurde offerte kan niet meer bewerkt worden.']);
        }

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
        $existingQuote = $customerRequest->quote;

        // A quote that has been sent (or accepted/rejected) is the document
        // the customer holds: it is never rewritten in place.
        if ($existingQuote !== null && $existingQuote->quote_status !== 'draft') {
            return redirect()->route('admin.requests.show', $customerRequest)
                ->withErrors(['quote' => 'Een verstuurde offerte kan niet meer bewerkt worden.']);
        }

        $validated = $request->validate([
            'title'                           => ['nullable', 'string', 'max:200'],
            'description'                     => ['nullable', 'string', 'max:5000'],
            'valid_until'                     => ['nullable', 'date'],
            'items'                           => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description'             => ['required', 'string', 'max:500'],
            'items.*.quantity'                => ['required', 'numeric', 'min:0', 'max:9999'],
            'items.*.unit_price_excl_vat'     => ['required', 'numeric', 'min:0', 'max:1000000'],
            'items.*.vat_rate'                => ['required', 'numeric', 'in:0,6,12,21'],
        ]);

        $lines = [];

        foreach ($validated['items'] as $index => $itemData) {
            $lines[$index] = QuoteItem::calculateLine(
                (float) $itemData['quantity'],
                (float) $itemData['unit_price_excl_vat'],
                (float) $itemData['vat_rate']
            );
        }

        $totalIncl = array_sum(array_column($lines, 'line_total_incl_vat'));

        $validator = validator([]);
        $validator->after(function (Validator $v) use ($lines, $totalIncl): void {
            foreach ($lines as $index => $line) {
                if ($line['line_total_incl_vat'] > self::MAX_AMOUNT) {
                    $v->errors()->add("items.{$index}.unit_price_excl_vat", 'Het lijntotaal is te groot (maximaal 99.999.999,99).');
                }
            }

            if ($totalIncl > self::MAX_AMOUNT) {
                $v->errors()->add('items', 'Het offertetotaal is te groot (maximaal 99.999.999,99).');
            }
        });
        $validator->validate();

        $writeQuote = function (?string $quoteNumber) use ($customerRequest, $existingQuote, $validated, $lines): Quote {
            return DB::transaction(function () use ($customerRequest, $existingQuote, $validated, $lines, $quoteNumber): Quote {
                $quote = Quote::updateOrCreate(
                    ['customer_request_id' => $customerRequest->id],
                    [
                        'quote_number' => $existingQuote?->quote_number ?? $quoteNumber,
                        'title'        => $validated['title'] ?? null,
                        'description'  => $validated['description'] ?? null,
                        'valid_until'  => $validated['valid_until'] ?? null,
                    ]
                );

                // Sync items: delete all existing, recreate in submitted order.
                // Inside the transaction, so a failing insert keeps the old items.
                $quote->items()->delete();

                foreach ($validated['items'] as $index => $itemData) {
                    $quote->items()->create([
                        'position'            => $index + 1,
                        'description'         => $itemData['description'],
                        'quantity'            => $itemData['quantity'],
                        'unit_price_excl_vat' => $itemData['unit_price_excl_vat'],
                        'vat_rate'            => $itemData['vat_rate'],
                        ...$lines[$index],
                    ]);
                }

                $quote->recalculateTotals();

                return $quote;
            });
        };

        if ($existingQuote !== null) {
            $writeQuote(null);
        } else {
            // First save: reserve the number under the generator's lock so
            // two admins saving at once never collide on the unique index.
            $this->quoteNumbers->withNextNumber($writeQuote);
        }

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

        if (! in_array($quote->quote_status, self::TRANSITIONS[$validated['action']], true)) {
            return back()->withErrors([
                'action' => 'Deze actie is niet mogelijk voor een offerte met status "' . $quote->quote_status . '".',
            ]);
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
}

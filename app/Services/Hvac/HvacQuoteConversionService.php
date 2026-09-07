<?php

namespace App\Services\Hvac;

use App\Models\HvacRecommendation;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\QuoteNumberGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Converts an APPROVED recommendation into the existing quote system.
 *
 * - Only approved recommendations convert; anything else throws.
 * - The readiness gate is re-evaluated at conversion time: a critical rule
 *   unvalidated after approval blocks the conversion.
 * - An existing quote is never silently replaced: conversion is blocked
 *   until Martin removes the existing quote through the normal quote flow.
 * - Quote items preserve SKU, description, quantity, unit price and VAT.
 * - Purchase prices and margin NEVER reach the quote (customer-facing).
 * - The quote stays a draft; sending remains a separate explicit action
 *   through the existing quote mail flow. No automatic email, ever.
 * - Test-catalog (demo) recommendations may convert so the flow can be
 *   rehearsed, but the quote title is marked and such a quote can never
 *   be e-mailed (QuoteController::sendEmail refuses the marker).
 */
class HvacQuoteConversionService
{
    public const TEST_CATALOG_TITLE_PREFIX = '[TESTCATALOGUS] ';

    public function __construct(
        private readonly QuoteNumberGenerator $quoteNumbers,
        private readonly HvacRecommendationReadiness $readiness,
    ) {
    }

    public function convert(HvacRecommendation $recommendation, string $adminEmail): Quote
    {
        if ($recommendation->status !== 'approved') {
            throw new \DomainException('Alleen goedgekeurde opties kunnen omgezet worden naar een offerte.');
        }

        // A recalculation supersedes the calculation this approval was based
        // on — stale approvals must never become quotes.
        if ($recommendation->calculation->status !== 'calculated') {
            throw new \DomainException(
                'Deze goedkeuring hoort bij een verouderde berekening. Voer de voorcalculatie opnieuw uit en keur opnieuw goed.'
            );
        }

        // Re-gate: readiness can regress after approval (e.g. a critical
        // rule was unvalidated). Same gate as approval, same blockers.
        $evaluation = $this->readiness->evaluate($recommendation->loadMissing('items', 'calculation'));
        if (! $evaluation['ready']) {
            throw new \DomainException(
                'Deze optie voldoet niet meer aan de voorwaarden om omgezet te worden: '
                . implode(' ', $evaluation['blockers'])
            );
        }

        $customerRequest = $recommendation->calculation->customerRequest;

        if ($customerRequest->quote()->exists()) {
            throw new \DomainException(
                'Er bestaat al een offerte voor deze aanvraag. Verwijder of verwerk die eerst — bestaande offertes worden nooit stilzwijgend overschreven.'
            );
        }

        $isDemo = (bool) $evaluation['demo'];

        // The quote number is reserved under the generator's lock for the
        // whole transaction, so a concurrent conversion or manual quote save
        // cannot end up with the same number.
        return $this->quoteNumbers->withNextNumber(fn (string $quoteNumber) => DB::transaction(function () use ($recommendation, $customerRequest, $adminEmail, $quoteNumber, $isDemo) {
            $vatRate = (float) $recommendation->vat_rate;

            $quote = Quote::create([
                'customer_request_id' => $customerRequest->id,
                'quote_number'        => $quoteNumber,
                'quote_status'        => 'draft',
                'title'               => ($isDemo ? self::TEST_CATALOG_TITLE_PREFIX : '') . $this->quoteTitle($customerRequest->locale),
                'vat_rate'            => $vatRate,
                'valid_until'         => now()->addDays(30)->toDateString(),
            ]);

            $position = 0;
            foreach ($recommendation->items()->orderBy('id')->get() as $item) {
                $position++;
                $lineTotals = QuoteItem::calculateLine(
                    (float) $item->quantity,
                    (float) $item->sale_unit_price,
                    $vatRate
                );

                $quote->items()->create([
                    'position'            => $position,
                    'description'         => ($item->sku !== null ? "[{$item->sku}] " : '') . $item->description,
                    'quantity'            => $item->quantity,
                    'unit_price_excl_vat' => $item->sale_unit_price,
                    'vat_rate'            => $vatRate,
                    ...$lineTotals,
                ]);
            }

            $quote->recalculateTotals();

            // Preserve the audit trail in both directions.
            $recommendation->update([
                'status'             => 'converted',
                'converted_quote_id' => $quote->id,
            ]);

            $recommendation->calculation->overrides()->create([
                'field'            => "recommendation:{$recommendation->id}:converted",
                'original_value'   => 'approved',
                'overridden_value' => "quote:{$quote->id} ({$quote->quote_number})",
                'reason'           => 'Omgezet naar offerte door de beheerder.',
                'overridden_by'    => $adminEmail,
                'created_at'       => now(),
            ]);

            return $quote->fresh(['items']);
        }));
    }

    private function quoteTitle(string $locale): string
    {
        return match ($locale) {
            'fr'    => 'Installation de climatisation — pré-calcul',
            'en'    => 'Air-conditioning installation — pre-calculation',
            default => 'Airco-installatie — voorcalculatie',
        };
    }
}

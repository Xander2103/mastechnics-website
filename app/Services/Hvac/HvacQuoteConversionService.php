<?php

namespace App\Services\Hvac;

use App\Models\HvacRecommendation;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;

/**
 * Converts an APPROVED recommendation into the existing quote system.
 *
 * - Only approved recommendations convert; anything else throws.
 * - An existing quote is never silently replaced: conversion is blocked
 *   until Martin removes the existing quote through the normal quote flow.
 * - Quote items preserve SKU, description, quantity, unit price and VAT.
 * - Purchase prices and margin NEVER reach the quote (customer-facing).
 * - The quote stays a draft; sending remains a separate explicit action
 *   through the existing quote mail flow. No automatic email, ever.
 */
class HvacQuoteConversionService
{
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

        $customerRequest = $recommendation->calculation->customerRequest;

        if ($customerRequest->quote()->exists()) {
            throw new \DomainException(
                'Er bestaat al een offerte voor deze aanvraag. Verwijder of verwerk die eerst — bestaande offertes worden nooit stilzwijgend overschreven.'
            );
        }

        return DB::transaction(function () use ($recommendation, $customerRequest, $adminEmail) {
            $vatRate = (float) $recommendation->vat_rate;

            $quote = Quote::create([
                'customer_request_id' => $customerRequest->id,
                'quote_number'        => $this->generateQuoteNumber(),
                'quote_status'        => 'draft',
                'title'               => $this->quoteTitle($customerRequest->locale),
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
        });
    }

    private function quoteTitle(string $locale): string
    {
        return match ($locale) {
            'fr'    => 'Installation de climatisation — pré-calcul',
            'en'    => 'Air-conditioning installation — pre-calculation',
            default => 'Airco-installatie — voorcalculatie',
        };
    }

    private function generateQuoteNumber(): string
    {
        $year = now()->year;
        $max  = Quote::where('quote_number', 'LIKE', "OFF-{$year}-%")->max('quote_number');
        $next = $max ? ((int) substr($max, -4)) + 1 : 1;

        return 'OFF-' . $year . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}

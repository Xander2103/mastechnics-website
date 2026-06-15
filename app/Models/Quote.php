<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    protected $fillable = [
        'customer_request_id',
        'quote_number',
        'quote_status',
        'title',
        'description',
        'amount_excl_vat',
        'vat_rate',
        'amount_vat',
        'amount_incl_vat',
        'valid_until',
        'sent_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'amount_excl_vat'  => 'decimal:2',
        'vat_rate'         => 'decimal:2',
        'amount_vat'       => 'decimal:2',
        'amount_incl_vat'  => 'decimal:2',
        'valid_until'      => 'date',
        'sent_at'          => 'datetime',
        'accepted_at'      => 'datetime',
        'rejected_at'      => 'datetime',
    ];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /**
     * If this is a legacy quote with an amount but no items, seed one default item.
     * Idempotent: does nothing if items already exist or amount is null.
     */
    public function ensureDefaultItem(): void
    {
        if ($this->items()->count() > 0) {
            return;
        }

        if ($this->amount_excl_vat === null) {
            return;
        }

        $vatRate  = (float) ($this->vat_rate ?? 21);
        $unitPrice = (float) $this->amount_excl_vat;
        $lineTotals = QuoteItem::calculateLine(1.0, $unitPrice, $vatRate);

        $this->items()->create([
            'position'            => 1,
            'description'         => 'Offertebedrag',
            'quantity'            => 1.00,
            'unit_price_excl_vat' => $unitPrice,
            'vat_rate'            => $vatRate,
            ...$lineTotals,
        ]);
    }

    /**
     * Recompute quote totals from items. Call after syncing items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $this->update([
            'amount_excl_vat' => round((float) $items->sum('line_total_excl_vat'), 2),
            'amount_vat'      => round((float) $items->sum('line_vat_amount'), 2),
            'amount_incl_vat' => round((float) $items->sum('line_total_incl_vat'), 2),
        ]);
    }
}

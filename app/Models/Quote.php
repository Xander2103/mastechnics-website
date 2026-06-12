<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}

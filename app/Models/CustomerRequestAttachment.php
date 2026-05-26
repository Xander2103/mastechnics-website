<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequestAttachment extends Model
{
    protected $fillable = [
        'customer_request_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}
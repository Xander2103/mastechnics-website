<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequestAppointment extends Model
{
    protected $fillable = [
        'customer_request_id',
        'date',
        'time',
        'technician',
        'location',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function customerRequest(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class);
    }
}

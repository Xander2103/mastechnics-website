<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerRequest extends Model
{
    protected $fillable = [
        'locale',
        'service_slug',
        'request_type',
        'customer_name',
        'customer_email',
        'customer_phone',
        'brand',
        'device_model',
        'serial_number',
        'unknown_device_details',
        'description',
        'status',
        'metadata',
    ];

    protected $casts = [
        'unknown_device_details' => 'boolean',
        'metadata' => 'array',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerRequestAttachment::class);
    }
}

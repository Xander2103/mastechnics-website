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
        'description',
        'brand',
        'device_model',
        'serial_number',
        'unknown_device_details',
        'metadata',
        'status',
    ];

    protected $casts = [
        'metadata' => 'array',
        'unknown_device_details' => 'boolean',
    ];

    public function attachments(): HasMany
    {
        return $this->hasMany(CustomerRequestAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerRequestNote::class)->latest();
    }
}
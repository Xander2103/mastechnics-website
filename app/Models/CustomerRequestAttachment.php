<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRequestAttachment extends Model
{
    /**
     * Customer uploads live on the private disk (storage/app/private): they
     * are only ever served through the authenticated admin download route,
     * never as a direct /storage URL.
     */
    public const DISK = 'local';

    /**
     * Disk the uploads were stored on before the move to the private disk.
     * Still consulted for reading/deleting files that the
     * attachments:move-to-private command has not moved yet.
     */
    public const LEGACY_DISK = 'public';

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
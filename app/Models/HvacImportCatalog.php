<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An imported supplier product list ("Productlijst"): the unit the admin
 * browses instead of one flat product table. Products are linked through a
 * pivot (a product may live in several yearly lists); import history lives in
 * HvacImportRun. Archiving is a status change only — linked products and
 * historical references always stay intact.
 */
class HvacImportCatalog extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_SUPERSEDED, self::STATUS_FAILED,
    ];

    protected $fillable = [
        'name', 'hvac_supplier_id', 'source_filename', 'source_type',
        'imported_by', 'imported_at', 'product_count', 'status', 'notes',
        'hvac_mapping_profile_id', 'checksum',
    ];

    protected $casts = [
        'imported_at'   => 'datetime',
        'product_count' => 'integer',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(HvacSupplier::class, 'hvac_supplier_id');
    }

    public function mappingProfile(): BelongsTo
    {
        return $this->belongsTo(HvacMappingProfile::class, 'hvac_mapping_profile_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(HvacProduct::class, 'hvac_import_catalog_product')
            ->withPivot(['source_row', 'imported_at', 'source_price', 'source_stock', 'source_lead_time'])
            ->withTimestamps();
    }

    public function runs(): HasMany
    {
        return $this->hasMany(HvacImportRun::class)->latest('id');
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}

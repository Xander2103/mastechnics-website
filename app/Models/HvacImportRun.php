<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One import execution for a catalog — powers the per-list history
 * ("11/08/2026 — 123 toegevoegd, 0 bijgewerkt, 7 waarschuwingen").
 */
class HvacImportRun extends Model
{
    protected $fillable = [
        'hvac_import_catalog_id', 'created_count', 'updated_count',
        'skipped_count', 'warning_count', 'deactivated_count',
        'imported_by', 'source_filename',
    ];

    protected $casts = [
        'created_count'     => 'integer',
        'updated_count'     => 'integer',
        'skipped_count'     => 'integer',
        'warning_count'     => 'integer',
        'deactivated_count' => 'integer',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(HvacImportCatalog::class, 'hvac_import_catalog_id');
    }
}

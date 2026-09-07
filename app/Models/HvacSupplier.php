<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacSupplier extends Model
{
    protected $fillable = ['name', 'code', 'email', 'phone', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Single, case-insensitive supplier identity for every import path:
     * "Airco NV", "airco nv" and " AIRCO NV " are the same supplier. Creates
     * the supplier when it does not exist yet.
     */
    public static function resolveByName(string $name): self
    {
        $name = trim($name);

        return static::findByName($name)
            ?? static::create(['name' => $name, 'is_active' => true]);
    }

    public static function findByName(string $name): ?self
    {
        return static::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->first();
    }

    public function products(): HasMany
    {
        return $this->hasMany(HvacProduct::class);
    }

    public function catalogs(): HasMany
    {
        return $this->hasMany(HvacImportCatalog::class);
    }
}

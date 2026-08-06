<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HvacSupplier extends Model
{
    protected $fillable = ['name', 'code', 'email', 'phone', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(HvacProduct::class);
    }
}

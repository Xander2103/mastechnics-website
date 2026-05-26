<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'code',
        'type',
        'is_active',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
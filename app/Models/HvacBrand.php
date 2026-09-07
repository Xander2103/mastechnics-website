<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class HvacBrand extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    /**
     * Slug used as the brand identity. Str::slug() of a non-Latin name
     * (日立, 三菱) is '' — every such brand would collapse into one row — so
     * fall back to a stable hash-based slug.
     */
    public static function slugFor(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'brand-' . substr(md5(mb_strtolower(trim($name))), 0, 12);
    }

    public function products(): HasMany
    {
        return $this->hasMany(HvacProduct::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class BlockedEmail extends Model
{
    protected $fillable = [
        'email',
        'reason',
        'blocked_by',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Active means: flagged active and not past its optional expiry.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * Case-insensitive blocklist check for the public contact form. A
     * missing/unavailable table must never take the contact form down,
     * so infrastructure failures degrade to "not blocked".
     */
    public static function isBlocked(string $email): bool
    {
        try {
            $block = static::query()
                ->where('email', static::normalizeEmail($email))
                ->first();
        } catch (QueryException $e) {
            Log::error('Blocked email lookup failed', ['error' => $e->getMessage()]);

            return false;
        }

        return $block !== null && $block->isCurrentlyActive();
    }
}

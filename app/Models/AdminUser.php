<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUser extends Model
{
    /**
     * Session key holding the fingerprint of the password hash the admin
     * logged in with. The admin middleware compares it on every request, so
     * a session dies as soon as the account is deleted or its password
     * changes — without that, a stale cookie keeps full admin access.
     */
    public const SESSION_FINGERPRINT_KEY = 'admin_auth_fingerprint';

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Derive the value that is stored in the session at login. Only a hash
     * of the password hash is stored, never the hash itself.
     */
    public function sessionFingerprint(): string
    {
        return hash('sha256', (string) $this->password);
    }

    /**
     * The session payload that represents "logged in as this admin".
     *
     * @return array<string, string>
     */
    public function sessionPayload(): array
    {
        return [
            'admin_user_name' => (string) $this->name,
            'admin_user_email' => (string) $this->email,
            self::SESSION_FINGERPRINT_KEY => $this->sessionFingerprint(),
        ];
    }
}

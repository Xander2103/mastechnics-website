<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactSubmission extends Model
{
    protected $fillable = [
        'token',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'locale',
        'mail_sent_at',
    ];

    protected $casts = [
        'mail_sent_at' => 'datetime',
    ];
}

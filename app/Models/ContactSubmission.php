<?php

namespace App\Models;

use App\Services\Spam\Trust\TrustDecision;
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
        'trust_verdict',
        'trust_score',
        'trust_reasons',
        'trust_reviewed_at',
        'trust_reviewed_by',
    ];

    protected $casts = [
        'mail_sent_at' => 'datetime',
        'trust_reasons' => 'array',
        'trust_reviewed_at' => 'datetime',
    ];

    public function needsReview(): bool
    {
        return $this->trust_verdict === TrustDecision::NEEDS_REVIEW;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HvacAiLog extends Model
{
    protected $fillable = [
        'hvac_recommendation_id', 'provider', 'model', 'prompt_version',
        'input_hash', 'output', 'validation_status', 'error',
    ];

    protected $casts = [
        'output' => 'array',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(HvacRecommendation::class, 'hvac_recommendation_id');
    }
}

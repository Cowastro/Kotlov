<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalSelectionRun extends Model
{
    protected $fillable = [
        'user_id', 'prompt', 'task', 'result', 'external_result',
    ];

    protected $casts = [
        'result' => 'array',
        'external_result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

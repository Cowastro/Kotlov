<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatSetting extends Model
{
    protected $fillable = [
        'ai_enabled',
        'enabled_by',
        'enabled_at',
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'enabled_at' => 'datetime',
    ];

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], ['ai_enabled' => false]);
    }

    public static function aiEnabled(): bool
    {
        return (bool) self::query()->whereKey(1)->value('ai_enabled');
    }

    public function enabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enabled_by');
    }
}

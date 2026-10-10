<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierAutoTransferDecision extends Model
{
    protected $fillable = [
        'supplier_id', 'user_id', 'enabled', 'readiness_snapshot', 'note', 'decided_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'readiness_snapshot' => 'array',
        'decided_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Решение об автоматической передаче нельзя изменять.'));
        static::deleting(fn (): never => throw new LogicException('Решение об автоматической передаче нельзя удалять отдельно.'));
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

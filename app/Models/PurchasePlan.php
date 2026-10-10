<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePlan extends Model
{
    public const STATUSES = [
        'draft' => 'Черновик',
        'confirmed' => 'Подтверждён',
        'cancelled' => 'Отменён',
    ];

    protected $fillable = [
        'number', 'status', 'integration_source_id', 'source_code', 'source_name',
        'period_days', 'items_count', 'total_quantity', 'known_purchase_total',
        'purchase_total', 'missing_price_count', 'snapshot_hash', 'created_by',
        'confirmed_by', 'cancelled_by', 'confirmed_at', 'cancelled_at', 'note',
    ];

    protected $casts = [
        'period_days' => 'integer',
        'items_count' => 'integer',
        'total_quantity' => 'decimal:3',
        'known_purchase_total' => 'decimal:2',
        'purchase_total' => 'decimal:2',
        'missing_price_count' => 'integer',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchasePlanItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchasePlanStatusHistory::class)->latest('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationIssue extends Model
{
    public const PRODUCT_TYPES = [
        'product_attention',
        'product_unmatched',
        'product_missing_category',
        'product_missing_price',
        'product_identity_collision',
    ];

    public const ORDER_TYPES = [
        'order_not_exported',
        'order_no_1c_response',
    ];

    public const EXCHANGE_TYPES = [
        'integration_stale',
    ];

    protected $fillable = [
        'integration_source_id', 'integration_product_id', 'order_id', 'assigned_to_user_id',
        'fingerprint', 'type', 'severity', 'status', 'title', 'message', 'context',
        'first_detected_at', 'last_detected_at', 'resolved_at',
    ];

    protected $casts = [
        'context' => 'array',
        'first_detected_at' => 'datetime',
        'last_detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeProducts(Builder $query): Builder
    {
        return $query->whereIn('type', self::PRODUCT_TYPES);
    }

    public function scopeMissingPrice(Builder $query): Builder
    {
        return $query->products()->where('context->missing_price', true);
    }

    public function scopeUnmatched(Builder $query): Builder
    {
        return $query->products()->where('context->unmatched', true);
    }

    public function scopePossibleDuplicates(Builder $query): Builder
    {
        return $query->where('type', 'product_identity_collision');
    }

    public function scopeOrders(Builder $query): Builder
    {
        return $query->whereIn('type', self::ORDER_TYPES);
    }

    public function scopeExchange(Builder $query): Builder
    {
        return $query->whereIn('type', self::EXCHANGE_TYPES);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function integrationProduct(): BelongsTo
    {
        return $this->belongsTo(IntegrationProduct::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }
}

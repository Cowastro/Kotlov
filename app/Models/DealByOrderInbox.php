<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealByOrderInbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'remote_order_id', 'remote_status', 'remote_status_name', 'remote_created_at',
        'remote_modified_at', 'first_seen_at', 'last_seen_at', 'payload_hash', 'payload',
        'processing_status', 'order_id', 'processing_attempts', 'processed_at', 'last_error',
    ];

    protected $hidden = ['payload'];

    protected $casts = [
        'remote_created_at' => 'datetime',
        'remote_modified_at' => 'datetime',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'payload' => 'encrypted:array',
        'processing_attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function customerName(): string
    {
        return collect([
            data_get($this->payload, 'client_last_name'),
            data_get($this->payload, 'client_first_name'),
            data_get($this->payload, 'client_second_name'),
        ])->filter(fn (mixed $value): bool => filled($value))->implode(' ') ?: 'Имя не указано';
    }

    public function customerPhone(): ?string
    {
        $phone = trim((string) data_get($this->payload, 'phone'));

        return $phone !== '' ? $phone : null;
    }

    public function orderTotal(): ?float
    {
        $value = data_get($this->payload, 'full_price', data_get($this->payload, 'price'));

        return is_numeric($value) ? (float) $value : null;
    }

    public function productsCount(): int
    {
        $products = data_get($this->payload, 'products', []);

        return is_array($products) ? count($products) : 0;
    }

    public function productsSummary(): string
    {
        $products = data_get($this->payload, 'products', []);
        if (! is_array($products)) {
            return 'Товары не указаны';
        }

        return collect($products)
            ->map(fn (mixed $product): string => is_array($product)
                ? trim((string) ($product['name'] ?? $product['sku'] ?? ''))
                : '')
            ->filter()
            ->take(2)
            ->implode(', ') ?: 'Товары не указаны';
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sourceLabel(): string
    {
        return 'Deal.by №'.$this->remote_order_id;
    }
}

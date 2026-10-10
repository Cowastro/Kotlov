<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOrderRequest extends Model
{
    public const STATUSES = [
        'draft' => 'Черновик',
        'sent' => 'Передана поставщику',
        'acknowledged' => 'Принята поставщиком',
        'rejected' => 'Отклонена',
        'fulfilled' => 'Исполнена',
        'cancelled' => 'Отменена',
    ];

    protected $fillable = [
        'order_id', 'supplier_id', 'created_by', 'number', 'route', 'status',
        'supplier_name', 'supplier_contact', 'item_count', 'purchase_total',
        'sent_at', 'sent_by', 'acknowledged_at', 'supplier_response_note',
        'rejected_at', 'fulfilled_at', 'status_updated_by', 'status_updated_at', 'note',
    ];

    protected $casts = [
        'purchase_total' => 'decimal:2',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'rejected_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'status_updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function statusUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderRequestItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(SupplierOrderRequestStatusHistory::class)
            ->latest('created_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

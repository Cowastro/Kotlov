<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierOrderRequest extends Model
{
    public const STATUSES = [
        'draft' => 'Черновик',
        'sent' => 'Отправлена',
        'acknowledged' => 'Принята поставщиком',
        'rejected' => 'Отклонена',
        'fulfilled' => 'Исполнена',
        'cancelled' => 'Отменена',
    ];

    protected $fillable = [
        'order_id', 'supplier_id', 'created_by', 'number', 'route', 'status',
        'supplier_name', 'supplier_contact', 'item_count', 'purchase_total',
        'sent_at', 'acknowledged_at', 'note',
    ];

    protected $casts = [
        'purchase_total' => 'decimal:2',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderRequestItem::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

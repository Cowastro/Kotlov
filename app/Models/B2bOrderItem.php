<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bOrderItem extends Model
{
    public const AVAILABILITY_STATUSES = [
        'pending' => 'Не проверено',
        'available' => 'Подтверждено',
        'partial' => 'Частично',
        'unavailable' => 'Нет в наличии',
        'replacement' => 'Предложена замена',
    ];

    protected $fillable = [
        'b2b_order_id', 'product_id', 'integration_product_id', 'supplier_product_id',
        'warehouse_stock_id', 'stock_key', 'supplier_id', 'product_name', 'product_sku', 'price', 'quantity',
        'total', 'base_price', 'purchase_price', 'supplier_name', 'price_level_code', 'discount_percent', 'warehouse_name',
        'delivery_days', 'availability_status', 'confirmed_quantity', 'stock_free_at_check',
        'availability_note', 'checked_at',
        'checked_by', 'manager_note',
    ];

    protected $casts = [
        'price' => 'decimal:2', 'total' => 'decimal:2', 'base_price' => 'decimal:2', 'purchase_price' => 'decimal:2',
        'discount_percent' => 'decimal:3', 'stock_free_at_check' => 'decimal:3', 'checked_at' => 'datetime',
    ];

    public function order(): BelongsTo { return $this->belongsTo(B2bOrder::class, 'b2b_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function integrationProduct(): BelongsTo { return $this->belongsTo(IntegrationProduct::class); }
    public function supplierProduct(): BelongsTo { return $this->belongsTo(SupplierProduct::class); }
    public function supplier(): BelongsTo { return $this->belongsTo(Supplier::class); }
    public function checkedBy(): BelongsTo { return $this->belongsTo(User::class, 'checked_by'); }
    public function warehouseStock(): BelongsTo { return $this->belongsTo(ProductWarehouseStock::class, 'warehouse_stock_id'); }
    public function reservations(): HasMany { return $this->hasMany(B2bOrderReservation::class); }

    public function reservedQuantity(): float
    {
        return (float) $this->reservations->where('status', 'active')->sum('quantity');
    }

    public function isConfirmed(): bool
    {
        return in_array($this->availability_status, ['available', 'partial'], true);
    }

    /** Removed by a manager with a stated reason: kept for history, not billed. */
    public function isExcluded(): bool
    {
        return $this->availability_status === 'unavailable'
            && $this->checked_by !== null
            && trim((string) $this->manager_note) !== '';
    }

    public function orderedQuantity(): int
    {
        return (int) ($this->confirmed_quantity ?: $this->quantity);
    }
}

<?php

namespace App\Models;

use App\Services\Orders\OrderItemSupplyContextResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class OrderItem extends Model
{
    public const FULFILLMENT_ROUTES = [
        'own_stock' => 'Наш склад',
        'supplier_purchase' => 'Закупить у поставщика',
        'direct_supplier' => 'Передать поставщику напрямую',
    ];

    /** @var array<string, mixed>|null */
    private ?array $supplyContextCache = null;

    protected $fillable = [
        'order_id', 'product_id',
        'product_name', 'product_sku',
        'price', 'quantity', 'total',
        'pricing_type', 'price_tax_mode', 'integration_product_id',
        'supply_status', 'supply_route_label', 'supply_supplier_id',
        'supply_integration_source_id', 'supply_channel', 'supply_supplier_name',
        'supply_supplier_contact', 'supply_source_label', 'supply_purchase_price',
        'supply_price_tax_mode', 'supply_vat_rate', 'supply_stock_quantity',
        'supply_is_available', 'supply_candidate_count', 'supply_captured_at',
        'fulfillment_route', 'fulfillment_supplier_id', 'fulfillment_supplier_name',
        'fulfillment_supplier_contact', 'fulfillment_confirmed_by',
        'fulfillment_confirmed_at', 'fulfillment_note',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'total' => 'decimal:2',
        'supply_purchase_price' => 'decimal:2',
        'supply_vat_rate' => 'decimal:2',
        'supply_stock_quantity' => 'decimal:3',
        'supply_is_available' => 'boolean',
        'supply_candidate_count' => 'integer',
        'supply_captured_at' => 'datetime',
        'fulfillment_confirmed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OrderItem $item): void {
            if ($item->supply_captured_at || ! Schema::hasColumn('order_items', 'supply_captured_at')) {
                return;
            }

            $context = app(OrderItemSupplyContextResolver::class)->resolve($item);

            $item->forceFill([
                'supply_status' => $context['status'],
                'supply_route_label' => $context['route_label'],
                'supply_supplier_id' => $context['supplier_id'],
                'supply_integration_source_id' => $context['integration_source_id'],
                'supply_channel' => $context['channel'],
                'supply_supplier_name' => $context['supplier_name'],
                'supply_supplier_contact' => $context['supplier_contact'],
                'supply_source_label' => $context['source_label'],
                'supply_purchase_price' => $context['wholesale_price'],
                'supply_price_tax_mode' => $context['price_tax_mode'],
                'supply_vat_rate' => $context['vat_rate'],
                'supply_stock_quantity' => $context['stock_quantity'],
                'supply_is_available' => $context['is_available'],
                'supply_candidate_count' => $context['candidate_count'],
                'supply_captured_at' => now(),
            ]);
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function integrationProduct(): BelongsTo
    {
        return $this->belongsTo(IntegrationProduct::class);
    }

    public function fulfillmentSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'fulfillment_supplier_id');
    }

    public function fulfillmentConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfillment_confirmed_by');
    }

    public function fulfillmentHistory(): HasMany
    {
        return $this->hasMany(OrderItemFulfillmentHistory::class)->latest();
    }

    public function fulfillmentRouteLabel(): ?string
    {
        return $this->fulfillment_route
            ? (self::FULFILLMENT_ROUTES[$this->fulfillment_route] ?? $this->fulfillment_route)
            : null;
    }

    /** @return array<string, mixed> */
    public function supplyContext(): array
    {
        return $this->supplyContextCache ??= app(OrderItemSupplyContextResolver::class)->resolve($this);
    }
}

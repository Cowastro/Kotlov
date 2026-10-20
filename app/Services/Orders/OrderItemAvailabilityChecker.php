<?php

namespace App\Services\Orders;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;

class OrderItemAvailabilityChecker
{
    public function __construct(private readonly OrderItemSupplyContextResolver $resolver) {}

    /** @return array<string, mixed> */
    public function check(OrderItem $item, ?User $user = null): array
    {
        $item->load([
            'integrationProduct.source.supplier',
            'product.purchasePriceSources.supplier',
            'product.integrationProducts.source.supplier.latestChannelTransition',
            'product.supplierProducts.supplier.latestChannelTransition',
        ]);

        $context = $this->resolver->resolveCurrent($item);

        if ($item->product?->manual_stock_management) {
            $available = $item->product->effectiveAvailabilityStatus() === Product::AVAILABILITY_IN_STOCK;
            $context['is_available'] = $available;
            $context['stock_quantity'] = $available ? (float) $item->product->stock_qty : 0.0;
            $context['stock_label'] = $available
                ? $this->formatQuantity((float) $item->product->stock_qty)
                : 'Сейчас нет в наличии';
            $context['stock_updated_at'] = $item->product->updated_at;
            $context['source_label'] = 'Ручное наличие карточки';
        }

        $check = [
            'status' => $context['status'],
            'supplier_id' => $context['supplier_id'],
            'supplier_name' => $context['supplier_name'],
            'source_label' => $context['source_label'],
            'stock_quantity' => $context['stock_quantity'],
            'stock_label' => $context['stock_label'],
            'is_available' => $context['is_available'],
            'source_updated_at' => $this->dateString($context['stock_updated_at'] ?? null),
        ];

        $item->forceFill([
            'current_availability' => $check,
            'current_availability_checked_at' => now(),
            'current_availability_checked_by' => $user?->id,
        ])->saveQuietly();

        return $check;
    }

    private function dateString(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    private function formatQuantity(float $quantity): string
    {
        $formatted = abs($quantity - round($quantity)) < 0.0005
            ? number_format($quantity, 0, '.', ' ')
            : rtrim(rtrim(number_format($quantity, 3, '.', ' '), '0'), '.');

        return $formatted.' шт.';
    }
}

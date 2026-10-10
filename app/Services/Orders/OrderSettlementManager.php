<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderSettlement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderSettlementManager
{
    /** @return array<string, mixed> */
    public function preview(
        Order $order,
        float $deliveryCost,
        float $paymentFee,
        float $refundTotal,
    ): array {
        foreach (compact('deliveryCost', 'paymentFee', 'refundTotal') as $field => $value) {
            if ($value < 0) {
                throw ValidationException::withMessages([
                    $this->fieldName($field) => 'Сумма не может быть отрицательной.',
                ]);
            }
        }

        $order->loadMissing('items.fulfillmentSupplier');
        if ($order->items->isEmpty()) {
            throw ValidationException::withMessages(['order' => 'В заказе нет позиций для взаиморасчёта.']);
        }

        $lines = $order->items->map(fn (OrderItem $item): array => $this->calculateLine($item))->values();
        $goodsSaleTotal = round((float) $lines->sum('sale_total'), 2);
        $deliveryRevenue = round((float) ($order->delivery_price ?? 0), 2);
        $discountTotal = round((float) ($order->discount ?? 0), 2);
        $orderTotal = round((float) $order->total, 2);

        if ($refundTotal > $orderTotal) {
            throw ValidationException::withMessages([
                'refund_total' => 'Возврат не может превышать сумму заказа.',
            ]);
        }

        $routeSignature = $this->routeSignatureFromLines($lines->all());
        $basis = [
            'route_signature' => $routeSignature,
            'delivery_cost' => round($deliveryCost, 2),
            'payment_fee' => round($paymentFee, 2),
            'refund_total' => round($refundTotal, 2),
        ];
        $platformGrossMargin = round((float) $lines->sum('platform_margin'), 2);
        $netProfit = round($platformGrossMargin + $deliveryRevenue - $discountTotal - $deliveryCost - $paymentFee - $refundTotal, 2);

        return [
            'route_signature' => $routeSignature,
            'basis_signature' => hash('sha256', json_encode($basis, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)),
            'currency' => 'BYN',
            'goods_sale_total' => $goodsSaleTotal,
            'delivery_revenue' => $deliveryRevenue,
            'discount_total' => $discountTotal,
            'order_total' => $orderTotal,
            'cost_of_goods_total' => round((float) $lines->sum('cost_of_goods'), 2),
            'supplier_payable_total' => round((float) $lines->sum('supplier_payable'), 2),
            'marketplace_commission_total' => round((float) $lines->sum('commission_amount'), 2),
            'reseller_margin_total' => round((float) $lines->whereIn('route', ['own_stock', 'supplier_purchase'])->sum('platform_margin'), 2),
            'platform_gross_margin_total' => $platformGrossMargin,
            'delivery_cost' => round($deliveryCost, 2),
            'payment_fee' => round($paymentFee, 2),
            'refund_total' => round($refundTotal, 2),
            'net_profit' => $netProfit,
            'calculation_basis' => $basis,
            'lines' => $lines->all(),
        ];
    }

    public function confirm(
        Order $order,
        float $deliveryCost,
        float $paymentFee,
        float $refundTotal,
        ?User $user,
        ?string $note = null,
    ): OrderSettlement {
        return DB::transaction(function () use ($order, $deliveryCost, $paymentFee, $refundTotal, $user, $note): OrderSettlement {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $order->load(['items.fulfillmentSupplier']);
            $preview = $this->preview($order, $deliveryCost, $paymentFee, $refundTotal);

            $existing = $order->settlements()
                ->where('basis_signature', $preview['basis_signature'])
                ->first();
            if ($existing) {
                return $existing->load('lines');
            }

            $version = ((int) $order->settlements()->max('version')) + 1;
            $lines = $preview['lines'];
            unset($preview['lines']);

            $settlement = $order->settlements()->create($preview + [
                'version' => $version,
                'confirmed_by' => $user?->id,
                'confirmed_at' => now(),
                'note' => filled($note) ? trim($note) : null,
            ]);
            $settlement->lines()->createMany($lines);

            return $settlement->load('lines');
        });
    }

    public function routeSignature(Order $order): string
    {
        $order->loadMissing('items.fulfillmentSupplier');

        $lines = $order->items
            ->map(fn (OrderItem $item): array => $this->routeState($item))
            ->values()
            ->all();

        return $this->routeSignatureFromLines($lines);
    }

    public function isCurrent(OrderSettlement $settlement, Order $order): bool
    {
        return hash_equals($settlement->route_signature, $this->routeSignature($order));
    }

    /** @return array<string, mixed> */
    private function calculateLine(OrderItem $item): array
    {
        $route = $item->fulfillment_route;
        if (! array_key_exists((string) $route, OrderItem::FULFILLMENT_ROUTES)) {
            throw ValidationException::withMessages([
                'fulfillment' => 'Сначала подтвердите маршрут для позиции «'.$item->product_name.'».',
            ]);
        }

        $supplier = $item->fulfillmentSupplier;
        if (in_array($route, ['supplier_purchase', 'direct_supplier'], true) && ! $supplier) {
            throw ValidationException::withMessages([
                'fulfillment' => 'Для позиции «'.$item->product_name.'» не выбран поставщик.',
            ]);
        }

        $quantity = (int) $item->quantity;
        $saleTotal = round((float) $item->total, 2);
        $purchasePrice = $item->fulfillment_purchase_price !== null
            ? (float) $item->fulfillment_purchase_price
            : null;
        $costOfGoods = 0.0;
        $supplierPayable = 0.0;
        $commissionRate = null;
        $commissionAmount = 0.0;

        if (in_array($route, ['own_stock', 'supplier_purchase'], true)) {
            if ($purchasePrice === null) {
                throw ValidationException::withMessages([
                    'fulfillment' => 'Для позиции «'.$item->product_name.'» не указана подтверждённая входная цена.',
                ]);
            }
            $costOfGoods = round($purchasePrice * $quantity, 2);
            $supplierPayable = $route === 'supplier_purchase' ? $costOfGoods : 0.0;
        } else {
            if ($supplier->marketplace_commission_rate === null) {
                throw ValidationException::withMessages([
                    'fulfillment' => 'Для поставщика «'.$supplier->name.'» не задана комиссия прямой продажи.',
                ]);
            }
            $commissionRate = (float) $supplier->marketplace_commission_rate;
            $commissionAmount = round($saleTotal * $commissionRate / 100, 2);
            $supplierPayable = round($saleTotal - $commissionAmount, 2);
        }

        $platformMargin = $route === 'direct_supplier'
            ? $commissionAmount
            : round($saleTotal - $costOfGoods, 2);

        return $this->routeState($item) + [
            'order_item_id' => $item->id,
            'supplier_id' => $supplier?->id,
            'supplier_name' => $supplier?->name ?? $item->fulfillment_supplier_name,
            'supplier_contact' => $supplier?->contact ?? $item->fulfillment_supplier_contact,
            'route' => $route,
            'product_name' => $item->product_name,
            'product_sku' => $item->product_sku,
            'quantity' => $quantity,
            'sale_total' => $saleTotal,
            'purchase_price' => $purchasePrice,
            'cost_of_goods' => $costOfGoods,
            'supplier_payable' => $supplierPayable,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'platform_margin' => $platformMargin,
        ];
    }

    /** @return array<string, mixed> */
    private function routeState(OrderItem $item): array
    {
        return [
            'item_id' => $item->id,
            'route_state' => $item->fulfillment_route,
            'supplier_state' => $item->fulfillment_supplier_id,
            'purchase_price_state' => $item->fulfillment_purchase_price !== null
                ? round((float) $item->fulfillment_purchase_price, 2)
                : null,
            'commission_rate_state' => $item->fulfillmentSupplier?->marketplace_commission_rate !== null
                ? round((float) $item->fulfillmentSupplier->marketplace_commission_rate, 4)
                : null,
            'quantity_state' => (int) $item->quantity,
            'sale_total_state' => round((float) $item->total, 2),
        ];
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function routeSignatureFromLines(array $lines): string
    {
        $state = collect($lines)
            ->map(fn (array $line): array => array_intersect_key($line, array_flip([
                'item_id', 'route_state', 'supplier_state', 'purchase_price_state',
                'commission_rate_state', 'quantity_state', 'sale_total_state',
            ])))
            ->sortBy('item_id')
            ->values()
            ->all();

        return hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function fieldName(string $camelCase): string
    {
        return match ($camelCase) {
            'deliveryCost' => 'delivery_cost',
            'paymentFee' => 'payment_fee',
            default => 'refund_total',
        };
    }
}

<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierOrderRequestItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierOrderRequestBuilder
{
    /** @return Collection<int, SupplierOrderRequest> */
    public function buildDrafts(Order $order, ?User $user): Collection
    {
        $order->loadMissing('items');
        $externalItems = $order->items
            ->filter(fn (OrderItem $item): bool => in_array($item->fulfillment_route, ['supplier_purchase', 'direct_supplier'], true)
                && $item->fulfillment_supplier_id !== null);

        if ($externalItems->isEmpty()) {
            throw ValidationException::withMessages([
                'order' => 'Сначала подтвердите внешний маршрут хотя бы для одной позиции заказа.',
            ]);
        }

        return DB::transaction(function () use ($order, $externalItems, $user): Collection {
            $requests = $externalItems
                ->groupBy(fn (OrderItem $item): string => $item->fulfillment_supplier_id.'|'.$item->fulfillment_route)
                ->map(function (Collection $items) use ($order, $user): SupplierOrderRequest {
                    /** @var OrderItem $first */
                    $first = $items->first();
                    $request = SupplierOrderRequest::query()->firstOrNew([
                        'order_id' => $order->id,
                        'supplier_id' => $first->fulfillment_supplier_id,
                        'route' => $first->fulfillment_route,
                    ]);

                    if ($request->exists && ! in_array($request->status, ['draft', 'cancelled'], true)) {
                        throw ValidationException::withMessages([
                            'order' => "Заявка {$request->number} уже передана поставщику и не может быть перестроена.",
                        ]);
                    }

                    $pricesComplete = $items->every(fn (OrderItem $item): bool => $item->fulfillment_purchase_price !== null);
                    $request->fill([
                        'created_by' => $user?->id,
                        'number' => $request->number ?: $this->number($order, $first),
                        'status' => 'draft',
                        'supplier_name' => $first->fulfillment_supplier_name,
                        'supplier_contact' => $first->fulfillment_supplier_contact,
                        'item_count' => $items->count(),
                        'purchase_total' => $pricesComplete
                            ? $items->sum(fn (OrderItem $item): float => (float) $item->fulfillment_purchase_price * $item->quantity)
                            : null,
                    ])->save();

                    foreach ($items as $item) {
                        SupplierOrderRequestItem::query()->updateOrCreate(
                            ['order_item_id' => $item->id],
                            [
                                'supplier_order_request_id' => $request->id,
                                'product_name' => $item->product_name,
                                'product_sku' => $item->product_sku,
                                'quantity' => $item->quantity,
                                'purchase_price' => $item->fulfillment_purchase_price,
                                'purchase_total' => $item->fulfillment_purchase_price !== null
                                    ? (float) $item->fulfillment_purchase_price * $item->quantity
                                    : null,
                            ],
                        );
                    }

                    return $request->load('items');
                })
                ->values();

            SupplierOrderRequest::query()
                ->where('order_id', $order->id)
                ->where('status', 'draft')
                ->whereNotIn('id', $requests->pluck('id'))
                ->update([
                    'status' => 'cancelled',
                    'item_count' => 0,
                    'purchase_total' => null,
                ]);

            return $requests;
        });
    }

    private function number(Order $order, OrderItem $item): string
    {
        $route = $item->fulfillment_route === 'direct_supplier' ? 'DIRECT' : 'BUY';

        return 'SR-'.$order->number.'-'.$item->fulfillment_supplier_id.'-'.$route;
    }
}

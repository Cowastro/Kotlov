<?php

namespace App\Services\B2bOrders;

use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;
use App\Models\B2bOrderItem;
use App\Models\B2bOrderReservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-click availability check: each line is matched against the stock pools
 * of its product, reserved where the free balance covers it and moved to
 * another pool when the linked one is empty. Only what the data cannot
 * decide is left to the manager (no balance reported, shortage, no card).
 */
class B2bOrderStockCheck
{
    public function __construct(private readonly B2bStockLocator $locator) {}

    /** @return array{available: int, partial: int, unavailable: int, manual: int} */
    public function check(B2bOrder $order, ?User $actor = null): array
    {
        return DB::transaction(function () use ($order, $actor): array {
            $locked = B2bOrder::query()->lockForUpdate()->findOrFail($order->id);
            $summary = ['available' => 0, 'partial' => 0, 'unavailable' => 0, 'manual' => 0];

            foreach ($locked->items()->with(['product', 'integrationProduct.source'])->get() as $item) {
                // A line a manager has decided on stays as decided.
                if ($item->checked_by !== null) {
                    $summary[$this->summaryBucket($item)]++;

                    continue;
                }

                $this->checkItem($item, $actor);
                $summary[$this->summaryBucket($item)]++;
            }

            $this->recalculate($locked);
            B2bOrderEvent::query()->create([
                'b2b_order_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event' => 'stock_checked',
                'comment' => sprintf(
                    'Проверка остатков: в наличии %d, частично %d, нет %d, требуют решения %d.',
                    $summary['available'], $summary['partial'], $summary['unavailable'], $summary['manual'],
                ),
                'metadata' => $summary,
            ]);

            return $summary;
        });
    }

    /** Applies a manager's decision on a line: chosen pool, quantity, reserve. */
    public function applyManagerDecision(B2bOrderItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $item->refresh();
            $pool = $item->product && $item->stock_key
                ? $this->locator->poolsForProduct($item->product, $item->b2b_order_id)->firstWhere('key', $item->stock_key)
                : null;
            if ($pool) {
                $this->pointAt($item, $pool);
            }
            if ($item->availability_status === 'available') {
                $item->confirmed_quantity = $item->quantity;
            }
            if (! in_array($item->availability_status, ['available', 'partial'], true)) {
                $item->confirmed_quantity = null;
            }
            $item->forceFill([
                'stock_free_at_check' => $pool['free'] ?? null,
                'availability_note' => $pool ? 'Решение менеджера · '.$pool['label'] : 'Решение менеджера',
                'checked_at' => now(),
                'checked_by' => $actor->id,
            ])->save();

            $this->reserve($item, $pool, $actor);
            $order = B2bOrder::query()->lockForUpdate()->findOrFail($item->b2b_order_id);
            $this->recalculate($order);

            B2bOrderEvent::query()->create([
                'b2b_order_id' => $order->id,
                'actor_id' => $actor->id,
                'event' => 'item_checked',
                'comment' => 'Проверена позиция '.$item->product_name.'.',
                'metadata' => [
                    'item_id' => $item->id,
                    'availability_status' => $item->availability_status,
                    'confirmed_quantity' => $item->confirmed_quantity,
                    'price' => (float) $item->price,
                    'stock_key' => $item->stock_key,
                ],
            ]);
        });
    }

    public function releaseOrder(B2bOrder $order): void
    {
        B2bOrderReservation::query()->active()->where('b2b_order_id', $order->id)
            ->update(['status' => 'released', 'released_at' => now()]);
    }

    /** Shipped goods leave the pool; the next stock sync reflects them. */
    public function shipOrder(B2bOrder $order): void
    {
        B2bOrderReservation::query()->active()->where('b2b_order_id', $order->id)
            ->update(['status' => 'shipped', 'released_at' => now()]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function poolsFor(B2bOrderItem $item): Collection
    {
        return $item->product
            ? $this->locator->poolsForProduct($item->product, $item->b2b_order_id)
            : collect();
    }

    private function checkItem(B2bOrderItem $item, ?User $actor): void
    {
        $quantity = (int) $item->quantity;
        if (! $item->product) {
            $this->decide($item, null, 'pending', null, 'Нет карточки товара — проверьте вручную.', $actor);

            return;
        }

        $pools = $this->locator->poolsForProduct($item->product, $item->b2b_order_id);
        $linkedKey = $this->locator->keyForItem($item);
        $linked = $linkedKey ? $pools->firstWhere('key', $linkedKey) : null;
        $covers = fn (array $pool): bool => $pool['always_available'] || (float) $pool['free'] >= $quantity;

        if ($linked && $covers($linked)) {
            $this->decide($item, $linked, 'available', $quantity, $linked['label'], $actor);

            return;
        }

        $other = $pools->first(fn (array $pool): bool => $pool['key'] !== $linkedKey && $covers($pool));
        if ($other) {
            $from = $linked ? ' (на «'.$linked['warehouse_name'].'» не хватает)' : '';
            $this->decide($item, $other, 'available', $quantity, 'Взято со склада'.$from.' · '.$other['label'], $actor);

            return;
        }

        $best = $linked && (float) $linked['free'] > 0
            ? $linked
            : $pools->filter(fn (array $pool): bool => (float) $pool['free'] > 0)->sortByDesc('free')->first();
        if ($best) {
            $free = (int) floor((float) $best['free']);
            if ($free >= 1) {
                $this->decide($item, $best, 'partial', $free, 'Хватает '.$free.' из '.$quantity.' · '.$best['label'], $actor);

                return;
            }
        }

        $onRequest = $linked && $linked['stock'] === null
            ? $linked
            : $pools->first(fn (array $pool): bool => $pool['stock'] === null);
        if ($onRequest) {
            $this->decide($item, $onRequest, 'pending', null, 'Подтвердить у поставщика · '.$onRequest['label'], $actor);

            return;
        }

        $this->decide($item, $linked, 'unavailable', null, $pools->isEmpty()
            ? 'Товар не привязан ни к одному складу или поставщику.'
            : 'Нет свободного остатка ни на одном складе.', $actor);
    }

    /** @param array<string, mixed>|null $pool */
    private function decide(B2bOrderItem $item, ?array $pool, string $status, ?int $confirmed, string $note, ?User $actor): void
    {
        if ($pool) {
            $this->pointAt($item, $pool);
        }
        $item->forceFill([
            'availability_status' => $status,
            'confirmed_quantity' => $confirmed,
            'stock_free_at_check' => $pool['free'] ?? null,
            'availability_note' => mb_substr($note, 0, 255),
            'checked_at' => now(),
        ])->save();

        $this->reserve($item, $pool, $actor);
    }

    /** @param array<string, mixed> $pool */
    private function pointAt(B2bOrderItem $item, array $pool): void
    {
        $item->forceFill([
            'stock_key' => $pool['key'],
            'integration_product_id' => $pool['integration_product_id'] ?? $item->integration_product_id,
            'warehouse_stock_id' => $pool['warehouse_stock_id'],
            'supplier_product_id' => $pool['supplier_product_id'],
            'supplier_id' => $pool['supplier_id'],
            'supplier_name' => $pool['supplier_name'],
            'warehouse_name' => $pool['warehouse_name'],
            'delivery_days' => $pool['delivery_days'] ?? $item->delivery_days,
            'purchase_price' => $pool['purchase_price'] ?? $item->purchase_price,
        ]);
        if ($pool['kind'] !== 'integration' && $pool['kind'] !== 'warehouse') {
            $item->integration_product_id = null;
        }
    }

    /** @param array<string, mixed>|null $pool */
    private function reserve(B2bOrderItem $item, ?array $pool, ?User $actor): void
    {
        B2bOrderReservation::query()->active()->where('b2b_order_item_id', $item->id)
            ->update(['status' => 'released', 'released_at' => now()]);

        $quantity = in_array($item->availability_status, ['available', 'partial'], true)
            ? (int) ($item->confirmed_quantity ?: 0)
            : 0;
        if (! $pool || $quantity < 1) {
            return;
        }

        B2bOrderReservation::query()->create([
            'b2b_order_id' => $item->b2b_order_id,
            'b2b_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'stock_key' => $pool['key'],
            'integration_product_id' => $pool['integration_product_id'],
            'warehouse_stock_id' => $pool['warehouse_stock_id'],
            'supplier_product_id' => $pool['supplier_product_id'],
            'supplier_id' => $pool['supplier_id'],
            'warehouse_name' => $pool['warehouse_name'],
            'quantity' => $quantity,
            'reserved_by' => $actor?->id,
        ]);
    }

    private function recalculate(B2bOrder $order): void
    {
        $subtotal = (float) $order->items()->get()->sum(fn (B2bOrderItem $item): float => in_array($item->availability_status, ['unavailable'], true)
            ? 0.0
            : (float) $item->price * $item->orderedQuantity());
        $order->forceFill([
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal - (float) $order->discount + (float) $order->delivery_price, 2),
        ])->saveQuietly();
    }

    private function summaryBucket(B2bOrderItem $item): string
    {
        return match ($item->availability_status) {
            'available' => 'available',
            'partial' => 'partial',
            'unavailable' => 'unavailable',
            default => 'manual',
        };
    }
}

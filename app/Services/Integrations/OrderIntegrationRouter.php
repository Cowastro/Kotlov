<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderIntegrationRouter
{
    public function eligibleOrders(IntegrationSource $source): Builder
    {
        return Order::query()
            ->when($source->code === 'onec', fn (Builder $query): Builder => $query
                ->whereHas('integrationDeliveries', fn (Builder $query): Builder => $query
                    ->where('integration_source_id', $source->id)
                    ->whereNotNull('queued_at')
                    ->whereNull('exported_at')
                    ->whereIn('status', [
                        OrderIntegrationDelivery::STATUS_PENDING,
                        OrderIntegrationDelivery::STATUS_FAILED,
                    ])))
            ->whereDoesntHave('integrationDeliveries', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id)
                ->whereNotNull('exported_at'))
            ->where(function (Builder $query) use ($source): void {
                $query->whereHas('items', fn (Builder $query): Builder => $this->ownedItemsQuery($query, $source));

                if ($source->code === 'onec' && ! $source->requiresLinkedOrderProducts()) {
                    // Preserve central accounting for legacy/manual orders that
                    // were created without item rows.
                    $query->orWhereDoesntHave('items');
                }
            });
    }

    public function orderBelongsToSource(Order $order, IntegrationSource $source): bool
    {
        if ($source->code === 'onec' && ! $order->items()->exists()) {
            return true;
        }

        return $order->items()
            ->where(fn (Builder $query): Builder => $this->ownedItemsQuery($query, $source))
            ->exists();
    }

    /** @return Collection<int, OrderItem> */
    public function itemsForSource(Order $order, IntegrationSource $source): Collection
    {
        $order->loadMissing([
            'items.integrationProduct',
        ]);

        return $order->items
            ->filter(fn (OrderItem $item): bool => $this->sourceOwnsItem($item, $source))
            ->values();
    }

    public function sourceOwnsItem(OrderItem $item, IntegrationSource $source): bool
    {
        if (! in_array($item->pricing_type, $source->orderPricingTypes(), true)) {
            return false;
        }

        if ($item->integration_product_id) {
            return (int) $item->integrationProduct?->integration_source_id === (int) $source->id;
        }

        return $source->code === 'onec' && ! $source->requiresLinkedOrderProducts();
    }

    private function ownedItemsQuery(Builder $query, IntegrationSource $source): Builder
    {
        return $query
            ->whereIn('pricing_type', $source->orderPricingTypes())
            ->where(function (Builder $query) use ($source): void {
            $query->whereHas('integrationProduct', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id));

            if ($source->code === 'onec' && ! $source->requiresLinkedOrderProducts()) {
                $query->orWhereNull('integration_product_id');
            }
        });
    }
}

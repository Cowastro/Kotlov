<?php

namespace App\Services\Integrations;

use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderIntegrationRouter
{
    public function eligibleOrders(IntegrationSource $source): Builder
    {
        return Order::query()
            ->when($source->code === 'onec', fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($source): void {
                    $query->whereNull('onec_exported_at')
                        ->orWhereHas('integrationDeliveries', fn (Builder $query): Builder => $query
                            ->where('integration_source_id', $source->id)
                            ->whereNull('exported_at'));
                }))
            ->whereDoesntHave('integrationDeliveries', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id)
                ->whereNotNull('exported_at'))
            ->where(function (Builder $query) use ($source): void {
                $query->whereHas('items', fn (Builder $query): Builder => $this->ownedItemsQuery($query, $source));

                if ($source->code === 'onec') {
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
        if ($item->integration_product_id) {
            return (int) $item->integrationProduct?->integration_source_id === (int) $source->id;
        }

        return $source->code === 'onec';
    }

    private function ownedItemsQuery(Builder $query, IntegrationSource $source): Builder
    {
        return $query->where(function (Builder $query) use ($source): void {
            $query->whereHas('integrationProduct', fn (Builder $query): Builder => $query
                ->where('integration_source_id', $source->id));

            if ($source->code === 'onec') {
                $query->orWhereNull('integration_product_id');
            }
        });
    }
}

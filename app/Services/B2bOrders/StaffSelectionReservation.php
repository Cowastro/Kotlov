<?php

namespace App\Services\B2bOrders;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\User;
use App\Services\StaffCatalogWorkspace;
use App\Services\Suppliers\SupplierOrderRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffSelectionReservation
{
    public function expire(): int
    {
        $count = 0;
        B2bOrder::whereHas('reservations', fn ($query) => $query->where('status', 'active')->where('expires_at', '<=', now()))->eachById(function ($order) use (&$count): void {
            DB::transaction(function () use ($order, &$count): void {
                $locked = B2bOrder::lockForUpdate()->findOrFail($order->id);
                $released = $locked->reservations()->where('status', 'active')->where('expires_at', '<=', now())->update(['status' => 'released', 'released_at' => now()]);
                if ($released > 0) {
                    $locked->events()->create(['event' => 'reservation_expired', 'comment' => 'Срок внутреннего резерва истёк.', 'metadata' => ['released_rows' => $released]]);
                    $count += $released;
                }
            });
        });

        return $count;
    }

    public function create(CustomerSelection $selection, User $manager, array $data): B2bOrder
    {
        abort_unless(StaffCatalogWorkspace::allowed($manager), 403);

        return DB::transaction(function () use ($selection, $manager, $data): B2bOrder {
            $draft = CustomerSelection::where('user_id', $manager->id)->lockForUpdate()->findOrFail($selection->id);
            if ($id = $draft->meta['published_b2b_order_id'] ?? null) {
                $existing = B2bOrder::findOrFail($id);
                abort_unless((int) $existing->b2b_client_id === (int) $data['b2b_client_id'], 422, 'Этот подбор уже передан другой компании. Создайте копию для новой заявки.');

                return $existing;
            }
            abort_unless($draft->revision === (int) $data['revision'], 409, 'Подбор изменён. Обновите страницу перед передачей клиенту.');
            $client = B2bClient::where('status', 'approved')->whereHas('user', fn ($query) => $query->where('is_active', true))->with('user')->findOrFail($data['b2b_client_id']);
            $items = array_diff_key($draft->items, array_flip($draft->meta['desk_excluded_ids'] ?? []));
            abort_if($items === [], 422, 'Отметьте товары, которые нужно передать клиенту.');
            $expires = now()->addDays((int) $data['hold_days']);
            $order = B2bOrder::create([
                'b2b_client_id' => $client->id, 'created_by' => $manager->id, 'assigned_manager_id' => $manager->id,
                'number' => B2bOrder::generateNumber(), 'status' => 'awaiting_client', 'payment_type' => 'invoice', 'payment_status' => 'pending',
                'delivery_type' => 'b2b_delivery', 'delivery_city' => $client->default_delivery_city, 'delivery_address' => $client->default_delivery_address,
                'contact_name' => $client->contact_name ?: $client->user->name, 'contact_phone' => $client->contact_phone ?: ($client->user->phone ?? ''),
                'contact_email' => $client->contact_email ?: $client->user->email, 'company_name' => $client->company_name, 'company_unp' => $client->unp,
                'commercial_terms_snapshot' => ['staff_reservation' => ['selection_id' => $draft->id, 'revision' => $draft->revision, 'expires_at' => $expires->toIso8601String(), 'scope' => 'internal']],
                'availability_confirmed_at' => now(),
            ]);
            // Shared product locks serialize stock reservations across orders.
            $products = Product::active()->notArchived()->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $workspace = app(StaffCatalogWorkspace::class);
            $locator = app(B2bStockLocator::class);
            $subtotal = 0;
            foreach ($items as $id => $item) {
                $product = $products->get($id);
                if (! $product || (float) $item['price'] <= 0 || (int) $item['quantity'] < 1) {
                    throw ValidationException::withMessages(['reservation' => ($item['name'] ?? 'Товар').': проверьте доступность, цену и количество.']);
                }
                $offer = $workspace->offers($product, $manager)->firstWhere('supplier_key', $item['workspace_purchase']['supplier_key'] ?? null);
                if (! $offer || empty($offer['stock_key'])) {
                    throw ValidationException::withMessages(['reservation' => $item['name'].': выберите поставщика с подтверждённым складским остатком в каталоге.']);
                }
                $context = clone $product;
                $context->setRelation('supplierProducts', $product->operationalSupplierProducts());
                $context->setRelation('integrationProducts', $product->operationalIntegrationProducts());
                $context->setRelation('warehouseStocks', $product->warehouseStocks->filter(fn ($stock) => $stock->supplier?->is_active));
                $pool = $locator->poolsForProduct($context)->firstWhere('key', $offer['stock_key']);
                $quantity = (int) $item['quantity'];
                if (! $pool || $pool['stock'] === null || $pool['free'] < $quantity || SupplierOrderRules::warning($offer['order_rules'] ?? [], $quantity) !== '') {
                    throw ValidationException::withMessages(['reservation' => $item['name'].': недостаточно подтверждённого свободного остатка или не соблюдены условия заказа. Уменьшите количество либо оставьте позицию неотмеченной.']);
                }
                $total = round((float) $item['price'] * $quantity, 2);
                $line = $order->items()->create([
                    'product_id' => $product->id, 'product_name' => $item['name'], 'product_sku' => $item['sku'] ?? null,
                    'price' => $item['price'], 'quantity' => $quantity, 'total' => $total, 'base_price' => $item['client_pricing']['base_price'] ?? $item['price'],
                    'purchase_price' => $item['workspace_purchase']['purchase_price'] ?? $pool['purchase_price'],
                    'price_level_code' => 'agreed', 'discount_percent' => $item['client_pricing']['discount_percent'] ?? null,
                    'stock_key' => $pool['key'], 'supplier_id' => $pool['supplier_id'], 'supplier_name' => $pool['supplier_name'], 'warehouse_name' => $pool['warehouse_name'],
                    'integration_product_id' => $pool['integration_product_id'], 'supplier_product_id' => $pool['supplier_product_id'], 'warehouse_stock_id' => $pool['warehouse_stock_id'],
                    'availability_status' => 'available', 'confirmed_quantity' => $quantity, 'stock_free_at_check' => $pool['free'], 'checked_at' => now(), 'checked_by' => $manager->id,
                    'manager_note' => $item['workspace_note'] ?? null,
                ]);
                $line->reservations()->create([
                    'b2b_order_id' => $order->id, 'product_id' => $product->id, 'stock_key' => $pool['key'], 'quantity' => $quantity,
                    'supplier_id' => $pool['supplier_id'], 'warehouse_name' => $pool['warehouse_name'], 'supplier_product_id' => $pool['supplier_product_id'],
                    'integration_product_id' => $pool['integration_product_id'], 'warehouse_stock_id' => $pool['warehouse_stock_id'],
                    'status' => 'active', 'reserved_by' => $manager->id, 'expires_at' => $expires,
                ]);
                $subtotal += $total;
            }
            $order->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal, 2)]);
            $order->events()->create(['actor_id' => $manager->id, 'event' => 'staff_selection_reserved', 'to_status' => 'awaiting_client', 'comment' => 'Внутренний резерв из подбора. Без обмена с 1С и отправки писем.']);
            if (filled($data['client_message'] ?? null)) {
                $order->comments()->create(['author_id' => $manager->id, 'visible_to_client' => true, 'body' => $data['client_message']]);
            }
            $draft->update(['meta' => array_replace($draft->meta ?? [], ['published_b2b_order_id' => $order->id]), 'revision' => $draft->revision + 1]);

            return $order;
        });
    }

    public function release(CustomerSelection $selection, User $manager): void
    {
        abort_unless(StaffCatalogWorkspace::allowed($manager), 403);
        DB::transaction(function () use ($selection, $manager): void {
            $draft = CustomerSelection::where('user_id', $manager->id)->lockForUpdate()->findOrFail($selection->id);
            $order = B2bOrder::lockForUpdate()->findOrFail($draft->meta['published_b2b_order_id'] ?? 0);
            abort_unless($order->isStaffReservation() && in_array($order->status, ['awaiting_client', 'client_confirmed', 'cancelled'], true), 422, 'На этом этапе резерв снимается через обработку заказа.');
            if ($order->status === 'cancelled') {
                return;
            }
            $order->reservations()->where('status', 'active')->update(['status' => 'released', 'released_at' => now()]);
            $order->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $order->events()->create(['actor_id' => $manager->id, 'event' => 'staff_reservation_released', 'to_status' => 'cancelled', 'comment' => 'Менеджер снял внутренний резерв.']);
        });
    }
}

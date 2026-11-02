<?php

namespace App\Console\Commands;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bOrderEvent;
use App\Models\B2bOrderItem;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateLegacyB2bOrdersCommand extends Command
{
    protected $signature = 'b2b:orders:migrate-legacy {--apply : Выполнить перенос; без флага только показать план}';
    protected $description = 'Идемпотентно переносит старые B2B-заказы из общей таблицы orders в отдельный B2B-модуль';

    public function handle(): int
    {
        if (! Schema::hasTable('b2b_orders')) {
            $this->error('Сначала выполните миграции: таблица b2b_orders отсутствует.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $eligible = 0;
        $skipped = 0;
        $migrated = 0;

        Order::query()->with('items')->orderBy('id')->chunkById(100, function ($orders) use ($apply, &$eligible, &$skipped, &$migrated): void {
            foreach ($orders as $order) {
                $clientId = (int) data_get($order->b2b_commercial_terms_snapshot, 'b2b_client_id', 0);
                $isB2b = $clientId > 0
                    && $order->items->isNotEmpty()
                    && $order->items->every(fn ($item): bool => $item->pricing_type === 'b2b')
                    && B2bClient::query()->whereKey($clientId)->exists();

                if (! $isB2b) {
                    $skipped++;
                    continue;
                }

                $eligible++;
                if (! $apply || B2bOrder::query()->where('legacy_order_id', $order->id)->exists()) {
                    continue;
                }

                DB::transaction(function () use ($order, $clientId): void {
                    $b2bOrder = B2bOrder::create([
                        'b2b_client_id' => $clientId,
                        'created_by' => $order->user_id,
                        'assigned_manager_id' => $order->manager_id,
                        'accepted_by' => $order->availability_confirmed_by,
                        'commercial_document_id' => $order->commercial_document_id,
                        'legacy_order_id' => $order->id,
                        'number' => str_starts_with($order->number, 'B2B-') ? $order->number : 'B2B-'.$order->number,
                        'status' => $this->mapStatus($order->status),
                        'payment_status' => $order->payment_status,
                        'payment_type' => $order->payment_type,
                        'delivery_type' => $order->delivery_type,
                        'delivery_region' => $order->delivery_region,
                        'delivery_city' => $order->delivery_city,
                        'delivery_address' => $order->delivery_address,
                        'delivery_price' => $order->delivery_price,
                        'contact_name' => $order->customer_name,
                        'contact_phone' => $order->customer_phone,
                        'contact_email' => $order->customer_email,
                        'company_name' => $order->company_name ?: data_get($order->b2b_commercial_terms_snapshot, 'company_name', 'B2B-клиент'),
                        'company_unp' => $order->company_unp,
                        'company_address' => $order->company_address,
                        'company_email' => $order->company_email,
                        'subtotal' => $order->subtotal,
                        'discount' => $order->discount,
                        'total' => $order->total,
                        'commercial_terms_snapshot' => $order->b2b_commercial_terms_snapshot,
                        'client_comment' => $order->comment,
                        'manager_note' => $order->admin_comment,
                        'accepted_at' => $order->availability_confirmed_at,
                        'availability_confirmed_at' => $order->availability_confirmed_at,
                        'completed_at' => $order->status === 'delivered' ? $order->updated_at : null,
                        'cancelled_at' => $order->status === 'cancelled' ? $order->updated_at : null,
                    ]);
                    DB::table('b2b_orders')->where('id', $b2bOrder->id)->update([
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                    ]);

                    foreach ($order->items as $item) {
                        $b2bItem = B2bOrderItem::create([
                            'b2b_order_id' => $b2bOrder->id,
                            'product_id' => $item->product_id,
                            'integration_product_id' => $item->integration_product_id,
                            'supplier_product_id' => $item->supplier_product_id,
                            'warehouse_stock_id' => $item->warehouse_stock_id,
                            'supplier_id' => $item->fulfillment_supplier_id ?: $item->supply_supplier_id,
                            'product_name' => $item->product_name,
                            'product_sku' => $item->product_sku,
                            'price' => $item->price,
                            'quantity' => $item->quantity,
                            'total' => $item->total,
                            'base_price' => $item->b2b_base_price,
                            'purchase_price' => $item->fulfillment_purchase_price ?: $item->supply_purchase_price,
                            'supplier_name' => $item->fulfillment_supplier_name ?: $item->supply_supplier_name,
                            'price_level_code' => $item->b2b_price_level_code,
                            'discount_percent' => $item->b2b_discount_percent,
                            'warehouse_name' => $item->supply_warehouse_name,
                            'delivery_days' => $item->supply_delivery_days,
                            'availability_status' => $this->mapAvailability($item->fulfillment_availability_status),
                            'confirmed_quantity' => $item->fulfillment_availability_status ? $item->quantity : null,
                            'checked_at' => $item->fulfillment_confirmed_at,
                            'checked_by' => $item->fulfillment_confirmed_by,
                            'manager_note' => $item->fulfillment_note,
                        ]);
                        DB::table('b2b_order_items')->where('id', $b2bItem->id)->update([
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ]);
                    }

                    $event = B2bOrderEvent::create([
                        'b2b_order_id' => $b2bOrder->id,
                        'event' => 'legacy_migrated',
                        'to_status' => $b2bOrder->status,
                        'comment' => 'Перенесено из прежнего общего модуля заказов.',
                        'metadata' => ['legacy_order_id' => $order->id],
                    ]);
                    DB::table('b2b_order_events')->where('id', $event->id)->update([
                        'created_at' => $order->created_at,
                        'updated_at' => $order->created_at,
                    ]);
                });
                $migrated++;
            }
        });

        $this->table(['Режим', 'Подходят', 'Перенесено', 'Пропущено'], [[
            $apply ? 'ПРИМЕНЕНИЕ' : 'ПРЕДПРОСМОТР', $eligible, $migrated, $skipped,
        ]]);
        if (! $apply) $this->info('Для переноса запустите команду повторно с --apply. Розничные записи не удаляются.');

        return self::SUCCESS;
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'checking' => 'checking', 'confirmed' => 'proposal_ready', 'processing' => 'assembling',
            'shipped' => 'shipped', 'delivered' => 'completed', 'cancelled' => 'cancelled', default => 'new',
        };
    }

    private function mapAvailability(?string $status): string
    {
        return match ($status) {
            'available', 'reserved', 'ordered' => 'available', 'unavailable' => 'unavailable', default => 'pending',
        };
    }
}

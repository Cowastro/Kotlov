<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\ProductWarehouseStock;
use App\Support\Suppliers\EcokaminChannel;
use Illuminate\Support\Facades\DB;

class EcokaminB2bOfferPublisher
{
    public const SOURCE_CODE = 'ecokamin_moscow';

    /** @return array{source_id:int,offers:int,categories:int,clients:int} */
    public function sync(): array
    {
        $supplierId = EcokaminChannel::supplierId();
        $brand = Brand::query()
            ->where('slug', 'ecokamin')
            ->orWhere('name', 'like', '%ЭкоКамин%')
            ->first();

        if (! $supplierId || ! $brand) {
            return ['source_id' => 0, 'offers' => 0, 'categories' => 0, 'clients' => 0];
        }

        return DB::transaction(function () use ($supplierId, $brand): array {
            $source = IntegrationSource::query()->firstOrNew(['code' => self::SOURCE_CODE]);
            $source->fill([
                'supplier_id' => $supplierId,
                'name' => 'ЭкоКамин · склад Москва',
                'driver' => 'warehouse',
                'price_currency' => 'BYN',
                'price_currency_rate' => 1,
                'is_active' => true,
                'create_products' => false,
                'update_prices' => true,
                'update_stock' => true,
            ]);
            $source->settings = array_merge($source->settings ?? [], [
                'b2b_enabled' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'partner_name' => 'ООО «СанБизнесГруп»',
                'warehouse_label' => 'Склад Москва',
                'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                'vat_rate' => 20,
                'stock_validity_minutes' => 20160,
                'zero_missing_stock_on_complete' => true,
                'allow_order_export' => false,
                'order_pricing_types' => ['b2b'],
            ]);
            $source->save();

            $stocks = ProductWarehouseStock::query()
                ->where('supplier_id', $supplierId)
                ->where('warehouse_code', 'ecokamin-moscow')
                ->where('available_quantity', '>', 0)
                ->whereHas('product', fn ($query) => $query
                    ->where('brand_id', $brand->id)
                    ->where('is_active', true)
                    ->where('is_archived', false)
                    ->where('price', '>', 0))
                ->with('product:id,category_id,brand_id,name,sku,price')
                ->get()
                ->groupBy('product_id');

            $productIds = [];
            $categoryIds = [];
            foreach ($stocks as $productId => $items) {
                $product = $items->first()->product;
                if (! $product) {
                    continue;
                }

                $quantity = round((float) $items->sum('available_quantity'), 3);
                $confirmedAt = $items->max('last_synced_at') ?: now();
                IntegrationProduct::query()->updateOrCreate(
                    [
                        'integration_source_id' => $source->id,
                        'external_id' => 'ecokamin-moscow-'.$product->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'external_sku' => $items->pluck('external_article')->filter()->first() ?: $product->sku,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'price_currency' => 'BYN',
                        'price_currency_rate' => 1,
                        'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                        'price_vat_rate' => 20,
                        'price_byn' => (float) $product->price,
                        'stock_quantity' => $quantity,
                        'match_status' => 'matched',
                        'b2b_enabled' => true,
                        'match_method' => 'ecokamin_warehouse_stock',
                        'match_confidence' => 1,
                        'payload' => ['warehouse_code' => 'ecokamin-moscow'],
                        'matched_at' => now(),
                        'last_seen_at' => $confirmedAt,
                        'last_offer_seen_at' => $confirmedAt,
                        'stock_confirmed_at' => $confirmedAt,
                    ],
                );
                $productIds[] = (int) $product->id;
                $categoryIds[] = (int) $product->category_id;
            }

            IntegrationProduct::query()
                ->where('integration_source_id', $source->id)
                ->when($productIds !== [], fn ($query) => $query->whereNotIn('product_id', $productIds))
                ->update([
                    'stock_quantity' => 0,
                    'b2b_enabled' => false,
                    'stock_confirmed_at' => now(),
                    'updated_at' => now(),
                ]);

            $categoryIds = collect($categoryIds)->filter()->unique()->sort()->values()->all();
            $settings = $source->settings;
            $settings['b2b_category_ids'] = $categoryIds;
            $source->update(['settings' => $settings]);

            $oneCId = IntegrationSource::query()->where('code', 'onec')->value('id');
            $updatedClients = 0;
            if ($oneCId) {
                B2bClient::query()->where('status', 'approved')->get()->each(
                    function (B2bClient $client) use ($oneCId, $source, &$updatedClients): void {
                        $allowed = collect($client->allowed_integration_source_ids ?? [])->map(fn ($id): int => (int) $id);
                        if (! $allowed->contains((int) $oneCId) || $allowed->contains((int) $source->id)) {
                            return;
                        }
                        $client->update([
                            'allowed_integration_source_ids' => $allowed->push((int) $source->id)->unique()->values()->all(),
                        ]);
                        $updatedClients++;
                    }
                );
            }

            return [
                'source_id' => (int) $source->id,
                'offers' => count($productIds),
                'categories' => count($categoryIds),
                'clients' => $updatedClients,
            ];
        });
    }
}

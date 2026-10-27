<?php

namespace App\Services;

use App\Models\B2bClient;
use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class TsarPechiB2bOfferPublisher
{
    public const SOURCE_CODE = 'tsar_pechi_sbg';

    /** @return array{source_id:int,offers:int,categories:int,clients:int} */
    public function sync(): array
    {
        $supplier = Supplier::query()
            ->where(fn ($query) => $query
                ->where('code', 'sanbusinessgroup')
                ->orWhere('name', 'like', '%СанБизнесГруп%'))
            ->orderByRaw("CASE WHEN code = 'sanbusinessgroup' THEN 0 ELSE 1 END")
            ->first();
        $brand = Brand::query()
            ->where(fn ($query) => $query
                ->where('slug', 'tsar-pechi')
                ->orWhereIn('name', ['Царь-Печи', 'Царь Печи']))
            ->orderByRaw("CASE WHEN slug = 'tsar-pechi' THEN 0 ELSE 1 END")
            ->first();

        if (! $supplier || ! $brand) {
            return ['source_id' => 0, 'offers' => 0, 'categories' => 0, 'clients' => 0];
        }

        return DB::transaction(function () use ($supplier, $brand): array {
            $source = IntegrationSource::query()->firstOrNew(['code' => self::SOURCE_CODE]);
            $source->fill([
                'supplier_id' => $supplier->id,
                'name' => 'Царь-Печи · под заказ',
                'driver' => 'catalog',
                'price_currency' => 'BYN',
                'price_currency_rate' => 1,
                'is_active' => true,
                'create_products' => false,
                'update_prices' => true,
                'update_stock' => false,
            ]);
            $source->settings = array_merge($source->settings ?? [], [
                'b2b_enabled' => true,
                'procurement_mode' => true,
                'b2b_price_role' => IntegrationSource::B2B_PRICE_ROLE_SALE,
                'partner_name' => 'ООО «СанБизнесГруп»',
                'warehouse_label' => 'Под заказ у СанБизнесГруп',
                'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                'vat_rate' => 20,
                'allow_order_export' => false,
                'order_pricing_types' => ['b2b'],
            ]);
            $source->save();

            $products = Product::query()
                ->where('brand_id', $brand->id)
                ->where('is_active', true)
                ->where('is_archived', false)
                ->where('price', '>', 0)
                ->whereHas('supplierProducts', fn ($query) => $query
                    ->where('supplier_id', $supplier->id)
                    ->whereNotNull('product_id'))
                ->get(['id', 'category_id', 'name', 'sku', 'price']);

            $productIds = [];
            $categoryIds = [];
            foreach ($products as $product) {
                IntegrationProduct::query()->updateOrCreate(
                    [
                        'integration_source_id' => $source->id,
                        'external_id' => 'tsar-pechi-sbg-'.$product->id,
                    ],
                    [
                        'product_id' => $product->id,
                        'external_sku' => $product->sku,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                        'price_currency' => 'BYN',
                        'price_currency_rate' => 1,
                        'price_tax_mode' => IntegrationSource::PRICE_TAX_INCLUSIVE,
                        'price_vat_rate' => 20,
                        'price_byn' => (float) $product->price,
                        'stock_quantity' => 0,
                        'match_status' => 'matched',
                        'b2b_enabled' => true,
                        'match_method' => 'tsar_pechi_verified_sbg_retail',
                        'match_confidence' => 1,
                        'payload' => [
                            'supplier' => 'ООО «СанБизнесГруп»',
                            'procurement_mode' => true,
                            'b2b_discount_percent' => 20,
                        ],
                        'matched_at' => now(),
                        'last_seen_at' => now(),
                        'last_offer_seen_at' => now(),
                        'stock_confirmed_at' => null,
                    ],
                );
                $productIds[] = (int) $product->id;
                $categoryIds[] = (int) $product->category_id;
            }

            IntegrationProduct::query()
                ->where('integration_source_id', $source->id)
                ->when($productIds !== [], fn ($query) => $query->whereNotIn('product_id', $productIds))
                ->update(['b2b_enabled' => false, 'updated_at' => now()]);

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

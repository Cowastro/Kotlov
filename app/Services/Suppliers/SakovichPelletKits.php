<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

class SakovichPelletKits
{
    public const OPTIONS = [
        'KOTLOV-023095' => ['model' => 'Standart 35 кВт', 'parts' => 'Горелка Praktik и топливный бункер'],
        'KOTLOV-000320' => ['model' => 'Standart 35 кВт', 'parts' => 'Сталь 4 мм · KIPI Rotary 8–36 кВт · бункер 450 л'],
        'KOTLOV-000301' => ['model' => 'Max 50 кВт', 'parts' => 'Сталь 6 мм · KIPI Rotary 10–50 кВт · бункер 700 л'],
    ];

    private const COMPONENTS = [
        'KOTLOV-000320' => ['PELLET-STANDART-4MM-35', 'ACCESSORY-C7187E744C1A39B35E49', 'ACCESSORY-847FD749AAEF346FA509'],
        'KOTLOV-000301' => ['PELLET-MAX-50', 'ACCESSORY-4AB9E8438C072C37FE93', 'ACCESSORY-62C9461F435F4DFAE822'],
    ];

    public function refresh(Supplier $supplier): void
    {
        $components = $supplier->supplierProducts()->whereIn('supplier_article_normalized', array_merge(...array_values(self::COMPONENTS)))
            ->get()->keyBy('supplier_article_normalized');

        foreach (self::COMPONENTS as $sku => $articles) {
            $product = Product::where('sku', $sku)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }

            $rows = $components->only($articles);
            if ($rows->count() !== count($articles) || $rows->contains(fn ($row) => $row->currency !== 'BYN'
                || (float) $row->price_byn <= 0 || (float) data_get($row->raw, 'retail') <= 0 || $row->stock_status === 'missing_from_feed')) {
                $product->update(['requires_price_confirmation' => true]);

                continue;
            }

            $purchase = $rows->sum('price_byn');
            $retail = $rows->sum(fn ($row) => (float) $row->raw['retail']);
            $key = $articles[0].'-KIPI-BUNDLE';
            SupplierProduct::updateOrCreate([
                'supplier_id' => $supplier->id, 'supplier_article_normalized' => $key,
            ], [
                'product_id' => $product->id, 'product_sku' => $sku,
                'supplier_article' => 'SANTEHSKLAD-'.$key, 'supplier_name' => $product->name,
                'price' => $purchase, 'price_byn' => $purchase, 'currency' => 'BYN', 'currency_rate' => 1,
                'match_status' => 'matched', 'match_confidence' => 'manual_price_list',
                'stock_status' => 'unknown', 'in_stock' => false, 'stock_quantity' => null,
                'stock_text' => 'Наличие комплекта уточняется у поставщика',
                'warehouse_name' => 'Сантехсклад', 'last_synced_at' => now(),
                'raw' => ['bundle' => true, 'components' => $articles, 'retail' => $retail],
            ]);
            $product->update(['price' => $retail, 'requires_price_confirmation' => false]);
        }
    }
}

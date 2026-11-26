<?php

namespace App\Services\Suppliers;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;

class SakovichPelletKits
{
    public const OPTIONS = [
        'KOTLOV-023095' => ['model' => 'Standart', 'power_kw' => 35, 'burner' => 'Praktik', 'hopper_l' => null],
        'KOTLOV-000323' => ['model' => 'Standart', 'power_kw' => 15, 'burner' => 'KIPI Rotary', 'hopper_l' => 180, 'steel_mm' => 4],
        'KOTLOV-000322' => ['model' => 'Standart', 'power_kw' => 20, 'burner' => 'KIPI Rotary', 'hopper_l' => 240, 'steel_mm' => 4],
        'KOTLOV-000321' => ['model' => 'Standart', 'power_kw' => 25, 'burner' => 'KIPI Rotary', 'hopper_l' => 300, 'steel_mm' => 4],
        'KOTLOV-000320' => ['model' => 'Standart', 'power_kw' => 35, 'burner' => 'KIPI Rotary 8–36 кВт', 'hopper_l' => 450, 'steel_mm' => 4],
        'KOTLOV-000301' => ['model' => 'Max', 'power_kw' => 50, 'burner' => 'KIPI Rotary 10–50 кВт', 'hopper_l' => 700, 'steel_mm' => 6],
        'KOTLOV-000303' => ['model' => 'Max', 'power_kw' => 75, 'burner' => 'KIPI Rotary', 'hopper_l' => 1000, 'steel_mm' => 6],
        'KOTLOV-000289' => ['model' => 'Max', 'power_kw' => 99, 'burner' => 'KIPI Rotary 20–100 кВт', 'hopper_l' => 1000, 'steel_mm' => 6],
        'KOTLOV-000318' => ['model' => 'Max', 'power_kw' => 150, 'burner' => 'KIPI Rotary 30–150 кВт', 'hopper_l' => 1500, 'steel_mm' => 6],
        'KOTLOV-000299' => ['model' => 'Pellet Universal', 'power_kw' => 16, 'burner' => 'KIPI Rotary с автоматикой Standart', 'hopper_l' => 190],
        'KOTLOV-000313' => ['model' => 'Pellet Universal', 'power_kw' => 20, 'burner' => 'KIPI Rotary с автоматикой Standart', 'hopper_l' => 230],
        'KOTLOV-000316' => ['model' => 'Pellet Universal', 'power_kw' => 26, 'burner' => 'KIPI Rotary с автоматикой Standart', 'hopper_l' => 300],
        'KOTLOV-000315' => ['model' => 'Pellet Universal', 'power_kw' => 36, 'burner' => 'KIPI Rotary с автоматикой Standart', 'hopper_l' => 400],
        'KOTLOV-000314' => ['model' => 'Pellet Universal', 'power_kw' => 50, 'burner' => 'KIPI Rotary с автоматикой Standart', 'hopper_l' => 550],
    ];

    private const COMPONENTS = [
        'KOTLOV-000320' => ['PELLET-STANDART-4MM-35', 'ACCESSORY-C7187E744C1A39B35E49', 'ACCESSORY-847FD749AAEF346FA509'],
        'KOTLOV-000301' => ['PELLET-MAX-50', 'ACCESSORY-4AB9E8438C072C37FE93', 'ACCESSORY-62C9461F435F4DFAE822'],
        'KOTLOV-000289' => ['PELLET-MAX-99', 'ACCESSORY-E51F2B971EEBDB9A0951', 'ACCESSORY-53670881B403DB332F96'],
        'KOTLOV-000318' => ['PELLET-MAX-150', 'ACCESSORY-46C0E42CEB1CF40E2878', 'ACCESSORY-D95A466EF6C853F53547'],
    ];

    public function refresh(Supplier $supplier, ?array $importedArticles = null): void
    {
        $components = $supplier->supplierProducts()->whereIn('supplier_article_normalized', array_merge(...array_values(self::COMPONENTS)))
            ->get()->toBase()->keyBy('supplier_article_normalized');

        foreach (self::COMPONENTS as $sku => $articles) {
            $product = Product::where('sku', $sku)->lockForUpdate()->first();
            if (! $product) {
                continue;
            }

            $rows = $components->only($articles);
            if (($importedArticles !== null && array_diff($articles, $importedArticles))
                || $rows->count() !== count($articles) || $rows->contains(fn ($row) => $row->currency !== 'BYN'
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

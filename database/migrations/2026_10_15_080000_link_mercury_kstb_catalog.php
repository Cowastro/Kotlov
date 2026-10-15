<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'mercury';

    private const CATALOG_URL = 'https://pkmercury.by/catalog/kotly-kstb/';

    /**
     * The manufacturer publishes a starting retail price ("от") for every
     * configuration of the same output. It is not a confirmed procurement
     * price, so the storefront keeps the manager-confirmation flag enabled.
     *
     * @var array<string, array{article: string, name: string, url: string, price: float}>
     */
    private const PRODUCTS = [
        'PS-011.540' => [
            'article' => 'КСТБ-95',
            'name' => 'Твердотопливный котел КСТБ-95',
            'url' => 'https://pkmercury.by/product/kotel-kstb-95/',
            'price' => 8490.00,
        ],
        'PS-011.541' => [
            'article' => 'КСТБ-75',
            'name' => 'Твердотопливный котел КСТБ-75',
            'url' => 'https://pkmercury.by/product/kotel-kstb-75/',
            'price' => 7290.00,
        ],
        'PS-011.542' => [
            'article' => 'КСТБ-50',
            'name' => 'Твердотопливный котел КСТБ-50',
            'url' => 'https://pkmercury.by/product/kotel-kstb-50/',
            'price' => 5640.00,
        ],
        'PS-011.543' => [
            'article' => 'КСТБ-30',
            'name' => 'Твердотопливный котел КСТБ-30',
            'url' => 'https://pkmercury.by/product/kotel-kstb-30/',
            'price' => 3660.00,
        ],
        'PS-011.544' => [
            'article' => 'КСТБ 30-1Т',
            'name' => 'Твердотопливный котел КСТБ 30-1Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-30-1t/',
            'price' => 3660.00,
        ],
        'PS-011.545' => [
            'article' => 'КСТБ 30-1ТА',
            'name' => 'Твердотопливный котел КСТБ 30-1ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-30-1ta/',
            'price' => 3660.00,
        ],
        'PS-011.546' => [
            'article' => 'КСТБ 30-2Т',
            'name' => 'Твердотопливный котел КСТБ 30-2Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-30-2t/',
            'price' => 3660.00,
        ],
        'PS-011.547' => [
            'article' => 'КСТБ 50-1Т',
            'name' => 'Твердотопливный котел КСТБ 50-1Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-50-1t/',
            'price' => 5640.00,
        ],
        'PS-011.548' => [
            'article' => 'КСТБ 50-1ТА',
            'name' => 'Твердотопливный котел КСТБ 50-1ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-50-1ta/',
            'price' => 5640.00,
        ],
        'PS-011.549' => [
            'article' => 'КСТБ 50-2ТА',
            'name' => 'Твердотопливный котел КСТБ 50-2ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-50-2ta/',
            'price' => 5640.00,
        ],
        'PS-011.550' => [
            'article' => 'КСТБ 75-1Т',
            'name' => 'Твердотопливный котел КСТБ 75-1Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-75-1t/',
            'price' => 7290.00,
        ],
        'PS-011.551' => [
            'article' => 'КСТБ 75-1ТА',
            'name' => 'Твердотопливный котел КСТБ 75-1ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-75-1ta/',
            'price' => 7290.00,
        ],
        'PS-011.552' => [
            'article' => 'КСТБ 75-2ТА',
            'name' => 'Твердотопливный котел КСТБ 75-2ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-75-2ta/',
            'price' => 7290.00,
        ],
        'PS-011.553' => [
            'article' => 'КСТБ 75-2Т',
            'name' => 'Твердотопливный котел КСТБ 75-2Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-75-2t/',
            'price' => 7290.00,
        ],
        'PS-011.554' => [
            'article' => 'КСТБ 95-1Т',
            'name' => 'Твердотопливный котел КСТБ 95-1Т',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-95-1t/',
            'price' => 8490.00,
        ],
        'PS-011.555' => [
            'article' => 'КСТБ 95-1ТА',
            'name' => 'Твердотопливный котел КСТБ 95-1ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-95-1ta/',
            'price' => 8490.00,
        ],
        'PS-011.556' => [
            'article' => 'КСТБ 95-2ТА',
            'name' => 'Твердотопливный котел КСТБ 95-2ТА',
            'url' => 'https://pkmercury.by/product/tverdotoplivnyj-kotel-kstb-95-2ta/',
            'price' => 8490.00,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')
            || ! Schema::hasTable('suppliers')
            || ! Schema::hasTable('supplier_products')) {
            return;
        }

        if (! DB::table('products')->whereIn('sku', array_keys(self::PRODUCTS))->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $supplierId = $this->ensureSupplier($now);

            $this->ensureSupplierSource($supplierId, $now);
            $this->linkBrand($supplierId, $now);

            foreach (self::PRODUCTS as $sku => $item) {
                $product = DB::table('products')->where('sku', $sku)->first();

                if (! $product) {
                    continue;
                }

                DB::table('products')->where('id', $product->id)->update([
                    'name' => $item['name'],
                    'h1' => $item['name'],
                    'price' => $item['price'],
                    'currency' => 'BYN',
                    'content_source_url' => $item['url'],
                    'in_stock' => false,
                    'stock_qty' => 0,
                    'availability_status' => Product::AVAILABILITY_CHECK,
                    'requires_price_confirmation' => true,
                    'updated_at' => $now,
                ]);

                $normalized = $this->normalizeArticle($item['article']);
                $values = [
                    'product_id' => $product->id,
                    'product_sku' => $sku,
                    'supplier_article' => $item['article'],
                    'supplier_article_normalized' => $normalized,
                    'supplier_article_compact' => $normalized,
                    'supplier_name' => $item['name'],
                    'source_url' => $item['url'],
                    'price' => $item['price'],
                    'currency' => 'BYN',
                    'currency_rate' => 1,
                    'price_byn' => $item['price'],
                    'in_stock' => false,
                    'stock_quantity' => null,
                    'stock_status' => 'unknown',
                    'stock_text' => 'Наличие уточнять у производителя',
                    'match_status' => 'matched',
                    'match_confidence' => '100',
                    'raw' => json_encode([
                        'source' => 'official_manufacturer_catalog',
                        'published_price_type' => 'minimum_retail',
                        'published_price_label' => 'от',
                        'price_is_supplier_cost' => false,
                        'checked_at' => '2026-10-15',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'last_synced_at' => $now,
                    'updated_at' => $now,
                ];

                $existing = DB::table('supplier_products')
                    ->where('supplier_id', $supplierId)
                    ->where(function ($query) use ($product, $normalized): void {
                        $query->where('product_id', $product->id)
                            ->orWhere('supplier_article_normalized', $normalized);
                    })
                    ->first();

                if ($existing) {
                    DB::table('supplier_products')->where('id', $existing->id)->update($values);
                } else {
                    DB::table('supplier_products')->insert($values + [
                        'supplier_id' => $supplierId,
                        'created_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Supplier mappings and verified catalogue facts are intentionally durable.
    }

    private function ensureSupplier($now): int
    {
        $supplier = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();
        $values = [
            'name' => 'ООО Производственная компания «Меркурий»',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => '+375 (29) 640-09-59 · info@pkmercury.by · '.self::CATALOG_URL,
            'notes' => 'Официальный каталог КСТБ публикует розничные цены с пометкой «от»; закупочную цену и наличие нужно подтверждать.',
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('suppliers', 'communication_channels')) {
            $values['communication_channels'] = json_encode([
                ['type' => 'phone', 'value' => '+375296400959'],
                ['type' => 'email', 'value' => 'info@pkmercury.by'],
                ['type' => 'website', 'value' => 'https://pkmercury.by/'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($supplier) {
            DB::table('suppliers')->where('id', $supplier->id)->update($values);

            return (int) $supplier->id;
        }

        return (int) DB::table('suppliers')->insertGetId($values + [
            'code' => self::SUPPLIER_CODE,
            'created_at' => $now,
        ]);
    }

    private function ensureSupplierSource(int $supplierId, $now): void
    {
        if (! Schema::hasTable('supplier_sources')) {
            return;
        }

        DB::table('supplier_sources')->updateOrInsert(
            [
                'supplier_id' => $supplierId,
                'name' => 'Официальный каталог КСТБ',
            ],
            [
                'type' => 'website',
                'config' => json_encode([
                    'url' => self::CATALOG_URL,
                    'price_type' => 'minimum_retail',
                    'price_label' => 'от',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'schedule_cron' => 'Проверять вручную перед продажей',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    private function linkBrand(int $supplierId, $now): void
    {
        if (! Schema::hasTable('brand_supplier')) {
            return;
        }

        $brandId = DB::table('brands')
            ->where('name', 'Меркурий')
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower('Меркурий')])
            ->value('id');

        if (! $brandId) {
            return;
        }

        DB::table('brand_supplier')->insertOrIgnore([
            'brand_id' => $brandId,
            'supplier_id' => $supplierId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function normalizeArticle(string $article): string
    {
        return (string) preg_replace('/[^A-Z0-9А-ЯЁ]+/u', '', mb_strtoupper($article));
    }
};

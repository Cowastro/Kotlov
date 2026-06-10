<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SUPPLIER_CODE = 'elicon';
    private const CATEGORY_ID = 96;
    private const BRAND_ID = 112;

    public function up(): void
    {
        $now = now();

        $supplierId = $this->ensureSupplier($now);
        $this->ensureBrand($now);
        $this->ensureCategory($now);

        foreach ($this->items() as $item) {
            $product = $this->findProduct($item);
            $price = $item['price'];
            $inStock = $price !== null;

            $payload = [
                'category_id' => self::CATEGORY_ID,
                'brand_id' => self::BRAND_ID,
                'supplier_id' => null,
                'name' => $item['name'],
                'h1' => $item['name'],
                'price' => $price ?? 0,
                'price_old' => null,
                'currency' => 'BYN',
                'content' => 'Актуализировано по каталогу elicon.by, категория "Счетчики газа", 10.06.2026.',
                'short_description' => sprintf('Артикул поставщика Эликон: %s.', $item['article']),
                'images' => json_encode([], JSON_UNESCAPED_UNICODE),
                'specs' => json_encode($this->makeSpecs($item), JSON_UNESCAPED_UNICODE),
                'video_url' => null,
                'weight' => null,
                'unit' => 'шт',
                'warranty' => null,
                'is_active' => true,
                'is_archived' => false,
                'in_stock' => $inStock,
                'stock_qty' => null,
                'is_featured' => false,
                'is_new' => false,
                'is_sale' => false,
                'sort_order' => 0,
                'meta_title' => $item['name'] . ' купить в Минске',
                'meta_keywords' => 'счетчик газа, БелОМО, Эликон, ' . $item['article'],
                'meta_description' => $item['name'] . '. Актуальная цена: ' . ($price !== null ? number_format($price, 2, ',', ' ') . ' BYN.' : 'уточняйте наличие.'),
                'rating' => 0,
                'reviews_count' => 0,
                'views_count' => 0,
                'updated_at' => $now,
            ];

            if ($product) {
                DB::table('products')->where('id', $product->id)->update($payload);
                $productId = $product->id;
                $productSku = $product->sku;
            } else {
                $productSku = 'ELICON-' . $item['article'];
                $payload['sku'] = $productSku;
                $payload['slug'] = $this->uniqueSlug($item['name']);
                $payload['created_at'] = $now;
                $productId = DB::table('products')->insertGetId($payload);
            }

            $this->upsertMapping($item, $productId, $productSku, $now);
        }
    }

    public function down(): void
    {
        DB::table('supplier_product_mappings')
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->delete();

        DB::table('products')
            ->where('sku', 'like', 'ELICON-%')
            ->delete();
    }

    private function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->first();

        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => 'Эликон',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => 'https://elicon.by/product-category/bitovie_schetchiki_gaza/',
                'notes' => 'Каталог бытовых счетчиков газа БелОМО.',
                'is_active' => true,
                'updated_at' => $now,
            ]);

            return $existing->id;
        }

        return DB::table('suppliers')->insertGetId([
            'code' => self::SUPPLIER_CODE,
            'name' => 'Эликон',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => 'https://elicon.by/product-category/bitovie_schetchiki_gaza/',
            'notes' => 'Каталог бытовых счетчиков газа БелОМО.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureBrand($now): void
    {
        $payload = [
            'name' => 'БелОМО',
            'slug' => 'belomo',
            'h1' => 'БелОМО',
            'country' => 'Беларусь',
            'producer' => 'ОАО "ММЗ имени С.И. Вавилова - управляющая компания холдинга БелОМО"',
            'is_active' => true,
            'updated_at' => $now,
        ];

        $existing = DB::table('brands')->where('id', self::BRAND_ID)->orWhere('slug', 'belomo')->first();

        if ($existing) {
            DB::table('brands')->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table('brands')->insert($payload + [
            'id' => self::BRAND_ID,
            'created_at' => $now,
        ]);
    }

    private function ensureCategory($now): void
    {
        $payload = [
            'parent_id' => 301,
            'name' => 'Счетчики газа',
            'slug' => 'schetchiki-gaza',
            'h1' => 'Счетчики газа',
            'type' => 'child',
            'sort_order' => 160,
            'is_active' => true,
            'updated_at' => $now,
        ];

        $existing = DB::table('categories')->where('id', self::CATEGORY_ID)->orWhere('slug', 'schetchiki-gaza')->first();

        if ($existing) {
            DB::table('categories')->where('id', $existing->id)->update($payload);
            return;
        }

        DB::table('categories')->insert($payload + [
            'id' => self::CATEGORY_ID,
            'created_at' => $now,
        ]);
    }

    private function findProduct(array $item): ?object
    {
        $mapping = DB::table('supplier_product_mappings')
            ->where('supplier_code', self::SUPPLIER_CODE)
            ->where('supplier_article', $item['article'])
            ->whereNotNull('product_id')
            ->first();

        if ($mapping) {
            $product = DB::table('products')->where('id', $mapping->product_id)->first();
            if ($product) {
                return $product;
            }
        }

        $legacySku = array_flip($this->legacySkuToArticle())[$item['article']] ?? null;
        if ($legacySku) {
            $product = DB::table('products')->where('sku', $legacySku)->first();
            if ($product) {
                return $product;
            }
        }

        return DB::table('products')->where('slug', Str::slug($item['name']))->first();
    }

    private function upsertMapping(array $item, int $productId, ?string $productSku, $now): void
    {
        DB::table('supplier_product_mappings')->updateOrInsert(
            [
                'supplier_code' => self::SUPPLIER_CODE,
                'supplier_article' => $item['article'],
            ],
            [
                'product_id' => $productId,
                'product_sku' => $productSku,
                'supplier_name' => $item['name'],
                'confidence' => 'manual',
                'is_active' => true,
                'notes' => sprintf('elicon.by wp_id=%s, scraped 2026-06-10', $item['wp_id']),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'elicon-gas-meter';
        }

        $slug = $base;
        $i = 2;
        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function makeSpecs(array $item): array
    {
        $name = $item['name'];

        preg_match('/\bG\s*([0-9]+(?:[,.][0-9]+)?)/u', $name, $gMatch);
        preg_match('/L\s*=\s*([0-9]+)/u', $name, $lengthMatch);
        preg_match('/\((левый|правый)\)/ui', $name, $sideMatch);
        preg_match('/(СГД-3Т|СГД\s*4|СГМН|СКАТ|ВЕГА|КАТА)/ui', $name, $seriesMatch);

        return array_filter([
            'supplier' => 'Эликон',
            'supplier_article' => $item['article'],
            'source_url' => 'https://elicon.by/product-category/bitovie_schetchiki_gaza/',
            'source_wp_id' => $item['wp_id'],
            'type' => str_contains(mb_strtolower($name), 'ультразвуковой') ? 'ультразвуковой' : 'диафрагменный',
            'series' => $seriesMatch[1] ?? null,
            'g_size' => isset($gMatch[1]) ? 'G' . str_replace(',', '.', $gMatch[1]) : null,
            'side' => $sideMatch[1] ?? null,
            'connection_length_mm' => isset($lengthMatch[1]) ? (int) $lengthMatch[1] : null,
            'thermal_compensation' => str_contains($name, 'термокомпенсатором'),
            'price_scraped_at' => '2026-06-10',
        ], fn($value) => $value !== null);
    }

    private function legacySkuToArticle(): array
    {
        return [
            'PS-002.021' => '8181-20',
            'PS-002.022' => '8181-00',
            'PS-007.727' => '8181-21',
            'PS-007.728' => '8181-01',
            'PS-007.759' => '8181-04',
            'PS-007.760' => '8181-05',
            'PS-007.761' => '8181-22',
            'PS-007.762' => '8181-23',
        ];
    }

    private function items(): array
    {
        return [
            ['wp_id' => '1157', 'article' => '8181-00', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1-1 G6 (левый) L=200', 'price' => 172.38],
            ['wp_id' => '1159', 'article' => '8181-20', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1-1-G4 (левый) L=200', 'price' => 232.96],
            ['wp_id' => '1158', 'article' => '8181-01', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1-2 G6 (правый) L=200', 'price' => 187.85],
            ['wp_id' => '916', 'article' => '8181-21', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1-2-G4 (правый) L=200', 'price' => 181.70],
            ['wp_id' => '911', 'article' => '8181-22', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1И-1-G4 (левый) L=200', 'price' => null],
            ['wp_id' => '907', 'article' => '8181-04', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1И-1-G6 (левый) L=200', 'price' => 187.65],
            ['wp_id' => '944', 'article' => '8181-23', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1И-2- G4 (правый) L=200', 'price' => 195.17],
            ['wp_id' => '903', 'article' => '8181-05', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1И-2- G6 (правый) L=200', 'price' => 187.65],
            ['wp_id' => '1218', 'article' => '8181-10', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-2-1 G6 (левый) L=250', 'price' => 187.03],
            ['wp_id' => '1161', 'article' => '8181-11', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-2-2 G6 (правый) L=250', 'price' => 187.03],
            ['wp_id' => '1160', 'article' => '8181-12', 'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-2И-1 G6 (левый) L=250', 'price' => 203.73],
            ['wp_id' => '1878', 'article' => '8336-50', 'name' => 'Счетчик газа диафрагменный СГД 4-3-1 G2,5 L=110 (левый)', 'price' => null],
            ['wp_id' => '1221', 'article' => '8336-94', 'name' => 'Счетчик газа диафрагменный СГД 4-3-1 G2,5 И L=110 (левый)', 'price' => null],
            ['wp_id' => '925', 'article' => '8336-48', 'name' => 'Счетчик газа диафрагменный СГД 4-3-1-G4ТИ (левый) L=110 (без манжет)', 'price' => null],
            ['wp_id' => '1331', 'article' => '8336-07', 'name' => 'Счетчик газа диафрагменный СГД 4-3-2-G4ТИ (правый) L=110', 'price' => 173.36],
            ['wp_id' => '1228', 'article' => '1009-00', 'name' => 'Счетчик газа диафрагменный СГМН-1-1-1-G6 (левый) L=250', 'price' => null],
            ['wp_id' => '947', 'article' => '1009-04', 'name' => 'Счетчик газа диафрагменный СГМН-1-2-1-G6 (левый) L=200', 'price' => 162.66],
            ['wp_id' => '1152', 'article' => '1009-06', 'name' => 'Счетчик газа диафрагменный СГМН-1-2-2-G6 (правый) L=200', 'price' => 162.66],
            ['wp_id' => '929', 'article' => '8072-20', 'name' => 'Счетчик газа СГД-1-2-1-G1,6 (правый) L=110', 'price' => 180.51],
            ['wp_id' => '920', 'article' => '8072-22', 'name' => 'Счетчик газа СГД-1-2-1-G2,5 (правый) L=110', 'price' => 180.51],
            ['wp_id' => '923', 'article' => '8072-21', 'name' => 'Счетчик газа СГД-1-2-2-G1,6 (левый) L=110', 'price' => 180.51],
            ['wp_id' => '1167', 'article' => '8072-23', 'name' => 'Счетчик газа СГД-1-2-2-G2,5 (левый) L=110', 'price' => 180.51],
            ['wp_id' => '4588', 'article' => '8349-10', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G10', 'price' => 542.34],
            ['wp_id' => '4303', 'article' => '8349000000012', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G10 R', 'price' => null],
            ['wp_id' => '6023', 'article' => '8349000000016', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G10 RP', 'price' => null],
            ['wp_id' => '4305', 'article' => '8349000000011', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G10 В', 'price' => 574.08],
            ['wp_id' => '6021', 'article' => '8349000000001', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G6 B', 'price' => 572.70],
            ['wp_id' => '6016', 'article' => '8349000000004', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G6 P', 'price' => 695.52],
            ['wp_id' => '4304', 'article' => '8349000000002', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G6 R', 'price' => null],
            ['wp_id' => '5719', 'article' => '8349000000007', 'name' => 'Счетчик газа ультразвуковой "СКАТ"- G6 RKP', 'price' => 1134.36],
            ['wp_id' => '1162', 'article' => '8345-00', 'name' => 'Счетчик газа ультразвуковой ВЕГА G1.6', 'price' => null],
            ['wp_id' => '1164', 'article' => '8345-04', 'name' => 'Счетчик газа ультразвуковой ВЕГА G1.6 В', 'price' => 222.18],
            ['wp_id' => '1698', 'article' => '8345-01', 'name' => 'Счетчик газа ультразвуковой ВЕГА G2.5', 'price' => 209.76],
            ['wp_id' => '3857', 'article' => '8348-30', 'name' => 'Счетчик газа ультразвуковой КАТА - G6 R-3', 'price' => 651.36],
            ['wp_id' => '3165', 'article' => '8348000000023', 'name' => 'Счетчик газа ультразвуковой КАТА - G6 RК-1', 'price' => 822.48],
            ['wp_id' => '3159', 'article' => '8348.00.00.000-09', 'name' => 'Счетчик газа ультразвуковой КАТА G4 B-3', 'price' => 449.88],
            ['wp_id' => '6032', 'article' => '8348000000008', 'name' => 'Счетчик газа ультразвуковой КАТА G4-3', 'price' => 433.32],
            ['wp_id' => '3863', 'article' => '8348-01', 'name' => 'Счетчик газа ультразвуковой КАТА-G4 В-1', 'price' => 444.36],
            ['wp_id' => '6041', 'article' => '8348000000005', 'name' => 'Счетчик газа ультразвуковой КАТА-G4 В-2', 'price' => 445.74],
            ['wp_id' => '3862', 'article' => '8348-00', 'name' => 'Счетчик газа ультразвуковой КАТА-G4-1', 'price' => 430.56],
            ['wp_id' => '3166', 'article' => '8348000000027', 'name' => 'Счетчик газа ультразвуковой КАТА-G6 RK-2', 'price' => 823.86],
            ['wp_id' => '3861', 'article' => '8348-21', 'name' => 'Счетчик газа ультразвуковой КАТА-G6 В-1', 'price' => 451.26],
            ['wp_id' => '6043', 'article' => '8348000000029', 'name' => 'Счетчик газа ультразвуковой КАТА-G6 В-3', 'price' => 454.02],
            ['wp_id' => '6037', 'article' => '8348000000020', 'name' => 'Счетчик газа ультразвуковой КАТА-G6-1', 'price' => 436.08],
            ['wp_id' => '6047', 'article' => '8348000000024', 'name' => 'Счетчик газа ультразвуковой КАТА-G6-2', 'price' => 437.46],
            ['wp_id' => '6045', 'article' => '8348000000028', 'name' => 'Счетчик газа ультразвуковой КАТА-G6-3', 'price' => 438.84],
            ['wp_id' => '3854', 'article' => '8348-02', 'name' => 'Счетчики газа ультразвуковые КАТА-G4 R-1', 'price' => 640.32],
            ['wp_id' => '6039', 'article' => '8348000000010', 'name' => 'Счетчики газа ультразвуковые КАТА-G4 R-3', 'price' => 643.08],
            ['wp_id' => '6034', 'article' => '8348000000003', 'name' => 'Счетчики газа ультразвуковые КАТА-G4 RК-1', 'price' => 818.34],
        ];
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BRAND_NAME = 'KOTLOV PRO';

    private const BRAND_SLUG = 'kotlov-pro';

    /** @var array<string, array{model: string, power: int}> */
    private const PRODUCTS = [
        'PS-011.540' => ['model' => 'КСТБ-95', 'power' => 95],
        'PS-011.541' => ['model' => 'КСТБ-75', 'power' => 75],
        'PS-011.542' => ['model' => 'КСТБ-50', 'power' => 50],
        'PS-011.543' => ['model' => 'КСТБ-30', 'power' => 30],
        'PS-011.544' => ['model' => 'КСТБ 30-1Т', 'power' => 30],
        'PS-011.545' => ['model' => 'КСТБ 30-1ТА', 'power' => 30],
        'PS-011.546' => ['model' => 'КСТБ 30-2Т', 'power' => 30],
        'PS-011.547' => ['model' => 'КСТБ 50-1Т', 'power' => 50],
        'PS-011.548' => ['model' => 'КСТБ 50-1ТА', 'power' => 50],
        'PS-011.549' => ['model' => 'КСТБ 50-2ТА', 'power' => 50],
        'PS-011.550' => ['model' => 'КСТБ 75-1Т', 'power' => 75],
        'PS-011.551' => ['model' => 'КСТБ 75-1ТА', 'power' => 75],
        'PS-011.552' => ['model' => 'КСТБ 75-2ТА', 'power' => 75],
        'PS-011.553' => ['model' => 'КСТБ 75-2Т', 'power' => 75],
        'PS-011.554' => ['model' => 'КСТБ 95-1Т', 'power' => 95],
        'PS-011.555' => ['model' => 'КСТБ 95-1ТА', 'power' => 95],
        'PS-011.556' => ['model' => 'КСТБ 95-2ТА', 'power' => 95],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('brands') || ! Schema::hasTable('products')) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();
            $oldBrandId = DB::table('brands')->where('name', 'Меркурий')->value('id');
            $brandId = $this->ensureBrand($now);

            foreach (self::PRODUCTS as $sku => $item) {
                $product = DB::table('products')->where('sku', $sku)->first();
                if (! $product) {
                    continue;
                }

                $name = 'Твердотопливный котел '.self::BRAND_NAME.' '.$item['model'];
                $shortDescription = "Твердотопливный котел {$item['model']} серии ".self::BRAND_NAME." мощностью {$item['power']} кВт. Инженерный подбор, доставка по Беларуси и монтаж от KOTLOV.BY.";

                DB::table('products')->where('id', $product->id)->update([
                    'brand_id' => $brandId,
                    'name' => $name,
                    'h1' => $name,
                    'short_description' => $shortDescription,
                    'content' => $this->replaceManufacturerName($product->content),
                    'specs' => $this->rebrandSpecs($product->specs),
                    'meta_title' => "{$name} {$item['power']} кВт купить в Беларуси",
                    'meta_keywords' => implode(', ', [
                        self::BRAND_NAME,
                        $item['model'],
                        "твердотопливный котел {$item['power']} кВт",
                        "котел {$item['model']} купить",
                        'котлы KOTLOV.BY',
                    ]),
                    'meta_description' => "{$name} мощностью {$item['power']} кВт. В наличии, актуальная цена, инженерный подбор, доставка и монтаж по Беларуси от KOTLOV.BY.",
                    'updated_at' => $now,
                ]);
            }

            $this->linkBrandToMercurySupplier($brandId, $now);

            if ($oldBrandId && ! DB::table('products')->where('brand_id', $oldBrandId)->exists()) {
                DB::table('brands')->where('id', $oldBrandId)->update([
                    'is_active' => false,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Public branding is intentionally durable; supplier provenance remains in supplier_products.
    }

    private function ensureBrand($now): int
    {
        $brand = DB::table('brands')->where('slug', self::BRAND_SLUG)->first();
        $values = [
            'name' => self::BRAND_NAME,
            'h1' => 'Твердотопливные котлы KOTLOV PRO',
            'country' => 'Беларусь',
            'producer' => 'KOTLOV.BY — инженерная комплектация и сопровождение',
            'content' => '<p><strong>KOTLOV PRO</strong> — профессиональная линейка отопительного оборудования, подобранная инженерами KOTLOV.BY для частных домов, коммерческих и производственных объектов.</p><p>Мы помогаем рассчитать мощность, подобрать обвязку и дымоход, организовать доставку, монтаж и сервисное сопровождение по Беларуси.</p>',
            'meta_title' => 'Котлы KOTLOV PRO — цены, подбор и монтаж в Беларуси',
            'meta_keywords' => 'KOTLOV PRO, котлы KOTLOV, твердотопливные котлы КСТБ, котлы в Беларуси',
            'meta_description' => 'Твердотопливные котлы KOTLOV PRO: модели КСТБ 30–95 кВт, инженерный подбор, актуальные цены, доставка и монтаж по Беларуси.',
            'is_active' => true,
            'is_featured' => true,
            'updated_at' => $now,
        ];

        if ($brand) {
            DB::table('brands')->where('id', $brand->id)->update($values);

            return (int) $brand->id;
        }

        return (int) DB::table('brands')->insertGetId($values + [
            'slug' => self::BRAND_SLUG,
            'created_at' => $now,
        ]);
    }

    private function linkBrandToMercurySupplier(int $brandId, $now): void
    {
        if (! Schema::hasTable('brand_supplier') || ! Schema::hasTable('suppliers')) {
            return;
        }

        $supplierId = DB::table('suppliers')->where('code', 'mercury')->value('id');
        if (! $supplierId) {
            return;
        }

        DB::table('brand_supplier')->insertOrIgnore([
            'brand_id' => $brandId,
            'supplier_id' => $supplierId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function replaceManufacturerName(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return $content;
        }

        return str_ireplace(['Меркурий', 'Mercury'], self::BRAND_NAME, $content);
    }

    private function rebrandSpecs(mixed $specs): string
    {
        $decoded = is_string($specs) ? json_decode($specs, true) : $specs;
        if (! is_array($decoded)) {
            $decoded = [];
        }

        if (array_is_list($decoded)) {
            $found = false;
            foreach ($decoded as &$spec) {
                if (is_array($spec) && mb_strtolower(trim((string) ($spec['key'] ?? ''))) === 'бренд') {
                    $spec['value'] = self::BRAND_NAME;
                    $found = true;
                }
            }
            unset($spec);

            if (! $found) {
                $decoded[] = ['key' => 'Бренд', 'value' => self::BRAND_NAME, 'unit' => ''];
            }
        } else {
            $decoded['Бренд'] = self::BRAND_NAME;
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
};

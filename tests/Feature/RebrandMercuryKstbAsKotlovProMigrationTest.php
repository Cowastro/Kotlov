<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RebrandMercuryKstbAsKotlovProMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_all_mercury_kstb_cards_as_kotlov_pro_without_breaking_supplier_links(): void
    {
        $now = now();
        $mercuryBrandId = (int) DB::table('brands')->where('name', 'Меркурий')->value('id');
        if ($mercuryBrandId === 0) {
            $mercuryBrandId = (int) DB::table('brands')->insertGetId([
                'name' => 'Меркурий',
                'slug' => 'merkuriy-kotlov-pro-test',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Твердотопливные котлы',
            'slug' => 'tverdotoplivnye-kotlov-pro-test',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(540, 556) as $suffix) {
            DB::table('products')->insert([
                'category_id' => $categoryId,
                'brand_id' => $mercuryBrandId,
                'name' => 'Меркурий тест '.$suffix,
                'slug' => 'kotlov-pro-test-'.$suffix,
                'sku' => sprintf('PS-011.%d', $suffix),
                'price' => 1,
                'currency' => 'BYN',
                'content' => '<p>Котел Меркурий для системы отопления.</p>',
                'specs' => json_encode([['key' => 'Мощность', 'value' => '95 кВт', 'unit' => '']], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'in_stock' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        (require database_path('migrations/2026_10_15_080000_link_mercury_kstb_catalog.php'))->up();
        (require database_path('migrations/2026_10_15_100000_apply_mercury_partner_prices_and_default_stock.php'))->up();
        (require database_path('migrations/2026_10_15_110000_rebrand_mercury_kstb_as_kotlov_pro.php'))->up();

        $brand = DB::table('brands')->where('slug', 'kotlov-pro')->firstOrFail();
        $this->assertSame('KOTLOV PRO', $brand->name);
        $this->assertTrue((bool) $brand->is_active);
        $this->assertTrue((bool) $brand->is_featured);

        $this->assertSame(17, DB::table('products')->where('brand_id', $brand->id)->count());
        $this->assertSame(0, DB::table('products')
            ->where('brand_id', $brand->id)
            ->where(function ($query): void {
                $query->where('name', 'like', '%Меркурий%')
                    ->orWhere('h1', 'like', '%Меркурий%')
                    ->orWhere('short_description', 'like', '%Меркурий%')
                    ->orWhere('content', 'like', '%Меркурий%');
            })
            ->count());

        $product = DB::table('products')->where('sku', 'PS-011.554')->firstOrFail();
        $this->assertSame('Твердотопливный котел KOTLOV PRO КСТБ 95-1Т', $product->name);
        $this->assertSame($product->name, $product->h1);
        $this->assertStringContainsString('95 кВт купить в Беларуси', $product->meta_title);
        $this->assertStringContainsString('доставка и монтаж', mb_strtolower($product->meta_description));

        $specs = json_decode((string) $product->specs, true, flags: JSON_THROW_ON_ERROR);
        $brandSpec = collect($specs)->firstWhere('key', 'Бренд');
        $this->assertSame('KOTLOV PRO', $brandSpec['value'] ?? null);

        $supplierId = (int) DB::table('suppliers')->where('code', 'mercury')->value('id');
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplierId,
            'product_id' => $product->id,
            'supplier_name' => 'Твердотопливный котел КСТБ 95-1Т',
            'price_byn' => 7641.00,
        ]);
        $this->assertDatabaseHas('brand_supplier', [
            'brand_id' => $brand->id,
            'supplier_id' => $supplierId,
        ]);
    }
}

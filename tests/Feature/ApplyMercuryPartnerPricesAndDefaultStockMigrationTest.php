<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyMercuryPartnerPricesAndDefaultStockMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_ten_percent_partner_discount_and_default_stock_to_all_mercury_cards(): void
    {
        $now = now();
        $brandId = (int) DB::table('brands')->where('name', 'Меркурий')->value('id');
        if ($brandId === 0) {
            $brandId = (int) DB::table('brands')->insertGetId([
                'name' => 'Меркурий',
                'slug' => 'merkuriy-partner-price-test',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Твердотопливные котлы',
            'slug' => 'tverdotoplivnye-mercury-partner-price-test',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (range(540, 556) as $suffix) {
            DB::table('products')->insert([
                'category_id' => $categoryId,
                'brand_id' => $brandId,
                'name' => 'Старое название '.$suffix,
                'slug' => 'mercury-partner-price-test-'.$suffix,
                'sku' => sprintf('PS-011.%d', $suffix),
                'price' => 1,
                'currency' => 'BYN',
                'is_active' => true,
                'in_stock' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        (require database_path('migrations/2026_10_15_080000_link_mercury_kstb_catalog.php'))->up();
        (require database_path('migrations/2026_10_15_090000_confirm_mercury_kstb_95_1t_stock.php'))->up();
        (require database_path('migrations/2026_10_15_100000_apply_mercury_partner_prices_and_default_stock.php'))->up();

        $supplierId = (int) DB::table('suppliers')->where('code', 'mercury')->value('id');

        $this->assertSame(17, DB::table('supplier_products')->where('supplier_id', $supplierId)->where('in_stock', true)->count());
        $this->assertSame(17, DB::table('products')->whereBetween('sku', ['PS-011.540', 'PS-011.556'])->where('availability_status', 'in_stock')->count());
        $this->assertSame(17, DB::table('products')->whereBetween('sku', ['PS-011.540', 'PS-011.556'])->where('requires_price_confirmation', false)->count());

        $expectedSupplierPrices = [
            3294.00 => 4,
            5076.00 => 4,
            6561.00 => 5,
            7641.00 => 4,
        ];
        foreach ($expectedSupplierPrices as $price => $count) {
            $this->assertSame($count, DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->where('price_byn', $price)
                ->count());
        }

        $controlProduct = DB::table('products')->where('sku', 'PS-011.554')->firstOrFail();
        $this->assertSame(8490.00, (float) $controlProduct->price);
        $this->assertSame(7, (int) $controlProduct->stock_qty);

        $controlOffer = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_id', $controlProduct->id)
            ->firstOrFail();
        $this->assertSame(7641.00, (float) $controlOffer->price_byn);
        $this->assertSame(7, (int) $controlOffer->stock_quantity);

        $defaultOffer = DB::table('supplier_products')
            ->where('supplier_id', $supplierId)
            ->where('product_sku', 'PS-011.540')
            ->firstOrFail();
        $this->assertSame(1, (int) $defaultOffer->stock_quantity);
        $this->assertSame('В наличии: 1 шт.', $defaultOffer->stock_text);

        $raw = json_decode((string) $defaultOffer->raw, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(10, $raw['partner_discount_percent']);
        $this->assertTrue($raw['price_is_supplier_cost']);
        $this->assertSame(7641.00, (float) $raw['partner_price_byn']);
        $this->assertSame('default_available', $raw['stock_source']);
    }
}

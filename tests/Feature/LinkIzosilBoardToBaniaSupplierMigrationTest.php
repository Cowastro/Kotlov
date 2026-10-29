<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LinkIzosilBoardToBaniaSupplierMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_the_existing_izosil_card_to_the_bania_wholesale_offer(): void
    {
        $category = Category::query()->create([
            'name' => 'Огнезащитные плиты и листы',
            'slug' => 'ognezashchitnye-plity-i-listy',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'IZOSIL Плита кальций-силикатная серая 1000×1220×30 мм',
            'slug' => 'izosil-plita-kalcii-silikatnaia-seraia-1000122030-mm',
            'sku' => 'KOTLOV-030536',
            'price' => 151,
            'currency' => 'BYN',
            'is_active' => true,
            'in_stock' => true,
            'stock_qty' => 3,
        ]);
        $supplier = Supplier::query()->updateOrCreate(
            ['code' => 'bania'],
            [
                'name' => 'BANIA.by',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'is_active' => true,
            ],
        );

        $migration = require database_path('migrations/2026_10_29_080000_link_izosil_board_to_bania_supplier.php');
        $migration->up();

        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'product_sku' => 'KOTLOV-030536',
            'supplier_article' => 'BANIA-PRICE-ROW-1965',
            'supplier_name' => 'Плита IZOSIL серая (Силикат кальция) 1000х1220х30мм',
            'price_byn' => 125.40,
            'in_stock' => true,
            'stock_quantity' => 1,
            'stock_status' => 'in_stock',
            'match_status' => 'matched',
            'match_confidence' => 'manual',
        ]);

        $this->assertSame(3, (int) $product->fresh()->stock_qty);
        $this->assertSame(151.0, (float) $product->fresh()->price);
        $this->assertSame(
            ['BANIA.by'],
            $product->fresh()->operationalSuppliers()->pluck('name')->all(),
        );

        $raw = json_decode((string) DB::table('supplier_products')
            ->where('supplier_id', $supplier->id)
            ->value('raw'), true);
        $this->assertSame('В наличии', data_get($raw, 'google_price_list.stock_text'));
        $this->assertSame(1965, data_get($raw, 'google_price_list.row'));
    }
}

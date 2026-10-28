<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\HomeTechnologiesPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncHomeTechnologiesPriceListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_links_purchase_price_but_preserves_own_one_c_stock(): void
    {
        $row = (new HomeTechnologiesPriceList)->load(
            base_path('resources/data/suppliers/home-technologies-2026-09.json')
        )['rows'][0];
        $category = Category::query()->create([
            'name' => 'Тест Домашние технологии',
            'slug' => 'test-home-technologies',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Складская карточка 1С без описания',
            'slug' => 'test-home-technologies-product',
            'sku' => 'KOTLOV-TEST-HOME-TECH',
            'price' => 0,
            'is_active' => true,
            'is_archived' => false,
            'manual_stock_management' => false,
        ]);
        $oneC = IntegrationSource::query()->where('code', 'onec')->firstOrFail();
        $oneC->forceFill(['is_active' => true, 'update_stock' => true])->save();
        IntegrationProduct::query()->create([
            'integration_source_id' => $oneC->id,
            'product_id' => $product->id,
            'external_id' => 'ONEC-HOME-TECH-1',
            'name' => $row['name'],
            'price' => 50,
            'stock_quantity' => 4,
            'match_status' => 'matched',
            'stock_confirmed_at' => now(),
        ]);

        $this->artisan('supplier:sync-home-technologies', [
            'file' => 'resources/data/suppliers/home-technologies-2026-09.json',
            '--apply' => true,
        ])->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'home-technologies')->firstOrFail();
        $offer = SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->where('supplier_article_normalized', $row['article_key'])
            ->firstOrFail();
        $product->refresh();

        $this->assertSame($product->id, $offer->product_id);
        $this->assertNull($offer->stock_quantity);
        $this->assertFalse($offer->in_stock);
        $this->assertTrue($offer->b2b_excluded);
        $this->assertSame(4, $product->stock_qty);
        $this->assertTrue($product->in_stock);
        $this->assertSame(number_format((float) $row['retail'], 2, '.', ''), $product->price);
        $this->assertNotEmpty($product->images);
        $this->assertNotEmpty($product->short_description);
        $this->assertNotEmpty($product->content);
        $this->assertCount(3, $product->specs);
        $this->assertSame('purchase', IntegrationSource::query()->where('code', 'home_technologies_pricelist')->firstOrFail()->b2bPriceRole());
    }
}

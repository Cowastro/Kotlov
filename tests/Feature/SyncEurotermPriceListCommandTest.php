<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncEurotermPriceListCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_online_updates_only_existing_supplier_articles_without_inventing_quantity_or_zeroing_missing_rows(): void
    {
        $category = Category::create(['name' => 'Котлы', 'slug' => 'online-boilers']);
        $supplier = Supplier::create(['name' => 'Евротерм', 'code' => 'euroterm']);
        $other = Supplier::create(['name' => 'Другой', 'code' => 'other']);
        $products = [];
        $rows = [];
        $parser = app(\App\Services\Suppliers\EurotermPriceList::class);
        foreach (['SKU-01' => $supplier->id, 'SKU-02' => $other->id] as $article => $owner) {
            $product = Product::create([
                'supplier_id' => $owner, 'category_id' => $category->id,
                'name' => 'Товар '.$article, 'slug' => strtolower($article), 'sku' => $article, 'price' => 100,
            ]);
            $products[] = $product;
            $supplier->supplierProducts()->create([
                'supplier_article' => $article, 'supplier_article_normalized' => $parser->articleKey($article),
                'product_id' => $product->id, 'match_status' => 'matched', 'price_byn' => 50,
                'in_stock' => true, 'stock_quantity' => 9, 'raw' => ['brand' => 'Подтверждённый бренд'],
            ]);
            $row = app(\App\Services\Suppliers\EurotermOnlinePriceList::class)
                ->parsePage(\Tests\Unit\EurotermOnlinePriceListTest::page($article))['rows'][0];
            $rows[] = array_replace($row, ['article_key' => $parser->articleKey($article), 'name_key' => $parser->nameKey($row['name']), 'wholesale' => 80, 'retail' => 120]);
        }
        $missing = $supplier->supplierProducts()->create([
            'supplier_article' => 'MISSING', 'supplier_article_normalized' => 'missing', 'in_stock' => true, 'stock_quantity' => 5,
        ]);
        $rows[] = array_replace($rows[0], ['article' => 'NEW', 'article_key' => 'new']);
        $productCount = Product::count();
        $this->mock(\App\Services\Suppliers\EurotermOnlinePriceList::class, function ($mock) use ($rows): void {
            $mock->shouldReceive('load')->twice()->andReturn([
                'source_date' => now()->toDateString(), 'source_sha256' => str_repeat('a', 64),
                'source_kind' => 'online', 'source_pages' => 1, 'warehouse' => ['name' => 'Евротерм'], 'rows' => $rows,
            ]);
        });
        for ($i = 0; $i < 2; $i++) {
            $this->artisan('supplier:sync-euroterm', ['--apply' => true])->assertSuccessful();
        }
        $this->assertSame(3, $supplier->supplierProducts()->count());
        $this->assertSame($productCount, Product::count());
        $this->assertSame(120.0, (float) $products[0]->fresh()->price);
        $this->assertSame(100.0, (float) $products[1]->fresh()->price);
        $offer = $supplier->supplierProducts()->where('product_id', $products[0]->id)->first();
        $this->assertSame(80.0, (float) $offer->price_byn);
        $this->assertNull($offer->stock_quantity);
        $this->assertSame('Подтверждённый бренд', $offer->raw['brand']);
        $this->assertSame(5.0, (float) $missing->fresh()->stock_quantity);
    }

    public function test_it_links_exact_model_and_updates_wholesale_retail_and_stock(): void
    {
        $category = Category::query()->create(['name' => 'Газовые котлы', 'slug' => 'gazovye-kotly']);
        $brand = Brand::query()->create(['name' => 'Navien', 'slug' => 'navien']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Газовый котел Navien Deluxe ONE 24K',
            'slug' => 'navien-deluxe-one-24k-test',
            'sku' => 'Deluxe ONE 24K',
            'price' => 1,
            'is_active' => true,
            'requires_price_confirmation' => true,
        ]);

        $this->artisan('supplier:sync-euroterm', [
            'file' => base_path('resources/data/suppliers/euroterm-2026-10-20.json'),
            '--apply' => true,
        ])->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'euroterm')->firstOrFail();
        $this->assertSame('ООО «Евротерм»', $supplier->name);
        $this->assertFalse($supplier->b2b_partner_enabled);
        $this->assertSame('Партнёрский склад', $supplier->b2b_client_warehouse_label);
        $this->assertDatabaseHas('b2b_pricing_rules', [
            'supplier_id' => $supplier->id,
            'calculation_type' => 'target_margin',
            'value' => 18,
            'status' => 'draft',
        ]);
        $this->assertNotNull($supplier->crm_counterparty_id);
        $this->assertSame('tce@euroterm.by', $supplier->communication_channels['email']);
        $this->assertDatabaseHas('crm_counterparties', [
            'id' => $supplier->crm_counterparty_id,
            'classification' => 'supplier',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $supplier->crm_counterparty_id,
            'type' => 'email',
            'normalized_value' => 'tce@euroterm.by',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('crm_contact_points', [
            'counterparty_id' => $supplier->crm_counterparty_id,
            'type' => 'phone',
            'normalized_value' => '375172411142',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_products', [
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article_normalized' => 'deluxeone24k',
            'price_byn' => 1950,
            'stock_quantity' => 4,
            'stock_status' => 'in_stock',
            'warehouse_name' => 'Евротерм · Машиностроителей, 9А',
        ]);
        $this->assertDatabaseHas('supplier_sources', [
            'supplier_id' => $supplier->id,
            'name' => 'Прайс и остатки Евротерм',
            'type' => 'excel',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_syncs', [
            'supplier_id' => $supplier->id,
            'key' => 'euroterm_price',
            'command' => 'supplier:sync-euroterm',
        ]);

        $product->refresh();
        $this->assertSame(2300.0, (float) $product->price);
        $this->assertFalse($product->requires_price_confirmation);
        $this->assertTrue($product->in_stock);
        $this->assertSame(4, $product->stock_qty);
    }
}

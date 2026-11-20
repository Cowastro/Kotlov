<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use App\Services\Suppliers\TermTeploFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TermTeploSupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_article_is_linked_to_supplier_offer_without_a_local_card_or_price_write(): void
    {
        $supplier = Supplier::create(['code' => 'termteplosistem', 'name' => 'ТТС', 'is_active' => true]);
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'supplier_article' => '2202214',
            'supplier_name' => 'Электрический котел Arderia E24', 'raw' => ['title' => 'Котлы ARDERIA']]);
        $client = $this->mock(DealByClient::class);
        $client->shouldReceive('listProducts')->twice()->andReturn(['products' => [
            ['id' => 123, 'sku' => '2202214', 'name' => 'Электрический котел Arderia E 24', 'price' => 900],
        ]]);
        $client->shouldNotReceive('editProducts');
        $this->artisan('deal:sync-brand-prices --supplier=termteplosistem')->assertSuccessful();
        $this->assertArrayNotHasKey('sbg_catalog_id', $offer->fresh()->raw);
        $this->artisan('deal:sync-brand-prices --supplier=termteplosistem --apply')->assertSuccessful();
        $this->assertEquals(123, $offer->fresh()->raw['sbg_catalog_id']);
        $this->assertSame('Котлы ARDERIA', $offer->fresh()->raw['title']);
    }

    public function test_sbg_updates_only_the_unique_complete_model_and_checks_the_result(): void
    {
        $category = Category::create(['name' => 'Водонагреватели', 'slug' => 'termteplo-sbg']);
        $card = Product::create(['category_id' => $category->id, 'sku' => 'internal-h80', 'name' => 'Haier ES80V-F7',
            'slug' => 'termteplo-sbg-h80', 'price' => 550, 'is_active' => true, 'is_archived' => false, 'stock_qty' => 8, 'in_stock' => true]);
        $supplier = Supplier::create(['code' => 'termteplosistem', 'name' => 'ТТС', 'is_active' => true]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $card->id, 'supplier_article' => 'GA0G7R002',
            'supplier_name' => 'ES80V-F7 Водонагреватель Haier', 'stock_quantity' => 8, 'last_stock_synced_at' => now(), 'match_status' => 'matched']);
        $remote = ['id' => 123, 'sku' => '', 'name' => 'Электрический водонагреватель Haier ES80V-F7', 'price' => 550];
        $other = ['id' => 124, 'sku' => 'OTHER', 'name' => 'Haier ES80V-F7(R)', 'price' => 600];
        $client = $this->mock(DealByClient::class);
        $client->shouldReceive('listProducts')->twice()->andReturn(['products' => [$remote, $other]],
            ['products' => [$remote + ['quantity_in_stock' => 8, 'presence' => 'available'], $other]]);
        $client->shouldReceive('editProducts')->once()->with([['id' => 123, 'price' => 550.0, 'presence' => 'available', 'quantity_in_stock' => 8]])->andReturn([]);
        $this->artisan('deal:sync-brand-prices --supplier=termteplosistem --apply')->assertSuccessful();
    }

    public function test_import_links_exact_article_keeps_spec_and_does_not_refresh_stock_date_on_replay(): void
    {
        $category = Category::create(['name' => 'Котлы', 'slug' => 'test-termteplo']);
        $card = Product::create(['category_id' => $category->id, 'sku' => 'GE0Q6NE09', 'name' => 'Urban',
            'slug' => 'test-haier-urban', 'price' => 100, 'specs' => [['key' => 'Тип', 'value' => 'Проверен']]]);
        $feed = $this->mock(TermTeploFeed::class);
        $feed->shouldReceive('sources')->andReturn(['price' => ['id' => 'price', 'date' => '2026-10-12'],
            'stock' => ['id' => 'stock', 'date' => '2026-11-19']]);
        $feed->shouldReceive('download')->andReturnNull();
        $feed->shouldReceive('read')->andReturn([['GE0Q6NE09' => [
            'article' => 'GE0Q6NE09', 'name' => 'Urban', 'title' => 'Котлы и бойлеры Haier',
            'quantity' => 344, 'retail' => 2200, 'cost' => 1879, 'specs' => ['Тип' => 'Другой', 'Мощность, кВт' => '24'],
        ]], [], []]);
        Http::fake(['drive.google.com/*' => Http::response('folder')]);
        $this->artisan('supplier:sync-termteplo --dry-run')->assertSuccessful();
        $this->assertFalse(Supplier::where('code', 'termteplosistem')->exists());
        $this->artisan('supplier:sync-termteplo --apply')->assertSuccessful();
        $this->artisan('supplier:sync-termteplo --apply')->assertSuccessful();
        $offer = SupplierProduct::where('supplier_article', 'GE0Q6NE09')->sole();
        $this->assertEquals($card->id, $offer->product_id);
        $this->assertEquals(344, $offer->stock_quantity);
        $this->assertSame('2026-11-19', $offer->last_stock_synced_at->setTimezone('Europe/Minsk')->toDateString());
        $this->assertEquals(2200, $card->fresh()->price);
        $this->assertSame(['Проверен', '24'], array_column($card->fresh()->specs, 'value'));
    }
}

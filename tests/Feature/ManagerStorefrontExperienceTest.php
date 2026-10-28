<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouseStock;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\ManagerStorefrontProductContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagerStorefrontExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teplov_manufacturer_is_not_shown_as_an_extra_sales_supplier(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $offer->supplier->update(['name' => 'ООО «СанБизнесГруп»']);
        $manufacturer = Supplier::create(['code' => 'teplov-manufacturer-test', 'name' => 'Теплов и Сухов', 'is_active' => true]);
        $legacyOffer = $offer->replicate();
        $legacyOffer->supplier_id = $manufacturer->id;
        $legacyOffer->stock_quantity = 0;
        $legacyOffer->in_stock = false;
        $legacyOffer->save();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);

        $offers = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager);
        $this->assertCount(1, $offers);
        $this->assertSame('ООО «СанБизнесГруп»', $offers->first()['supplier']);
        $this->assertSame('7 шт.', $offers->first()['stock']);
        $this->view('partials.manager-product-context', ['product' => $product->fresh(), 'compact' => true])
            ->assertSee('ООО «СанБизнесГруп»')->assertDontSee('Теплов и Сухов');
        $this->assertDatabaseHas('supplier_products', ['id' => $legacyOffer->id, 'supplier_id' => $manufacturer->id]);
    }

    public function test_belomo_procurement_is_routed_through_sanbusinessgroup_without_changing_prices_or_stock(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $db = DB::class;
        $belomo = Supplier::firstOrCreate(
            ['code' => 'belomo'],
            ['name' => 'БелОМО', 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true],
        );
        $offer->update(['supplier_id' => $belomo->id]);
        $sbg = Supplier::firstOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true],
        );
        $syncId = $db::table('supplier_syncs')->insertGetId([
            'key' => 'belomo_static_pricelist', 'name' => 'СанБизнесГруп',
            'code' => 'sanbusinessgroup', 'title' => 'Прайс БелОМО',
            'command' => 'supplier:sync-belomo-pricelist',
        ]);
        $offer->update(['supplier_sync_id' => $syncId]);
        $before = $offer->fresh();
        $db::table('integration_sources')->insert([
            'code' => 'belomo_wholesale', 'name' => 'СанБизнесГруп',
            'supplier_id' => $offer->supplier_id,
            'settings' => json_encode(['procurement_mode' => true, 'vat_rate' => 20]),
        ]);

        $brand = Brand::firstOrCreate(['slug' => 'belomo'], ['name' => 'БелОМО']);
        $product->update(['brand_id' => $brand->id]);

        $migration = require database_path('migrations/2026_10_21_230000_route_belomo_products_through_sanbusinessgroup.php');
        $migration->up();
        $migration->up();

        $offer->refresh();
        $this->assertSame('sanbusinessgroup', $offer->supplier->code);
        $this->assertSame('ООО «СанБизнесГруп»', $offer->warehouse_name);
        foreach (['price', 'price_byn', 'stock_quantity', 'in_stock', 'last_stock_synced_at', 'last_synced_at'] as $field) {
            $this->assertEquals($before->$field, $offer->$field);
        }
        $this->assertSame(10.0, (float) $product->fresh()->price);
        $source = $db::table('integration_sources')->where('code', 'belomo_wholesale')->first();
        $this->assertEquals($sbg->id, $source->supplier_id);
        $this->assertTrue(json_decode($source->settings, true)['procurement_mode']);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $context = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager)->first();
        $this->assertSame('ООО «СанБизнесГруп»', $context['supplier']);
        $this->assertSame('ООО «СанБизнесГруп»', $context['warehouse']);
    }

    public function test_internal_supplier_context_is_only_available_to_active_managers(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();
        $context = app(ManagerStorefrontProductContext::class);

        $this->assertFalse($context->canView(null));
        $this->assertCount(0, $context->offers($product, null));

        $inactiveManager = User::factory()->create(['role' => 'manager', 'is_active' => false]);
        $this->assertFalse($context->canView($inactiveManager));
        $this->assertCount(0, $context->offers($product, $inactiveManager));

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $offers = $context->offers($product, $manager);

        $this->assertTrue($context->canView($manager));
        $this->assertCount(1, $offers);
        $this->assertSame('Закрытый поставщик', $offers->first()['supplier']);
        $this->assertSame(['+375 29 111-22-33'], $offers->first()['phones']);
        $this->assertSame('SECRET-123', $offers->first()['article']);
        $this->assertSame(4.0, $offers->first()['purchase_price']);
        $this->assertSame(6.0, $offers->first()['margin']);
        $this->assertSame(60.0, $offers->first()['margin_percent']);
        $this->assertSame('7 шт.', $offers->first()['stock']);
    }

    public function test_missing_price_list_offer_does_not_show_stale_purchase_price_or_margin(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $offer->update([
            'stock_status' => 'missing_from_feed',
            'stock_text' => 'Нет в актуальном прайсе поставщика',
            'stock_quantity' => null,
            'in_stock' => false,
            'price' => 4,
            'price_byn' => 4,
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $context = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager)->first();

        $this->assertNull($context['purchase_price']);
        $this->assertNull($context['margin']);
        $this->assertNull($context['margin_percent']);
        $this->assertSame('Нет в актуальном прайсе поставщика', $context['stock']);
        $this->assertFalse($product->fresh()->hasAssignedSupplier());
    }

    public function test_duplicate_links_from_the_same_supplier_are_collapsed_to_the_best_offer(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $offer->update(['supplier_name' => $product->name]);
        $duplicate = $offer->replicate();
        $duplicate->supplier_article = 'SECRET-OLDER';
        $duplicate->supplier_article_normalized = 'SECRET-OLDER';
        $duplicate->supplier_name = 'Другой товар этого поставщика';
        $duplicate->price_byn = 5;
        $duplicate->price = 5;
        $duplicate->in_stock = true;
        $duplicate->stock_quantity = 20;
        $duplicate->stock_status = 'in_stock';
        $duplicate->stock_text = 'на складе';
        $duplicate->last_stock_synced_at = now();
        $duplicate->save();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $offers = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager);

        $this->assertCount(1, $offers);
        $this->assertSame('SECRET-123', $offers->first()['article']);
        $this->assertSame('7 шт.', $offers->first()['stock']);
    }

    public function test_manual_one_c_link_wins_over_an_older_legacy_offer_from_the_same_supplier(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $sbg = Supplier::firstOrCreate(
            ['code' => 'sanbusinessgroup'],
            ['name' => 'ООО «СанБизнесГруп»', 'currency' => 'BYN', 'currency_rate' => 1, 'is_active' => true],
        );
        $offer->update([
            'supplier_id' => $sbg->id,
            'supplier_name' => $product->name,
            'price' => 35,
            'price_byn' => 35,
            'stock_quantity' => 1.8,
        ]);

        $sourceId = DB::table('integration_sources')->insertGetId([
            'supplier_id' => $offer->supplier_id,
            'code' => 'onec-manager-context-test',
            'name' => '1С СанБизнесГруп',
            'is_active' => true,
            'update_stock' => true,
            'settings' => json_encode(['warehouse_label' => 'Основной']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('integration_products')->insert([
            'integration_source_id' => $sourceId,
            'product_id' => $product->id,
            'external_id' => 'UT-00009075',
            'external_code' => 'UT-00009075',
            'external_sku' => 'UT-00009075',
            'name' => 'Одеяло огнеупорное Blanket 1260',
            'price' => 21,
            'price_byn' => 25.20,
            'stock_quantity' => 10,
            'match_status' => 'matched',
            'match_method' => 'manual_search',
            'match_confidence' => 1,
            'matched_at' => now(),
            'stock_confirmed_at' => now(),
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);

        $context = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager)->first();

        $this->assertSame('UT-00009075', $context['article']);
        $this->assertSame(25.20, $context['purchase_price']);
        $this->assertSame('10 шт.', $context['stock']);
    }

    public function test_one_supplier_with_two_warehouses_is_rendered_once(): void
    {
        [$product, $offer] = $this->makeProductWithSupplierOffer();
        $brand = Brand::create(['name' => 'ЭкоКамин', 'slug' => 'ecokamin']);
        $product->update(['brand_id' => $brand->id]);
        $offer->supplier->update(['name' => 'ООО «СанБизнесГруп»']);

        ProductWarehouseStock::create([
            'product_id' => $product->id,
            'supplier_id' => $offer->supplier_id,
            'warehouse_code' => 'ecokamin-moscow',
            'warehouse_name' => 'Склад Москва',
            'external_article' => 'SECRET-123',
            'source_product_name' => $product->name,
            'total_quantity' => 4,
            'available_quantity' => 4,
            'delivery_days' => 7,
            'as_of_date' => now()->toDateString(),
            'last_synced_at' => now(),
        ]);
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);

        $offers = app(ManagerStorefrontProductContext::class)->offers($product->fresh(), $manager);

        $this->assertCount(1, $offers);
        $this->assertSame('11 шт. · 2 склада', $offers->first()['stock']);
        $this->assertSame(['Склад Москва', 'Склад Минск'], collect($offers->first()['locations'])->pluck('warehouse')->all());
        $html = (string) $this->view('partials.manager-product-context', [
            'product' => $product->fresh(),
            'compact' => true,
        ]);
        $this->assertSame(1, substr_count($html, 'ООО «СанБизнесГруп»'));
        $this->assertStringContainsString('Склад Москва: 4 шт.', $html);
        $this->assertStringContainsString('Склад Минск: 7 шт.', $html);
    }

    public function test_internal_supplier_markup_is_not_rendered_for_guests(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();

        $this->view('partials.manager-product-context', ['product' => $product])
            ->assertDontSee('Закрытый поставщик')
            ->assertDontSee('SECRET-123');

        $manager = User::factory()->create(['role' => 'sales_manager', 'is_active' => true]);
        $this->actingAs($manager);

        $this->view('partials.manager-product-context', ['product' => $product])
            ->assertSee('Закрытый поставщик')
            ->assertSee('SECRET-123')
            ->assertSee('Закупка: 4.00 BYN')
            ->assertSee('Позвонить: +375 29 111-22-33')
            ->assertDontSee('Контакт: Отдел продаж')
            ->assertSee('tel:+375291112233', false);

        $this->view('partials.manager-product-context', ['product' => $product, 'compact' => true])
            ->assertSee('<details', false)
            ->assertSee('Закрытый поставщик')
            ->assertSee('SECRET-123');
    }

    public function test_only_manager_search_can_find_products_by_supplier_article_or_name(): void
    {
        [$product] = $this->makeProductWithSupplierOffer();

        $this->get('/search?q=SECRET-123')
            ->assertOk()
            ->assertDontSee($product->name);

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager)
            ->get('/search?q=SECRET-123')
            ->assertOk()
            ->assertSee($product->name)
            ->assertSee('Закрытый поставщик');

        $this->getJson('/search/suggest?'.http_build_query(['q' => 'Закрытый поставщик']))
            ->assertOk()
            ->assertJsonFragment(['name' => $product->name]);
    }

    /** @return array{Product, SupplierProduct} */
    private function makeProductWithSupplierOffer(): array
    {
        $category = Category::create([
            'name' => 'Тестовый каталог',
            'slug' => 'manager-storefront-test',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Товар без секретов в названии',
            'slug' => 'manager-storefront-product',
            'sku' => 'PUBLIC-SKU',
            'price' => 10,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $supplier = Supplier::create([
            'code' => 'private-supplier',
            'name' => 'Закрытый поставщик',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => 'Отдел продаж: +375 29 111-22-33',
            'is_active' => true,
        ]);
        $offer = SupplierProduct::create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SECRET-123',
            'supplier_name' => 'Внутреннее название',
            'price' => 4,
            'currency' => 'BYN',
            'currency_rate' => 1,
            'price_byn' => 4,
            'in_stock' => true,
            'stock_quantity' => 7,
            'stock_status' => 'in_stock',
            'last_stock_synced_at' => now(),
            'last_synced_at' => now(),
            'match_status' => 'matched',
        ]);

        return [$product, $offer];
    }
}

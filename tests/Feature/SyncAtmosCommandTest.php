<?php

namespace Tests\Feature;

use App\Console\Commands\SyncAtmosCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncAtmosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_parses_the_price_page(): void
    {
        $rows = (new SyncAtmosCommand)->parsePage((string) file_get_contents(base_path('tests/Fixtures/atmos-price-page.html')), 1, 'BURNIT');

        $this->assertSame(['BURNiT WBS AC 30', 'BURNiT WBS AC 40', 'BURNiT WBS AC 50'], array_column($rows, 'name'));
        $this->assertSame([5865.0, 8216.04, 8850.35], array_column($rows, 'retail'));
        $this->assertSame(4985.25, $rows[0]['wholesale']);
        $this->assertSame([true, false, false], array_column($rows, 'in_stock'));
        $this->assertSame('2024', $rows[0]['article']);
        $this->assertSame('burnit', $rows[0]['family']);
        $this->assertSame('ZOTA BULAT 23', (new SyncAtmosCommand)->cleanName('ZOTA BULAT 23. Скидка -10%!'));
        $this->assertSame('VITA 24 F (с трубой)', (new SyncAtmosCommand)->cleanName('VITA 24 F (с трубой). ЦЕНА В БЕЛ.РУБ!'));
    }

    public function test_it_links_rows_and_syncs_prices_and_stock(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'tverdotoplivnye'], ['name' => 'Твердотопливные котлы', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Burnit', 'slug' => 'burnit', 'is_active' => true]);
        $exact = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 1, 'name' => 'BURNiT WBS AC 40', 'slug' => 'burnit-wbs-ac-40']);
        $model = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'price' => 1, 'requires_price_confirmation' => true, 'name' => 'Твердотопливный котел Burnit WBS AC 50 кВт', 'slug' => 'burnit-wbs-ac-50']);
        // Another supplier's article of another brand must not link.
        $foreign = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 1, 'name' => 'Котел UNIVAT 30', 'slug' => 'univat-30']);
        $other = Supplier::query()->create(['code' => 'other', 'name' => 'Other', 'is_active' => true]);
        \Illuminate\Support\Facades\DB::table('supplier_products')->insert(['supplier_id' => $other->id, 'product_id' => $foreign->id, 'supplier_article' => '2024', 'supplier_article_normalized' => '2024', 'currency' => 'BYN', 'match_status' => 'matched', 'created_at' => now(), 'updated_at' => now()]);

        $page = (string) file_get_contents(base_path('tests/Fixtures/atmos-price-page.html'));
        Http::fake([
            'price.teplota.by/prices*' => Http::response(str_repeat($page, 34)),
            'price.teplota.by/' => Http::response('<a href = "/prices?section=1&brand=3 ">BURNIT</a>'),
        ]);

        $this->artisan('supplier:sync-atmos --audit')
            ->expectsOutputToContain('metric.atmos.matched=68')
            ->expectsOutputToContain('audit.atmos.row=1:burnit:2:'.$exact->id.':burnit-wbs-ac-40:exact_name:0')
            ->expectsOutputToContain('audit.atmos.row=1:burnit:3:'.$model->id.':burnit-wbs-ac-50:model_key:0')
            ->expectsOutputToContain('audit.atmos.row=1:burnit:1:0::none:1')
            ->assertSuccessful();
        $this->assertNull(Supplier::query()->where('code', 'atmos')->first());

        $this->artisan('supplier:sync-atmos --apply --sync-retail-prices')->assertSuccessful();

        $model = $model->fresh();
        $this->assertSame('8850.35', (string) $model->price);
        $this->assertFalse($model->requires_price_confirmation);
        $link = $model->supplierProducts()->sole();
        $this->assertSame('7522.80', number_format((float) $link->price_byn, 2, '.', ''));
        $this->assertFalse((bool) $link->in_stock);
        $this->assertSame('preorder', $link->stock_status);
        $this->assertSame(Product::AVAILABILITY_CHECK, $model->availability_status);
        $this->assertSame('8216.04', (string) $exact->fresh()->price);
    }
}

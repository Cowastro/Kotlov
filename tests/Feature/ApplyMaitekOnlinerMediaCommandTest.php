<?php

namespace Tests\Feature;

use App\Console\Commands\ApplyMaitekOnlinerMediaCommand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyMaitekOnlinerMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_photos_specs_and_title_of_the_card_linked_to_the_price_row(): void
    {
        $category = Category::query()->create(['name' => 'Электрические', 'slug' => 'elektricheskie', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'HOTEX HOTEX BE 4 кВт c Wi-Fi',
            'slug' => 'hotex-hotex-be-4-kvt-c-wi-fi',
            'sku' => 'MT-HOTEX-4',
            'price' => 1179,
            'is_active' => false,
        ]);
        $supplierId = DB::table('suppliers')->insertGetId(['code' => 'maitek-group', 'name' => 'Майтек Групп', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('supplier_products')->insert([
            'supplier_id' => $supplierId,
            'product_id' => $product->id,
            'supplier_article' => 'HOTEX HOTEX BE 4 кВт c Wi-Fi',
            'supplier_article_normalized' => 'HOTEX HOTEX BE 4 KBT C WI-FI',
            'supplier_name' => 'HOTEX HOTEX BE 4 кВт c Wi-Fi',
            'match_status' => 'matched',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('catalog:apply-maitek-onliner-media')->assertSuccessful();
        $this->assertSame([], $product->fresh()->images ?? []);

        $this->artisan('catalog:apply-maitek-onliner-media --apply')->assertSuccessful();
        $product->refresh();

        $this->assertSame('Электрический котел HOTEX BE 4 кВт c Wi-Fi', $product->name);
        $this->assertNotEmpty($product->images);
        $this->assertFileExists(public_path(ltrim($product->images[0], '/')));
        $this->assertContains(['key' => 'Мощность обогрева', 'value' => '4 кВт'], $product->specs);
        $this->assertStringContainsString('площадь обогрева до 40 м²', (string) $product->short_description);
        $this->assertSame('https://catalog.onliner.by/boiler/hotex/hotexbe4', $product->content_source_url);
    }

    public function test_skat_gets_the_hotex_photos_but_not_their_source_page(): void
    {
        $category = Category::query()->create(['name' => 'Электрические', 'slug' => 'elektricheskie', 'is_active' => true]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'SKAT Skat plus 7.5 кВт',
            'slug' => 'skat-skat-plus-75-kvt',
            'sku' => 'MT-SKAT-75',
            'price' => 1600,
            'is_active' => false,
        ]);
        $supplierId = DB::table('suppliers')->insertGetId(['code' => 'maitek-group', 'name' => 'Майтек Групп', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('supplier_products')->insert([
            'supplier_id' => $supplierId, 'product_id' => $product->id,
            'supplier_article' => 'SKAT Skat plus 7.5 кВт', 'supplier_article_normalized' => 'SKAT SKAT PLUS 7.5 KBT',
            'supplier_name' => 'SKAT Skat plus 7.5 кВт', 'match_status' => 'matched', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('catalog:apply-maitek-onliner-media --apply')->assertSuccessful();
        $product->refresh();

        $this->assertSame('Электрический котел Skat plus 7.5 кВт', $product->name);
        $hotex = collect(json_decode((string) file_get_contents(resource_path(ApplyMaitekOnlinerMediaCommand::DATA_FILE)), true))
            ->firstWhere('supplier_name', 'HOTEX HOTEX BES 7.5 кВт c Wi-Fi');
        $this->assertSame($hotex['images'], $product->images);
        $this->assertEmpty($product->specs);
        $this->assertNull($product->content_source_url);
    }

    public function test_heater_takes_the_photos_of_its_series_and_keeps_its_name(): void
    {
        $category = Category::query()->create(['name' => 'ТЭНы', 'slug' => 'teny', 'is_active' => true]);
        Product::query()->create([
            'category_id' => $category->id, 'name' => 'СТЭН ТЭНБ - 3 - 1 1/2" - Ч с колпаком', 'slug' => 'sten-tenb-3-1-12-c-s-kolpakom',
            'sku' => 'MT-TENB-G112', 'price' => 100, 'images' => ['/img/products/sten-tenb.webp'], 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id, 'name' => 'СТЭН ТЭНБ - 3 - G2" - Ч с колпаком', 'slug' => 'sten-tenb-3-g2-c-s-kolpakom',
            'sku' => 'MT-TENB-G2', 'price' => 110, 'is_active' => false,
        ]);
        $supplierId = DB::table('suppliers')->insertGetId(['code' => 'maitek-group', 'name' => 'Майтек Групп', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('supplier_products')->insert([
            'supplier_id' => $supplierId, 'product_id' => $product->id,
            'supplier_article' => 'СТЭН ТЭНБ - 3 - G2" - Ч с колпаком', 'supplier_article_normalized' => 'TENB-3-G2',
            'supplier_name' => 'СТЭН ТЭНБ - 3 - G2" - Ч с колпаком', 'match_status' => 'matched', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('catalog:apply-maitek-onliner-media --apply')->assertSuccessful();
        $product->refresh();

        $this->assertSame('СТЭН ТЭНБ - 3 - G2" - Ч с колпаком', $product->name);
        $this->assertSame(['/img/products/sten-tenb.webp'], $product->images);
    }

    public function test_key_ignores_look_alike_letters(): void
    {
        $this->assertSame(
            ApplyMaitekOnlinerMediaCommand::key('Сангай Котел ЭВПМ-7,5 Сангай-МК'),
            ApplyMaitekOnlinerMediaCommand::key('Сaнгaй  Котел ЭВПМ-7.5 Сангай-МК'),
        );
    }
}

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

    public function test_key_ignores_look_alike_letters(): void
    {
        $this->assertSame(
            ApplyMaitekOnlinerMediaCommand::key('Сангай Котел ЭВПМ-7,5 Сангай-МК'),
            ApplyMaitekOnlinerMediaCommand::key('Сaнгaй  Котел ЭВПМ-7.5 Сангай-МК'),
        );
    }
}

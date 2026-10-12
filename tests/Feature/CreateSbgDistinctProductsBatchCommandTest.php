<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSbgDistinctProductsBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dry_runs_then_creates_a_complete_distinct_variant_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'mono-pipes',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-007993',
            'name' => 'Теплов и Сухов Труба L250 ТМ-Р 430-0.5 D130',
            'slug' => 'template-pipe',
            'price' => 20,
            'images' => ['product/008/007993/pipe.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'distinct-sbg-item',
            'external_code' => 'УТ-00000947',
            'external_sku' => 'TS.FRT.TRB.0130.12345',
            'name' => 'Труба L250 ТМ-Р 430-0.8 D130 (У)',
            'price_byn' => 19.39,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.69]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', ['--code' => ['УТ-00000947']])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', ['--code' => ['УТ-00000947'], '--apply' => true])
            ->expectsOutputToContain('APPLY')
            ->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertNotNull($product);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('27.70', $product->price);
        $this->assertSame(['product/008/007993/pipe.jpg'], $product->images);
        $this->assertSame($product->name, $product->h1);
        $this->assertNotEmpty($product->short_description);
        $this->assertStringContainsString('Цена и складской остаток поставщика', $product->content);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0.8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('130 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue($product->in_stock);
    }

    public function test_it_creates_a_researched_product_with_verified_source_content(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-researched',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'chimneys', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'DARCO', 'slug' => 'darco']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.331',
            'name' => 'DARCO Труба RP130/500-CZ2 (ML)',
            'slug' => 'darco-template',
            'price' => 65,
            'images' => ['img/products/darco/template.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'darco-rp120-500',
            'external_code' => 'УТ-00000962',
            'name' => 'Труба RP 120/500-CZ2 (ML)',
            'price_byn' => 49.61,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.95]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-e',
            '--code' => ['УТ-00000962'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame('DARCO Труба дымоходная RP120/500-CZ2 (ML) Ø120, 2 мм', $product->name);
        $this->assertSame('70.90', $product->price);
        $this->assertStringContainsString('sklep.darco.pl', $product->content_source_url);
        $this->assertSame('2 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertStringContainsString('ca2d77af23ce0eed739d5bb6a99e2b48', $product->images[0]);
    }
}

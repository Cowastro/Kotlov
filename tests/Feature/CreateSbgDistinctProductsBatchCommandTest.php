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

    public function test_it_creates_a_missing_verified_brand_for_a_researched_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-elcon',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Герметики и монтажные материалы', 'slug' => 'sealants', 'parent_id' => 0]);
        $certa = Brand::query()->create(['name' => 'Certa', 'slug' => 'certa']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $certa->id,
            'sku' => 'PS-007.697',
            'name' => 'Краска термостойкая (эмаль) Certa черная (520мл)',
            'slug' => 'certa-template',
            'price' => 12,
            'images' => ['product/007/007697/CAM01115.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'elcon-max-therm-520',
            'external_code' => 'УТ-00001178',
            'name' => 'Эмаль термостойкая ELCON 520 мл аэрозоль черная',
            'price_byn' => 14.40,
            'stock_quantity' => 15,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.66]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-f',
            '--code' => ['УТ-00001178'],
        ])->assertSuccessful();
        $this->assertDatabaseMissing('brands', ['slug' => 'elcon']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-f',
            '--code' => ['УТ-00001178'],
            '--apply' => true,
        ])->assertSuccessful();

        $brand = Brand::query()->where('slug', 'elcon')->firstOrFail();
        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame('ELCON', $brand->name);
        $this->assertSame('Россия', $brand->country);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('20.60', $product->price);
        $this->assertStringContainsString('elcon-mangal', $product->content_source_url);
        $this->assertSame('до 1000 °C', collect($product->specs)->firstWhere('key', 'Термостойкость')['value']);
        $this->assertStringContainsString('ei9ai7nmri8olju0ojfiespb506bxm6a', $product->images[0]);
    }

    public function test_it_creates_the_verified_kp_350_mount_in_the_chimney_mounting_category(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-kp-350',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Крепления и монтаж', 'slug' => 'chimney-mounts', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.119',
            'name' => 'Крепление универсальное Теплов и Сухов КУ Ø 240',
            'slug' => 'mount-template',
            'price' => 43.97,
            'images' => ['product/009/009119/mount.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-004.961',
            'name' => 'Крепление коллектора Buderus WMS3',
            'slug' => 'wrong-buderus-mount',
            'price' => 40,
            'images' => ['product/004/004961/mount.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-kp-350',
            'external_code' => 'УТ-00000430',
            'external_sku' => 'TS.KMP.KRP.0000.66285',
            'barcode' => '4680205277184',
            'name' => 'Крепление подвес КП 350',
            'price_byn' => 33.01,
            'stock_quantity' => 6,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.6739]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-g',
            '--code' => ['УТ-00000430'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Крепление-подвес КП 350, комплект 2 шт.', $product->name);
        $this->assertSame('47.20', $product->price);
        $this->assertSame('6', (string) $product->stock_qty);
        $this->assertStringContainsString('kreplenie-podves-kp-350', $product->content_source_url);
        $this->assertSame('350 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('4680205277184', collect($product->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertStringContainsString('yyvc6hgtzh97c17e0pkmab40cyk6l3u3', $product->images[0]);
    }

    public function test_it_creates_the_brandless_obvalovannyy_gabbro_diabase_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-gabbro-obval',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Камни для бани', 'slug' => 'sauna-stones', 'parent_id' => 0]);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'PS-000.123',
            'name' => 'Камни Габбро-диабаз колотый (20 кг)',
            'slug' => 'gabbro-split-template',
            'price' => 24,
            'images' => ['product/000/000123/gabbro.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'gabbro-obval-20kg',
            'external_code' => 'УТ-00000263',
            'name' => 'Камень для саун (габбро-диабаз обвал,кор)',
            'price_byn' => 21.50,
            'stock_quantity' => 5,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.6718]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-h',
            '--code' => ['УТ-00000263'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame($category->id, $product->category_id);
        $this->assertNull($product->brand_id);
        $this->assertSame('Камни для бани габбро-диабаз обвалованный, 20 кг', $product->name);
        $this->assertSame('30.75', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertStringContainsString('kamni-gabbro-diabaz-obvalovannyj', $product->content_source_url);
        $this->assertSame('Обвалованный', collect($product->specs)->firstWhere('key', 'Обработка')['value']);
        $this->assertSame('20 кг', collect($product->specs)->firstWhere('key', 'Фасовка')['value']);
        $this->assertStringContainsString('kamni_g-d_obval.png', $product->images[0]);
    }

    public function test_it_creates_the_verified_teplov_adapter_instead_of_the_ferrum_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-mm-130',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Переходы и адаптеры', 'slug' => 'chimney-adapters', 'parent_id' => 0]);
        $ferrum = Brand::query()->create(['name' => 'Ferrum', 'slug' => 'ferrum']);
        $teplov = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ferrum->id,
            'sku' => 'PS-007.853',
            'name' => 'Адаптер ММ FERRUM (430/0,8мм) Ф130',
            'slug' => 'ferrum-adapter-mm-130',
            'price' => 18,
            'images' => ['product/007/007853/adapter.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-mm-130',
            'external_code' => 'УТ-00000045',
            'external_sku' => 'TS.FRT.AKL.0130.32856',
            'barcode' => '4680205063992',
            'name' => 'Адаптер Моно М-М 430-0.8 D130 (У)',
            'price_byn' => 11.74,
            'stock_quantity' => 27,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.7191]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-i',
            '--code' => ['УТ-00000045'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер Моно М-М 430-0.8 D130 (У)', $product->name);
        $this->assertSame('18.00', $product->price);
        $this->assertSame('27', (string) $product->stock_qty);
        $this->assertSame('130 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('4680205063992', collect($product->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertStringContainsString('ts-frt-akl-0130-32856', $product->content_source_url);
        $this->assertStringContainsString('51404470.jpg', $product->images[0]);
    }
}

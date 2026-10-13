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

    public function test_it_creates_the_verified_teplov_110_adapter_instead_of_the_ferrum_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-mm-110',
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
            'sku' => 'PS-007.944',
            'name' => 'Адаптер ММ FERRUM (430/0,8мм) Ф110',
            'slug' => 'ferrum-adapter-mm-110',
            'price' => 16,
            'images' => ['product/007/007944/adapter.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-mm-110',
            'external_code' => 'УТ-00000044',
            'external_sku' => 'TS.FRT.AKL.0110.83435',
            'barcode' => '4680205039478',
            'name' => 'Адаптер Моно М-М 430-0.8 D110 (У)',
            'price_byn' => 9.56,
            'stock_quantity' => 51,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.7191]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-j',
            '--code' => ['УТ-00000044'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер Моно М-М 430-0.8 D110 (У)', $product->name);
        $this->assertSame('16.00', $product->price);
        $this->assertSame('51', (string) $product->stock_qty);
        $this->assertSame('110 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('4680205039478', collect($product->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertStringContainsString('adapter-mono-m-m-430-0-8-d110-u', $product->content_source_url);
        $this->assertStringContainsString('IMG17586c5773810e092068ef5f09ad9950', $product->images[0]);
    }

    public function test_it_creates_the_exact_aisi_304_d115_superheater_as_a_distinct_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-superheater-304-115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Теплосъёмники', 'slug' => 'heat-exchangers', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-010.836',
            'name' => 'Пароперегреватель ППШ-М',
            'slug' => 'ppsh-m-generic',
            'price' => 378.95,
            'images' => ['product/010/010836/ppsh.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-011.152',
            'name' => 'ППШ-Б бак 40л и пароперегреватель трубный D115',
            'slug' => 'ppsh-b-tank-40l',
            'price' => 1200,
            'images' => ['product/011/011152/ppsh-b.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '0992344f-efcb-11ed-a711-1c1b0d1de7c2',
            'external_code' => 'УТ-00008043',
            'external_sku' => 'TS.BAK.PPS.0115.79803',
            'barcode' => '4680205552700',
            'name' => 'ППШ-М Пароперегреватель трубный 304, L495 D115/115 (К)',
            'price_byn' => 346.70,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.7925]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-k',
            '--code' => ['УТ-00008043'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов ППШ-М пароперегреватель трубный AISI 304, L495, D115/115 (К)', $product->name);
        $this->assertSame('399.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр дымохода')['value']);
        $this->assertSame('TS.BAK.PPS.0115.79803', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertStringContainsString('ppsh-m-paroperegrevatel-trubnyj-304-l495-d115/115-k', $product->content_source_url);
        $this->assertCount(2, $product->images);
        $this->assertStringContainsString('qWnXfn-N0ofrsIuLPQmKrA', $product->images[0]);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_stainless_kp_700_mount_as_a_distinct_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-kp-700',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Крепления и монтаж', 'slug' => 'chimney-mounting', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022597',
            'name' => 'Теплов и Сухов Крепление-подвес КП 350, комплект 2 шт.',
            'slug' => 'kp-350-template',
            'price' => 47.20,
            'images' => ['product/kp-350.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.961',
            'name' => 'Крепление коллектора Buderus WMS3',
            'slug' => 'buderus-wms3',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '1888a166-41d0-11ea-a232-1c1b0d1de7c2',
            'external_code' => 'УТ-00003214',
            'external_sku' => 'TS.KMP.KRP.0000.66354',
            'barcode' => '4680205277191',
            'name' => 'Крепление подвес КП 700',
            'price_byn' => 47.86,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.6522]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-l',
            '--code' => ['УТ-00003214'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Крепление-подвес КП 700', $product->name);
        $this->assertSame('59.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertSame('700 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('1,47 кг', collect($product->specs)->firstWhere('key', 'Масса')['value']);
        $this->assertSame('TS.KMP.KRP.0000.66354', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertStringContainsString('kreplenie_podves_kp_700', $product->content_source_url);
        $this->assertStringContainsString('ijo8z5kglz1ipkakkic2apd1u2vs6ssu', $product->images[0]);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_unpainted_d115_200_thermo_cap_as_a_distinct_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-zkt-115-200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Зонты и дефлекторы', 'slug' => 'chimney-caps', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008041',
            'name' => 'Теплов и Сухов Зонт-Конус Термо MC Black D115/200',
            'slug' => 'zkt-115-200-black-template',
            'price' => 104.32,
            'images' => ['product/zkt-black.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008042',
            'name' => 'Теплов и Сухов Конус Термо КТ-Р MC Black D115/200',
            'slug' => 'kt-115-200-black-candidate',
            'price' => 60.60,
            'images' => ['product/kt-black.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'zkt-115-200-unpainted',
            'external_code' => 'УТ-00008824',
            'external_sku' => 'TS.FRT.ZKN.0115.74700',
            'name' => 'Зонт-Конус Термо ЗКТ-Р 430-0.5/430 D115/200* У',
            'price_byn' => 48.89,
            'stock_quantity' => 12,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.65]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-m',
            '--code' => ['УТ-00008824'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Зонт-Конус Термо ЗКТ-Р 430-0.5/430 D115/200 (У)', $product->name);
        $this->assertSame('55.00', $product->price);
        $this->assertSame('12', (string) $product->stock_qty);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('200 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('TS.FRT.ZKN.0115.74700', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertStringContainsString('zont-konus-termo-zkt-r-430-0-5-430-d115-200', $product->content_source_url);
        $this->assertStringContainsString('51984593.jpg', $product->images[0]);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_unpainted_d120_180_thermo_cap_as_a_distinct_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-zkt-120-180',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Зонты и дефлекторы', 'slug' => 'chimney-caps-120', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022603',
            'name' => 'Теплов и Сухов Зонт-Конус Термо ЗКТ-Р D115/200',
            'slug' => 'zkt-115-200-template',
            'price' => 55,
            'images' => ['product/zkt-115-200.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.079',
            'name' => 'Конус Термо КТ-Р 430-0.5/430 D120/180',
            'slug' => 'kt-120-180-candidate',
            'price' => 55,
            'images' => ['product/kt-120-180.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'zkt-120-180-unpainted',
            'external_code' => 'УТ-00009196',
            'external_sku' => 'TS.FRT.ZKN.0120.74698',
            'name' => 'Зонт-Конус Термо ЗКТ-Р 430-0.5/430 D120/180 У1',
            'price_byn' => 48.54,
            'stock_quantity' => 7,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.69]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261012-n',
            '--code' => ['УТ-00009196'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Зонт-Конус Термо ЗКТ-Р 430-0.5/430 D120/180 (У1)', $product->name);
        $this->assertSame('55.00', $product->price);
        $this->assertSame('7', (string) $product->stock_qty);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('180 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('TS.FRT.ZKN.0120.74698', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertStringContainsString('/4247/', $product->content_source_url);
        $this->assertStringContainsString('7il96iu0543p3l1czodqcc84cjbv93sp.jpeg', $product->images[0]);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_mixfor_mt77_bituminous_sealant_as_a_distinct_product(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-mixfor-mt77',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Герметики и монтажные материалы', 'slug' => 'sealants-mt77', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'MIXFOR', 'slug' => 'mixfor']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022563',
            'name' => 'MIXFOR MT-78 HiTemp герметик высокотемпературный, 300 мл',
            'slug' => 'mixfor-mt78-template',
            'price' => 15.90,
            'images' => ['product/mixfor-mt78.jpg'],
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'PS-006.489',
            'name' => 'Герметик битумный OLIMP, 290 мл',
            'slug' => 'olimp-bituminous-sealant-candidate',
            'price' => 16,
            'images' => ['product/olimp-bituminous-sealant.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'mixfor-mt77-310',
            'external_code' => 'УТ-00002604',
            'name' => 'Герметик битумный MIXFOR МТ-77 черный, 310 мл',
            'price_byn' => 12,
            'stock_quantity' => 35,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-o',
            '--code' => ['УТ-00002604'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('MIXFOR MT-77 герметик битумный для кровли, чёрный, 310 мл', $product->name);
        $this->assertSame('18.00', $product->price);
        $this->assertSame('35', (string) $product->stock_qty);
        $this->assertSame('MT-77', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('310 мл', collect($product->specs)->firstWhere('key', 'Объём')['value']);
        $this->assertStringContainsString('amazonka.by/germetik-bitumnyy-dlya-krovli-mixfor-mt-77', $product->content_source_url);
        $this->assertStringContainsString('bitumnyy-germetik-dlya-krov.webp', $product->images[0]);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_an_exact_researched_product_without_inventing_a_missing_supplier_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-omr-130-08',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Колена моно', 'slug' => 'mono-elbows-130', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-omr']);
        $template = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-007994',
            'name' => 'Теплов и Сухов Отвод 87° ОМ-Р 430-0.5 D130 (3S)',
            'slug' => 'omr-130-05-template',
            'price' => 29,
            'images' => ['product/omr-130-05.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'omr-130-08',
            'external_code' => 'УТ-00008565',
            'external_sku' => 'TS.FRT.O87.0130.75385',
            'name' => 'Отвод 87* ОМ-Р 430-0.8 D130 (3S) арт. TS.FRT.O87.0130.75385',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $template->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-p',
            '--code' => ['УТ-00008565'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($template->id, $product->id);
        $this->assertSame('Теплов и Сухов Отвод 87° ОМ-Р 430-0.8 D130 (3S)', $product->name);
        $this->assertSame('37.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.FRT.O87.0130.75385', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_one_metre_taim_gas_hose_without_linking_the_one_and_a_half_metre_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-taim-gas-hose-100',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $wrongCategory = Category::query()->create(['name' => 'Счётчики газа', 'slug' => 'gas-meters-hose-test', 'parent_id' => 0]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'gazovye-shlangi'],
            ['name' => 'Газовые шланги', 'h1' => 'Газовые шланги', 'parent_id' => 0, 'type' => 'main-child', 'is_active' => true],
        );
        $grand = Brand::query()->create(['name' => 'Гранд', 'slug' => 'grand']);
        $wrongLengthCandidate = Product::query()->create([
            'category_id' => $wrongCategory->id,
            'brand_id' => $grand->id,
            'sku' => 'PS-006.071',
            'name' => 'Шланг для газа Гранд Г-Ш 1/2x1/2 150',
            'slug' => 'grand-gas-hose-150-template',
            'price' => 15,
            'images' => ['product/006/006071/hose.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'taim-gas-hose-100',
            'external_code' => 'УТ-00001151',
            'name' => 'Шланг газовый ШГ-Ш1/2-1,0-Г1/2*',
            'price_byn' => null,
            'stock_quantity' => 80,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongLengthCandidate->id, 'score' => 0.65]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-q',
            '--code' => ['УТ-00001151'],
            '--apply' => true,
        ])->assertSuccessful();

        $brand = Brand::query()->where('slug', 'taim')->firstOrFail();
        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongLengthCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка', $product->name);
        $this->assertSame('11.00', $product->price);
        $this->assertSame('80', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('1,0 м', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('Штуцер–гайка 1/2″ × 1/2″', collect($product->specs)->firstWhere('key', 'Присоединение')['value']);
        $this->assertStringContainsString('minskgas.by/product/shlang-gazovyj-rezinovyj', $product->content_source_url);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_enisey_two_front_glass_in_a_dedicated_category(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-robax-enisey-2-front',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $wrongCategory = Category::query()->create(['name' => 'Дровницы', 'slug' => 'firewood-glass-test', 'parent_id' => 0]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'stekla-dlya-pechey-i-kaminov'],
            ['name' => 'Стекло для печей и каминов', 'h1' => 'Стекло для печей и каминов', 'parent_id' => 0, 'type' => 'main-child', 'is_active' => true],
        );
        $mbs = Brand::query()->create(['name' => 'MBS', 'slug' => 'mbs-glass-test']);
        $wrongSizeCandidate = Product::query()->create([
            'category_id' => $wrongCategory->id,
            'brand_id' => $mbs->id,
            'sku' => 'KOTLOV-022512',
            'name' => 'MBS Стекло термостойкое 183 х 171 MBS',
            'slug' => 'mbs-glass-183-171-template',
            'price' => 44,
            'images' => ['product/mbs/glass-183-171.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'robax-enisey-2-front-430-330',
            'external_code' => 'УТ-00000857',
            'name' => 'Стекло термостойкое 430х330х4 мм',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongSizeCandidate->id, 'score' => 0.76]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-r',
            '--code' => ['УТ-00000857'],
            '--apply' => true,
        ])->assertSuccessful();

        $brand = Brand::query()->where('slug', 'robax')->firstOrFail();
        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongSizeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('ROBAX Стекло огнеупорное МЕТА-БЕЛ Енисей-2 фронтальное, 430×330×4 мм', $product->name);
        $this->assertSame('215.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('430 мм', collect($product->specs)->firstWhere('key', 'Ширина')['value']);
        $this->assertSame('330 мм', collect($product->specs)->firstWhere('key', 'Высота')['value']);
        $this->assertSame('4 мм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_archived_ole_pro_burner_temperature_sensor_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-ole-pro-os-h-tp',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Автоматика и терморегуляторы',
            'slug' => 'regulyatory-sensor-test',
            'parent_id' => 0,
        ]);
        $kospel = Brand::query()->create(['name' => 'Kospel', 'slug' => 'kospel-sensor-test']);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $kospel->id,
            'sku' => 'PS-000.17896',
            'name' => 'Датчик температуры Kospel WE-019/01',
            'slug' => 'kospel-we-019-01-sensor-test',
            'price' => 40,
            'images' => ['product/kospel/we-019-01.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'ole-pro-os-h-tp-20-50',
            'external_code' => 'УТ-00000144',
            'name' => 'Датчик температуры OS-H-TP (20-50)',
            'price_byn' => null,
            'stock_quantity' => 6,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.8317]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-s',
            '--code' => ['УТ-00000144'],
            '--apply' => true,
        ])->assertSuccessful();

        $brand = Brand::query()->where('slug', 'ole-pro')->firstOrFail();
        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('OLE-PRO OS-H-TP датчик температуры горелки 20–50 кВт', $product->name);
        $this->assertSame('61.00', $product->price);
        $this->assertSame('6', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('OS-H-TP', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('OFC-OXI-1, OFC-OXI-2', collect($product->specs)->firstWhere('key', 'Совместимые контроллеры')['value']);
        $this->assertSame('20–50 кВт', collect($product->specs)->firstWhere('key', 'Диапазон мощности горелки')['value']);
        $this->assertStringContainsString('OXI%20ARTTHERM.pdf', $product->content_source_url);
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_150_to_200_teplov_adapter_instead_of_linking_the_150_to_160_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-adapter-150-200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Переходы и адаптеры',
            'slug' => 'chimney-adapters-150-200-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-adapter-test']);
        $wrongDiameterCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.995',
            'name' => 'Адаптер-переход Теплов и Сухов Моно М-М 430-0.8, Ø 150/160',
            'slug' => 'teplov-adapter-150-160-template',
            'price' => 30.52,
            'images' => ['product/008/008995/adapter.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-150-200',
            'external_code' => 'УТ-00009589',
            'external_sku' => 'TS.FRT.AKP.0150.61096',
            'name' => 'Адаптер переход Моно М-М (С) 430-0.8 D150/200',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongDiameterCandidate->id, 'score' => 0.7532]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-t',
            '--code' => ['УТ-00009589'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongDiameterCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер-переход Моно М-М (С) 430-0.8 D150/200', $product->name);
        $this->assertSame('41.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Переход с')['value']);
        $this->assertSame('200 мм', collect($product->specs)->firstWhere('key', 'Переход на')['value']);
        $this->assertSame('TS.FRT.AKP.0150.61096', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertStringContainsString('ts-frt-akp-0150-61096', mb_strtolower($product->content_source_url));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }
}

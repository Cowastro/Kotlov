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
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);

        $product->forceFill(['content' => '<p>Служебное описание цены и сопоставления.</p>'])->save();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-t',
            '--code' => ['УТ-00009589'],
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertStringContainsString('плавное соединение', $product->fresh()->content);
        $this->assertStringNotContainsString('Служебное', $product->fresh()->content);
    }

    public function test_it_creates_the_exact_aisi_304_l500_pipe_instead_of_linking_the_aisi_430_l1000_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-pipe-304-l500-d115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'mono-pipes-304-l500-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-pipe-test']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.743',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D115',
            'slug' => 'teplov-pipe-430-l1000-d115-template',
            'price' => 45,
            'images' => ['product/009/009743/pipe.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-pipe-304-l500-d115',
            'external_code' => 'УТ-00009115',
            'external_sku' => 'TS.ST5.TRB.0115.79182',
            'name' => 'Труба L500 ТМ-Р 304-0.8 D115 (У)',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.7529]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-u',
            '--code' => ['УТ-00009115'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба L500 ТМ-Р 304-0.8 D115 (У)', $product->name);
        $this->assertSame('65.50', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('TS.ST5.TRB.0115.79182', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-truba-l500-tm-r-304-08-d115.png'], $product->images);
        $this->assertStringContainsString('truba-mono-l500-tm-r-304-0-8-d-115', $product->content_source_url);
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);

        $product->forceFill(['images' => ['https://pechki66.ru/watermarked-product.jpg']])->save();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-u',
            '--code' => ['УТ-00009115'],
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(['img/products/generated/teplov-suhov-truba-l500-tm-r-304-08-d115.png'], $product->fresh()->images);
    }

    public function test_it_creates_the_exact_aisi_304_chimney_cap_instead_of_linking_the_ferrum_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-cap-304-d115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zonty-deflektory'], [
            'name' => 'Зонты и дефлекторы',
            'parent_id' => 0,
        ]);
        $ferrum = Brand::query()->create(['name' => 'FERRUM', 'slug' => 'ferrum-cap-test']);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongSteelCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ferrum->id,
            'sku' => 'PS-007.806',
            'name' => 'Зонт-К FERRUM (430/0,5) Ф115',
            'slug' => 'ferrum-cap-430-d115-template',
            'price' => 28,
            'images' => ['product/007/007806/cap.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-cap-304-d115',
            'external_code' => 'УТ-00008318',
            'external_sku' => 'TS.ST5.ZNT.0115.32496',
            'name' => 'Зонт ЗМ-Р 304-0.5 D115',
            'price_byn' => null,
            'stock_quantity' => 5,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongSteelCandidate->id, 'score' => 0.72]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-v',
            '--code' => ['УТ-00008318'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongSteelCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Зонт ЗМ-Р 304-0.5 D115', $product->name);
        $this->assertSame('31.70', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,5 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.ZNT.0115.32496', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-zont-zm-r-304-05-d115.png'], $product->images);
        $this->assertStringContainsString('zont-mono-zm-r-304-0-5-d-115', $product->content_source_url);
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);

        $product->forceFill(['images' => ['https://pechki66.ru/watermarked-product.jpg']])->save();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-v',
            '--code' => ['УТ-00008318'],
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame(['img/products/generated/teplov-suhov-zont-zm-r-304-05-d115.png'], $product->fresh()->images);
    }

    public function test_it_creates_the_1000va_solpi_stabilizer_instead_of_linking_the_15000va_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-solpi-m-1000va',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Стабилизаторы напряжения',
            'slug' => 'voltage-stabilizers-solpi-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Solpi-M', 'slug' => 'solpi-m-1000va-test']);
        $wrongPowerCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-002.374',
            'name' => 'Стабилизатор напряжения Solpi-M SLP-M 15000VA',
            'slug' => 'solpi-m-slp-m-15000va-template',
            'price' => 950,
            'images' => ['product/002/002374/solpi-15000va.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'solpi-m-slp-m-1000va',
            'external_code' => 'УТ-00008302',
            'name' => 'Стабилизатор напряжения Solpi-m SLP-M 1.000VA',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPowerCandidate->id, 'score' => 0.99]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-w',
            '--code' => ['УТ-00008302'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPowerCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Solpi-M SLP-M 1000VA электромеханический стабилизатор напряжения', $product->name);
        $this->assertSame('245.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('1000 ВА', collect($product->specs)->firstWhere('key', 'Номинальная мощность')['value']);
        $this->assertSame('150–260 В', collect($product->specs)->firstWhere('key', 'Входное напряжение')['value']);
        $this->assertSame(['img/products/generated/solpi-m-slp-m-1000va.png'], $product->images);
        $this->assertStringContainsString('slp_m_1000va', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_310_l500_pipe_instead_of_linking_the_aisi_430_l1000_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-energo-310-l500-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'mono-pipes-310-l500-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-energo-test']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.742',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D150',
            'slug' => 'teplov-pipe-430-l1000-d150-template',
            'price' => 80,
            'images' => ['product/009/009742/pipe.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-energo-pipe-310-l500-d150',
            'external_code' => 'УТ-00008128',
            'external_sku' => 'TS.ENG.TRB.0150.54823',
            'name' => 'Труба L500 ТМ-Р 310-0.8 D150',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.78]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-x',
            '--code' => ['УТ-00008128'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба L500 ТМ-Р 310-0.8 D150 «Энерго»', $product->name);
        $this->assertSame('143.50', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 310', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('TS.ENG.TRB.0150.54823', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-truba-l500-tm-r-310-08-d150.png'], $product->images);
        $this->assertStringContainsString('/9972/', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_l250_pipe_instead_of_linking_the_aisi_430_l1000_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-standard-304-l250-d115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'mono-pipes-304-l250-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-standard-l250-test']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.743',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D115',
            'slug' => 'teplov-pipe-430-l1000-d115-template-for-l250',
            'price' => 70,
            'images' => ['product/009/009743/pipe.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-standard-pipe-304-l250-d115',
            'external_code' => 'УТ-00007827',
            'external_sku' => 'TS.ST5.TRB.0115.79173',
            'name' => 'Труба L250 ТМ-Р 304-0.8 D115 (У)',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.73]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-y',
            '--code' => ['УТ-00007827'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба L250 ТМ-Р 304-0.8 D115 (У)', $product->name);
        $this->assertSame('31.30', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('250 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('TS.ST5.TRB.0115.79173', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-truba-l250-tm-r-304-08-d115.png'], $product->images);
        $this->assertStringContainsString('truba-mono-l250', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_l1000_pipe_instead_of_linking_the_aisi_430_l1000_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-standard-304-l1000-d115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Трубы моно',
            'slug' => 'mono-pipes-304-l1000-test',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov-standard-l1000-test']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.743',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D115',
            'slug' => 'teplov-pipe-430-l1000-d115-template-for-304',
            'price' => 70,
            'images' => ['https://pechki66.ru/watermarked-product.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-standard-pipe-304-l1000-d115',
            'external_code' => 'УТ-00007501',
            'external_sku' => 'TS.ST5.TRB.0115.79161',
            'name' => 'Труба L1000 ТМ-Р 304-0.8 D115 (У)',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.84]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-z',
            '--code' => ['УТ-00007501'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба L1000 ТМ-Р 304-0.8 D115 (У)', $product->name);
        $this->assertSame('98.30', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.TRB.0115.79161', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-truba-l1000-tm-r-304-08-d115.png'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertStringContainsString('tis-standart-50', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_d150_adapter_instead_of_linking_the_ferrum_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-adapter-304-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $ferrum = Brand::query()->create(['name' => 'FERRUM', 'slug' => 'ferrum-adapter-304-test']);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAdapterCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ferrum->id,
            'sku' => 'PS-007.784',
            'name' => 'Адаптер ММ FERRUM (430/0,8мм) Ф150',
            'slug' => 'ferrum-adapter-430-d150-template',
            'price' => 25,
            'images' => ['https://pechki66.ru/watermarked-adapter.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-304-d150',
            'external_code' => 'УТ-00007186',
            'external_sku' => 'TS.ST5.AKL.0150.32871',
            'name' => 'Адаптер Моно М-М 304-0.8 D150',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAdapterCandidate->id, 'score' => 0.72]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-aa',
            '--code' => ['УТ-00007186'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAdapterCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер Моно М-М 304-0.8 D150', $product->name);
        $this->assertSame('29.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.AKL.0150.32871', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d150.png'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertStringContainsString('vseinstrumenti.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_mono_thermo_transition_instead_of_linking_the_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-pmt-304-d115-210',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $wrongCategory = Category::query()->create([
            'name' => 'Заглушки и оголовки',
            'slug' => 'zaglushki-dymohod-pmt-test',
            'parent_id' => 0,
        ]);
        $correctCategory = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongTransitionCandidate = Product::query()->create([
            'category_id' => $wrongCategory->id,
            'brand_id' => $teplov->id,
            'sku' => 'KOTLOV-008031',
            'name' => 'Теплов и Сухов Переход Моно/Термо ПМТ-Р 430-0.8/430 D115/210',
            'slug' => 'teplov-pmt-430-d115-210-template-for-304',
            'price' => 70,
            'images' => ['https://pechki66.ru/watermarked-transition.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-pmt-304-d115-210',
            'external_code' => 'УТ-00006949',
            'external_sku' => 'TS.ST5.PRT.0115.49262',
            'name' => 'Переход Моно/Термо ПМТ-Р 304-0.8/304 D115/210*',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongTransitionCandidate->id, 'score' => 0.6596]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ab',
            '--code' => ['УТ-00006949'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongTransitionCandidate->id, $product->id);
        $this->assertSame($correctCategory->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Переход Моно/Термо ПМТ-Р 304-0.8/304-0.5 D115/210', $product->name);
        $this->assertSame('109.00', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('210 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('Нержавеющая сталь AISI 304', collect($product->specs)->firstWhere('key', 'Материал внутренней трубы')['value']);
        $this->assertSame('Нержавеющая сталь AISI 304', collect($product->specs)->firstWhere('key', 'Материал наружной трубы')['value']);
        $this->assertSame('TS.ST5.PRT.0115.49262', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-perehod-mono-termo-pmt-r-304-08-304-05-d115-210.png'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertStringContainsString('profsnabeng.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_d115_adapter_instead_of_linking_the_ferrum_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-adapter-304-d115',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $ferrum = Brand::query()->create(['name' => 'FERRUM', 'slug' => 'ferrum-adapter-304-d115-test']);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAdapterCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ferrum->id,
            'sku' => 'PS-007.802',
            'name' => 'Адаптер ММ FERRUM (430/0,8мм) Ф115',
            'slug' => 'ferrum-adapter-430-d115-template-for-304',
            'price' => 20,
            'images' => ['https://pechki66.ru/watermarked-adapter-d115.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-304-d115',
            'external_code' => 'УТ-00006945',
            'external_sku' => 'TS.ST5.AKL.0115.79296',
            'name' => 'Адаптер Моно М-М 304-0.8 D115 (У)',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAdapterCandidate->id, 'score' => 0.6966]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ac',
            '--code' => ['УТ-00006945'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAdapterCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер Моно М-М 304-0.8 D115 (У)', $product->name);
        $this->assertSame('24.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.AKL.0115.79296', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d115.png'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertStringContainsString('ivkamin.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_oxi_upper_grate_with_a_clean_local_image_instead_of_linking_the_legend_grate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-oxi-upper-grate',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'pelletnye-gorelki'], [
            'name' => 'Пеллетные горелки',
            'parent_id' => 0,
        ]);
        $legend = Brand::query()->create(['name' => 'Legend', 'slug' => 'legend-upper-grate-test']);
        $oxi = Brand::query()->firstOrCreate(['slug' => 'oxi'], ['name' => 'OXI']);
        $wrongGrateCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $legend->id,
            'sku' => 'KOTLOV-004507',
            'name' => 'Колосник легенда 16 (280*115)',
            'slug' => 'legend-16-grate-template-for-oxi-upper-grate',
            'price' => 35,
            'images' => ['https://pechki66.ru/watermarked-grate.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'oxi-upper-grate-75-1000',
            'external_code' => 'УТ-00006724',
            'external_sku' => null,
            'name' => 'Колосник верхний 75/1000',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongGrateCandidate->id, 'score' => 0.6667]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ad',
            '--code' => ['УТ-00006724'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongGrateCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($oxi->id, $product->brand_id);
        $this->assertSame('OXI Колосник верхнего ряда для пеллетной горелки Ceramik+ 75–1000 кВт', $product->name);
        $this->assertSame('109.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('AISI 310S', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('6 мм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame('03040059', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/oxi/oxi-ceramik-plus-upper-grate-75-1000.jpg'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertFalse(str_contains(implode(' ', $product->images), 'prom.ua'));
        $this->assertFalse(str_contains(implode(' ', $product->images), 'bigl.ua'));
        $this->assertStringContainsString('bigl.ua', $product->content_source_url);
        $this->assertStringContainsString('снятой с производства в 2020 году', mb_strtolower($product->content));
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_oxi_lower_grate_with_a_clean_local_image_instead_of_linking_the_legend_grate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-oxi-lower-grate',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'pelletnye-gorelki'], [
            'name' => 'Пеллетные горелки',
            'parent_id' => 0,
        ]);
        $legend = Brand::query()->create(['name' => 'Legend', 'slug' => 'legend-lower-grate-test']);
        $oxi = Brand::query()->firstOrCreate(['slug' => 'oxi'], ['name' => 'OXI']);
        $wrongGrateCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $legend->id,
            'sku' => 'KOTLOV-004507',
            'name' => 'Колосник легенда 16 (280*115)',
            'slug' => 'legend-16-grate-template-for-oxi-lower-grate',
            'price' => 35,
            'images' => ['https://pechki66.ru/watermarked-grate.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'oxi-lower-grate-75-1000',
            'external_code' => 'УТ-00006723',
            'external_sku' => null,
            'name' => 'Колосник нижний 75/1000',
            'price_byn' => null,
            'stock_quantity' => 6,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongGrateCandidate->id, 'score' => 0.6835]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ae',
            '--code' => ['УТ-00006723'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongGrateCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($oxi->id, $product->brand_id);
        $this->assertSame('OXI Колосник нижнего ряда для пеллетной горелки Ceramik+ 75–1000 кВт', $product->name);
        $this->assertSame('119.00', $product->price);
        $this->assertSame('6', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('AISI 310S', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('6 мм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame('03040060', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/oxi/oxi-ceramik-plus-lower-grate-75-1000.jpg'], $product->images);
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertFalse(str_contains(implode(' ', $product->images), 'prom.ua'));
        $this->assertFalse(str_contains(implode(' ', $product->images), 'bigl.ua'));
        $this->assertStringContainsString('bigl.ua', $product->content_source_url);
        $this->assertStringContainsString('снятой с производства в 2020 году', mb_strtolower($product->content));
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_solpi_slp_500_mini_instead_of_linking_the_separate_new_model(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-solpi-slp-500-mini',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'stabilizatory-napryazheniya'], [
            'name' => 'Стабилизаторы напряжения',
            'parent_id' => 0,
        ]);
        $solpi = Brand::query()->firstOrCreate(['slug' => 'solpi'], ['name' => 'Solpi']);
        $wrongNewCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $solpi->id,
            'sku' => 'PS-011.932',
            'name' => 'Стабилизатор напряжения Solpi-M SLP 500 ВА NEW',
            'slug' => 'solpi-slp-500-new-template-for-mini',
            'price' => 179,
            'images' => ['product/0011/011932/64f044882091c.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'solpi-slp-500-mini',
            'external_code' => 'УТ-00005747',
            'external_sku' => null,
            'name' => 'Стабилизатор напряжения Solpi-m  SLP-500ВА Mini',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongNewCandidate->id, 'score' => 0.9571]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-af',
            '--code' => ['УТ-00005747'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongNewCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($solpi->id, $product->brand_id);
        $this->assertSame('Solpi-M SLP-500VA Mini, стабилизатор напряжения 500 ВА', $product->name);
        $this->assertSame('169.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('SLP-500VA Mini', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('500 ВА', collect($product->specs)->firstWhere('key', 'Полная мощность')['value']);
        $this->assertSame('150–260 В', collect($product->specs)->firstWhere('key', 'Входное напряжение')['value']);
        $this->assertSame('2', collect($product->specs)->firstWhere('key', 'Выходные розетки')['value']);
        $this->assertSame(['img/products/solpi/solpi-m-slp-500-mini.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/solpi/solpi-m-slp-500-mini.jpg'));
        $this->assertStringContainsString('gss56.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_aisi_304_d160_180_adapter_instead_of_linking_the_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-adapter-304-d160-180',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAisi430Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-008.996',
            'name' => 'Адаптер-переход Теплов и Сухов Моно АПМ-Р 430-0.8 D160/200',
            'slug' => 'teplov-adapter-430-d160-200-template-for-304-d160-180',
            'price' => 28,
            'images' => ['https://pechki66.ru/watermarked-adapter-160-200.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-304-d160-180',
            'external_code' => 'УТ-00005720',
            'external_sku' => null,
            'name' => 'Адаптер переход Моно М-М 304-0.8 D160/180',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAisi430Candidate->id, 'score' => 0.7815]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ag',
            '--code' => ['УТ-00005720'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAisi430Candidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер-переход Моно М-М 304-0.8 D160/180', $product->name);
        $this->assertSame('39.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('160 мм', collect($product->specs)->firstWhere('key', 'Входной диаметр')['value']);
        $this->assertSame('180 мм', collect($product->specs)->firstWhere('key', 'Выходной диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.AKP.0160.66160', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d160-180.png'], $product->images);
        $this->assertFileExists(public_path('img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d160-180.png'));
        $this->assertFalse(str_contains(implode(' ', $product->images), 'pechki66.ru'));
        $this->assertStringContainsString('teplov.nt-rt.ru', $product->content_source_url);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нельзя объединять', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_metallo_as_a_bimetallic_single_section_instead_of_linking_rifar(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-atm-metallo-500-80',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'bimetallicheskie-radiatory'], [
            'name' => 'Биметаллические радиаторы',
            'parent_id' => 0,
        ]);
        $rifar = Brand::query()->create(['slug' => 'rifar', 'name' => 'Rifar']);
        $wrongRifarCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $rifar->id,
            'sku' => 'PS-010.410',
            'name' => 'Радиатор биметаллический Rifar SUPReMO 500, 1 секция',
            'slug' => 'rifar-supremo-template-for-metallo',
            'price' => 55,
            'images' => ['product/0010/010410/rifar.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'atm-metallo-500-80-one-section',
            'external_code' => 'УТ-00005614',
            'external_sku' => null,
            'name' => 'Радиатор отопления алюминиевый "Metallo" 500/80 1 секция, РФ',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongRifarCandidate->id, 'score' => 0.6957]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ah',
            '--code' => ['УТ-00005614'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongRifarCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ATM Thermo', $product->brand->name);
        $this->assertSame('ATM Thermo Metallo 500/80, биметаллический радиатор, 1 секция', $product->name);
        $this->assertSame('32.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Секционный биметаллический радиатор', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('1', collect($product->specs)->firstWhere('key', 'Количество секций')['value']);
        $this->assertSame('138 Вт', collect($product->specs)->firstWhere('key', 'Номинальный тепловой поток одной секции')['value']);
        $this->assertSame('24 бар', collect($product->specs)->firstWhere('key', 'Рабочее давление')['value']);
        $this->assertSame(['img/products/atm-thermo/atm-thermo-metallo-500-80.webp'], $product->images);
        $this->assertFileExists(public_path('img/products/atm-thermo/atm-thermo-metallo-500-80.webp'));
        $this->assertStringContainsString('progreem.by', $product->content_source_url);
        $this->assertStringContainsString('Цена указана за одну секцию', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_energia_as_an_atm_aluminium_single_section_instead_of_linking_royal_thermo(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-atm-energia-500-100',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'alyuminievye-radiatory'], [
            'name' => 'Алюминиевые радиаторы',
            'parent_id' => 0,
        ]);
        $royalThermo = Brand::query()->create(['slug' => 'royal-thermo', 'name' => 'Royal Thermo']);
        $wrongRoyalThermoCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $royalThermo->id,
            'sku' => 'KOTLOV-001413',
            'name' => 'Радиатор алюминиевый Royal Thermo BILINER A 500, 10 секций',
            'slug' => 'royal-thermo-biliner-template-for-energia',
            'price' => 500,
            'images' => ['product/0014/001413/royal-thermo.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'atm-energia-500-100-one-section',
            'external_code' => 'УТ-00005613',
            'external_sku' => null,
            'name' => 'Радиатор отопления алюминиевый "Energia" 500/100 1 секция, РФ',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongRoyalThermoCandidate->id, 'score' => 0.7093]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ai',
            '--code' => ['УТ-00005613'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongRoyalThermoCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ATM Thermo', $product->brand->name);
        $this->assertSame('ATM Thermo Energia 500/100, алюминиевый радиатор, 1 секция', $product->name);
        $this->assertSame('29.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Секционный алюминиевый радиатор', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('172 Вт', collect($product->specs)->firstWhere('key', 'Номинальный тепловой поток одной секции')['value']);
        $this->assertSame('16 бар', collect($product->specs)->firstWhere('key', 'Рабочее давление')['value']);
        $this->assertSame(['img/products/atm-thermo/atm-thermo-energia-500-100.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/atm-thermo/atm-thermo-energia-500-100.jpg'));
        $this->assertStringContainsString('progreem.by', $product->content_source_url);
        $this->assertStringContainsString('Цена указана за одну секцию', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_corrects_modemo_to_atm_moderno_and_does_not_link_rifar(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-atm-moderno-500-80',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'alyuminievye-radiatory'], [
            'name' => 'Алюминиевые радиаторы',
            'parent_id' => 0,
        ]);
        $rifar = Brand::query()->create(['slug' => 'rifar', 'name' => 'Rifar']);
        $wrongRifarCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $rifar->id,
            'sku' => 'PS-010.410',
            'name' => 'Радиатор биметаллический Rifar SUPReMO 500, 1 секция',
            'slug' => 'rifar-supremo-template-for-moderno',
            'price' => 55,
            'images' => ['product/0010/010410/rifar.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'atm-modemo-500-80-one-section',
            'external_code' => 'УТ-00005612',
            'external_sku' => null,
            'name' => 'Радиатор отопления алюминиевый "Modemo" 500/80 1 секция, РФ',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongRifarCandidate->id, 'score' => 0.7125]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-aj',
            '--code' => ['УТ-00005612'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongRifarCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ATM Thermo', $product->brand->name);
        $this->assertSame('ATM Thermo Moderno 500/80, алюминиевый радиатор, 1 секция', $product->name);
        $this->assertSame('27.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Moderno 500/80', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('Секционный алюминиевый радиатор', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('149 Вт', collect($product->specs)->firstWhere('key', 'Номинальный тепловой поток одной секции')['value']);
        $this->assertSame('16 бар', collect($product->specs)->firstWhere('key', 'Рабочее давление')['value']);
        $this->assertSame(['img/products/atm-thermo/atm-thermo-moderno-500-80.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/atm-thermo/atm-thermo-moderno-500-80.jpg'));
        $this->assertStringContainsString('progreem.by', $product->content_source_url);
        $this->assertStringContainsString('Цена указана за одну секцию', $product->content);
        $this->assertStringNotContainsString('Modemo', $product->name);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_darco_reverse_reducer_instead_of_linking_the_120_mm_variant(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-darco-rd150-200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $darco = Brand::query()->create(['slug' => 'darco', 'name' => 'DARCO']);
        $wrongDiameterCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $darco->id,
            'sku' => 'PS-009.425',
            'name' => 'Редукция Darco RD150/120-CZ2',
            'slug' => 'darco-rd150-120-template',
            'price' => 65,
            'images' => ['product/0009/009425/darco.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'darco-rd150-200-cz2-ii',
            'external_code' => 'УТ-00005568',
            'external_sku' => null,
            'name' => 'Редуктор RD150/200- CZ2/II',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongDiameterCandidate->id, 'score' => 0.75]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ak',
            '--code' => ['УТ-00005568'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongDiameterCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('DARCO', $product->brand->name);
        $this->assertSame('DARCO Редукция обратная RD150/200-CZ2/II, Ø150/200 мм, 2 мм', $product->name);
        $this->assertSame('84.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Обратная дымоходная редукция', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('200 мм', collect($product->specs)->firstWhere('key', 'Диаметр со стороны дымохода')['value']);
        $this->assertSame('2 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('600 °C', collect($product->specs)->firstWhere('key', 'Максимальная температура')['value']);
        $this->assertSame(['img/products/darco/darco-rd150-200-cz2-ii.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/darco/darco-rd150-200-cz2-ii.jpg'));
        $this->assertStringContainsString('sklep.darco.pl', $product->content_source_url);
        $this->assertStringContainsString('не взаимозаменяемы', $product->content);
        $this->assertStringNotContainsString('120 мм', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('pln', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_diamond_dm02_boiler_blower_instead_of_linking_ballu(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-diamond-dm02',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], [
            'name' => 'Автоматика и терморегуляторы',
            'parent_id' => 0,
        ]);
        $ballu = Brand::query()->create(['slug' => 'ballu', 'name' => 'Ballu']);
        $wrongBalluCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ballu->id,
            'sku' => 'KOTLOV-003041',
            'name' => 'Вентилятор промышленный центробежный Ballu BIF-10SB',
            'slug' => 'ballu-bif-10sb-template-for-diamond-dm02',
            'price' => 700,
            'images' => ['product/0030/003041/ballu.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'diamond-dm02-boiler-blower',
            'external_code' => 'УТ-00005506',
            'external_sku' => null,
            'name' => 'Вентилятор центробежный DM02',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongBalluCandidate->id, 'score' => 0.6909]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-al',
            '--code' => ['УТ-00005506'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongBalluCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Diamond', $product->brand->name);
        $this->assertSame('Diamond DM-02, центробежный вентилятор наддува для котла', $product->name);
        $this->assertSame('159.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('R20/84', collect($product->specs)->firstWhere('key', 'Двигатель')['value']);
        $this->assertSame('70 Вт', collect($product->specs)->firstWhere('key', 'Потребляемая мощность')['value']);
        $this->assertSame('175 м³/ч', collect($product->specs)->firstWhere('key', 'Максимальная производительность')['value']);
        $this->assertSame('215 Па', collect($product->specs)->firstWhere('key', 'Максимальное давление')['value']);
        $this->assertSame(['img/products/diamond/diamond-dm-02.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/diamond/diamond-dm-02.jpg'));
        $this->assertStringContainsString('pex.pl', $product->content_source_url);
        $this->assertStringNotContainsString('Ballu', $product->name);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('pln', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_aisi_304_d150_shiber_instead_of_linking_the_aisi_430_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-shiber-304-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'shibery-dymohod'], [
            'name' => 'Шиберы и задвижки',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAisi430Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-009.741',
            'name' => 'Шибер ШМ-Р (430-1,0) D150',
            'slug' => 'teplov-shiber-430-10-d150-template',
            'price' => 55,
            'images' => ['product/0009/009741/shiber-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-shiber-304-08-d150',
            'external_code' => 'УТ-00005212',
            'external_sku' => 'TS.ST5.SHR.0150.70287',
            'name' => 'Шибер ШМ(М)-Р 304-0.8 D150 (У)',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAisi430Candidate->id, 'score' => 0.82]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-am',
            '--code' => ['УТ-00005212'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAisi430Candidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Шибер Моно ШМ(М)-Р 304-0.8 D150 (У)', $product->name);
        $this->assertSame('69.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Поворотный шибер', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр дымохода')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.SHR.0150.70287', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-shiber-shmm-304-08-d150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-shiber-shmm-304-08-d150.jpg'));
        $this->assertStringContainsString('woodson.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_aisi_304_d150_250_mono_thermo_transition(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-pmt-304-d150-250',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAisi430Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-009.149',
            'name' => 'Переход Моно/Термо Теплов и Сухов ПМТ(М)-Р 430-0.8/430 D150/210',
            'slug' => 'teplov-pmt-430-d150-210-template-for-304-d150-250',
            'price' => 75,
            'images' => ['product/0009/009149/pmt-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-pmt-304-d150-250',
            'external_code' => 'УТ-00005207',
            'external_sku' => 'TS.ST5.PRT.0150.49266',
            'name' => 'Переход Моно/Термо ПМТ-Р 304-0.8/304 D150/250',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAisi430Candidate->id, 'score' => 0.73]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-an',
            '--code' => ['УТ-00005207'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAisi430Candidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Переход Моно/Термо ПМТ-Р 304-0.8/304 D150/250', $product->name);
        $this->assertSame('129.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('250 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали внутреннего контура')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали внутреннего контура')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали наружного контура')['value']);
        $this->assertSame('TS.ST5.PRT.0150.49266', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-pmt-304-08-304-d150-250.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-pmt-304-08-304-d150-250.jpg'));
        $this->assertStringContainsString('banikaminy.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_l500_aisi_304_d150_pipe_instead_of_linking_l1000_aisi_430(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-pipe-l500-304-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'truby-mono'], [
            'name' => 'Трубы одностенные',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-009.742',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D150',
            'slug' => 'teplov-pipe-l1000-430-10-d150-template',
            'price' => 65,
            'images' => ['product/0009/009742/pipe-l1000-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-pipe-l500-304-08-d150',
            'external_code' => 'УТ-00005205',
            'external_sku' => 'TS.ST5.TRB.0150.54778',
            'name' => 'Труба L500 ТМ-Р 304-0.8 D150',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.78]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ao',
            '--code' => ['УТ-00005205'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба Моно L500 ТМ-Р 304-0.8 D150', $product->name);
        $this->assertSame('84.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.TRB.0150.54778', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-truba-l500-304-08-d150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-truba-l500-304-08-d150.jpg'));
        $this->assertStringContainsString('pechi-online.ru', $product->content_source_url);
        $this->assertStringNotContainsString('L1000', $product->content);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_aisi_304_d120_45_degree_elbow_instead_of_linking_aisi_430(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-elbow-45-304-d120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'kolena-mono'], [
            'name' => 'Отводы одностенные',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongElbowCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'KOTLOV-007989',
            'name' => 'Теплов и Сухов Отвод 45* ОМ-Р 430-0.5 D120 (2S)',
            'slug' => 'teplov-elbow-45-430-05-d120-template',
            'price' => 30,
            'images' => ['product/0079/007989/elbow-45-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-elbow-45-304-08-d120-2s',
            'external_code' => 'УТ-00005204',
            'external_sku' => 'TS.ST5.O45.0120.75261',
            'name' => 'Отвод 45* ОМ-Р 304-0.8 D120 (2S)',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongElbowCandidate->id, 'score' => 0.69]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ap',
            '--code' => ['УТ-00005204'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongElbowCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Отвод 45° Моно ОМ-Р 304-0.8 D120 (2S)', $product->name);
        $this->assertSame('49.90', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('45°', collect($product->specs)->firstWhere('key', 'Угол поворота')['value']);
        $this->assertSame('2', collect($product->specs)->firstWhere('key', 'Количество сегментов')['value']);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.O45.0120.75261', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-otvod-45-304-08-d120-2s.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-otvod-45-304-08-d120-2s.jpg'));
        $this->assertStringContainsString('pechi-online.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('0,5 мм', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_aisi_304_d120_87_degree_tee_instead_of_linking_aisi_430(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-tee-87-304-d120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'troyniki-mono'], [
            'name' => 'Тройники одностенные',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongTeeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'KOTLOV-007990',
            'name' => 'Теплов и Сухов Тройник 87* ТРМ(М)-Р 430-0.5 D120',
            'slug' => 'teplov-tee-87-430-05-d120-template',
            'price' => 55,
            'images' => ['product/0079/007990/tee-87-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-tee-87-304-08-d120',
            'external_code' => 'УТ-00005165',
            'external_sku' => 'TS.ST5.T87.0120.66106',
            'name' => 'Тройник 87* ТРМ(М)-Р 304-0.8 D120',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongTeeCandidate->id, 'score' => 0.73]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-aq',
            '--code' => ['УТ-00005165'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongTeeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Тройник 87° Моно ТРМ(М)-Р 304-0.8 D120', $product->name);
        $this->assertSame('89.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('87°', collect($product->specs)->firstWhere('key', 'Угол ответвления')['value']);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.T87.0120.66106', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-troynik-87-304-08-d120.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-troynik-87-304-08-d120.jpg'));
        $this->assertStringContainsString('realkamin.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('0,5 мм', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_l1000_aisi_304_d150_pipe_instead_of_linking_aisi_430(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-pipe-l1000-304-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'truby-mono'], [
            'name' => 'Трубы одностенные',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongPipeCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-009.742',
            'name' => 'Труба моно L1000 ТМ-Р (430-1,0) D150',
            'slug' => 'teplov-pipe-l1000-430-10-d150-template',
            'price' => 65,
            'images' => ['product/0009/009742/pipe-l1000-430.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-pipe-l1000-304-08-d150',
            'external_code' => 'УТ-00005071',
            'external_sku' => 'TS.ST5.TRB.0150.54793',
            'name' => 'Труба L1000 ТМ-Р 304-0.8 D150',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongPipeCandidate->id, 'score' => 0.82]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ar',
            '--code' => ['УТ-00005071'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongPipeCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Труба Моно L1000 ТМ-Р 304-0.8 D150', $product->name);
        $this->assertSame('129.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.TRB.0150.54793', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-suhov-truba-l1000-304-08-d150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-suhov-truba-l1000-304-08-d150.jpg'));
        $this->assertStringContainsString('banikaminy.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('1,0 мм', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_reuses_the_existing_electrolux_eft_rr_card_instead_of_creating_a_duplicate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrolux-eft-rr',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'obogrevateli'], [
            'name' => 'Обогреватели',
            'parent_id' => 0,
        ]);
        $convectorCategory = Category::query()->firstOrCreate(['slug' => 'elektricheskie-konvektoryi'], [
            'name' => 'Электрические конвекторы',
            'parent_id' => 0,
        ]);
        $electrolux = Brand::query()->firstOrCreate(['slug' => 'electrolux'], ['name' => 'Electrolux']);
        $wrongSupportCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'KOTLOV-000745',
            'name' => 'Комплект опор Electrolux EFA2',
            'slug' => 'electrolux-efa2-support-template',
            'price' => 25,
            'images' => ['product/0000/000745/electrolux-efa2.jpg'],
        ]);
        $exactExisting = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'KOTLOV-000726',
            'name' => 'Electrolux EFT/RR',
            'slug' => 'electrolux-eftrr',
            'price' => 33,
            'images' => ['product/0000/000726/electrolux-eft-rr.jpg'],
            'content' => '<p>Ошибочно описан как обогреватель.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'electrolux-eft-rr-chassis',
            'external_code' => 'УТ-00004685',
            'external_sku' => null,
            'name' => 'Комплект шасси EFT/RR для напольной установки конвектора Electrolux Rapid Transformer',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongSupportCandidate->id, 'score' => 0.7968]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-as',
            '--code' => ['УТ-00004685'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactExisting->id, $product->id);
        $this->assertNotSame($wrongSupportCandidate->id, $product->id);
        $this->assertSame($convectorCategory->id, $product->category_id);
        $this->assertSame($electrolux->id, $product->brand_id);
        $this->assertSame('Electrolux EFT/RR комплект шасси для конвектора Rapid Transformer', $product->name);
        $this->assertSame('33.00', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('EFT/RR', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('НС-1117334', collect($product->specs)->firstWhere('key', 'Код товара')['value']);
        $this->assertSame('Rapid Transformer System', collect($product->specs)->firstWhere('key', 'Серия')['value']);
        $this->assertSame('2 колесные опоры', collect($product->specs)->firstWhere('key', 'Комплект')['value']);
        $this->assertSame(['img/products/electrolux/electrolux-eft-rr-chassis.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/electrolux/electrolux-eft-rr-chassis.jpg'));
        $this->assertStringContainsString('climat-profit.by', $product->content_source_url);
        $this->assertStringNotContainsString('EFA2', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_search', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_ballu_bec_wf_01_module_instead_of_linking_hommyn(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-ballu-bec-wf-01',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'obogrevateli'], [
            'name' => 'Обогреватели',
            'parent_id' => 0,
        ]);
        $ballu = Brand::query()->firstOrCreate(['slug' => 'ballu'], ['name' => 'Ballu']);
        $wrongHommynCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $ballu->id,
            'sku' => 'KOTLOV-003695',
            'name' => 'Модуль съемный управляющий HOMMYN HDN/WFN-02-02',
            'slug' => 'hommyn-hdn-wfn-02-02-template',
            'price' => 50,
            'images' => ['product/0036/003695/hommyn-wifi.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'ballu-bec-wf-01',
            'external_code' => 'УТ-00004679',
            'external_sku' => null,
            'name' => 'Модуль съёмный управляющий Ballu Smart WI-FI BEC/WF-01',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongHommynCandidate->id, 'score' => 0.77]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-at',
            '--code' => ['УТ-00004679'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongHommynCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($ballu->id, $product->brand_id);
        $this->assertSame('Ballu Smart Wi-Fi BEC/WF-01 съёмный управляющий модуль', $product->name);
        $this->assertSame('64.90', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('BEC/WF-01', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('НС-1102775', collect($product->specs)->firstWhere('key', 'Код товара')['value']);
        $this->assertSame('4620016799352', collect($product->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertSame('USB-A', collect($product->specs)->firstWhere('key', 'Интерфейс подключения')['value']);
        $this->assertSame('IEEE 802.11 b/g/n', collect($product->specs)->firstWhere('key', 'Стандарты Wi-Fi')['value']);
        $this->assertSame(['img/products/ballu/ballu-bec-wf-01-smart-wifi.png'], $product->images);
        $this->assertFileExists(public_path('img/products/ballu/ballu-bec-wf-01-smart-wifi.png'));
        $this->assertStringContainsString('climat-profit.by', $product->content_source_url);
        $this->assertStringNotContainsString('HOMMYN', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_aisi_304_d115_120_adapter_instead_of_linking_aisi_430(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-adapter-304-d115-120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
        ]);
        $teplov = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongAisi430Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $teplov->id,
            'sku' => 'PS-008.992',
            'name' => 'Адаптер переход Моно АПМ-Р 430-0.8 D115/120',
            'slug' => 'teplov-adapter-430-d115-120-template',
            'price' => 26.33,
            'images' => ['product/0008/008992/adapter-430-d115-120.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-adapter-304-d115-120',
            'external_code' => 'УТ-00003994',
            'external_sku' => 'TS.ST5.AKP.0115.79236',
            'name' => 'Адаптер переход Моно М-М 304-0.8 D115/120 (У)',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongAisi430Candidate->id, 'score' => 0.86]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-au',
            '--code' => ['УТ-00003994'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongAisi430Candidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($teplov->id, $product->brand_id);
        $this->assertSame('Теплов и Сухов Адаптер-переход Моно М-М 304-0.8 D115/120 (У)', $product->name);
        $this->assertSame('29.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('М-М', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('115 мм', collect($product->specs)->firstWhere('key', 'Входной диаметр')['value']);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Выходной диаметр')['value']);
        $this->assertSame('AISI 304', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.ST5.AKP.0115.79236', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d115-120.png'], $product->images);
        $this->assertFileExists(public_path('img/products/generated/teplov-suhov-adapter-mono-mm-304-08-d115-120.png'));
        $this->assertStringContainsString('pechiteplov.ru', $product->content_source_url);
        $this->assertStringNotContainsString('AISI 430', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_retra_2_temperature_sensor_instead_of_linking_buderus(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-retra-2-temperature-sensor',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'datchiki'], [
            'name' => 'Датчики',
            'parent_id' => 0,
        ]);
        $buderus = Brand::query()->firstOrCreate(['slug' => 'buderus'], ['name' => 'Buderus']);
        $wrongBuderusCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $buderus->id,
            'sku' => 'PS-004.481',
            'name' => 'Датчик температуры бойлера Buderus AS1',
            'slug' => 'buderus-as1-temperature-sensor-template',
            'price' => 120,
            'images' => ['product/0004/004481/buderus-as1.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'retra-2-temperature-sensor',
            'external_code' => 'УТ-00003789',
            'external_sku' => null,
            'name' => 'Датчик температуры к пульту "Retra-2 (RS-485)"',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongBuderusCandidate->id, 'score' => 0.70]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-av',
            '--code' => ['УТ-00003789'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongBuderusCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('RETRA', $product->brand->name);
        $this->assertSame('RETRA датчик температуры котла для пульта Retra-2 New RS-485, 3 м', $product->name);
        $this->assertSame('69.90', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('Пульт RETRA-2 New RS-485', collect($product->specs)->firstWhere('key', 'Совместимость')['value']);
        $this->assertSame('от −40 до +125 °C', collect($product->specs)->firstWhere('key', 'Диапазон измерения')['value']);
        $this->assertSame('3 м', collect($product->specs)->firstWhere('key', 'Длина кабеля')['value']);
        $this->assertSame(['img/products/generated/retra-2-new-rs485-temperature-sensor.png'], $product->images);
        $this->assertFileExists(public_path('img/products/generated/retra-2-new-rs485-temperature-sensor.png'));
        $this->assertStringContainsString('retra.com.ua', $product->content_source_url);
        $this->assertStringNotContainsString('Buderus', $product->content);
        $this->assertStringNotContainsString('AS1', $product->content);
        $this->assertStringNotContainsString('1-Wire', $product->content);
        $this->assertStringNotContainsString('Dallas', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('грн', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_engel_radiator_instead_of_linking_archived_lemax(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-engel-22-500x500',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'radiatory'], [
            'name' => 'Радиаторы',
            'parent_id' => 0,
        ]);
        $lemax = Brand::query()->firstOrCreate(['slug' => 'lemax'], ['name' => 'LEMAX']);
        $wrongArchivedLemaxCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $lemax->id,
            'sku' => 'PS-008.317',
            'name' => 'Радиатор стальной LEMAX Compact 22 500х500 (боковое)',
            'slug' => 'lemax-compact-22-500x500-archived-template',
            'price' => 190,
            'images' => ['product/008/008317/lemax-compact.png'],
            'is_active' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'engel-22-500x500-side',
            'external_code' => 'УТ-00003377',
            'external_sku' => null,
            'name' => 'Радиатор стальной панельный ENGEL (22) 500x500 бок',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongArchivedLemaxCandidate->id, 'score' => 0.69]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-aw',
            '--code' => ['УТ-00003377'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongArchivedLemaxCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ENGEL', $product->brand->name);
        $this->assertSame('ENGEL тип 22 500×500 стальной панельный радиатор, боковое подключение', $product->name);
        $this->assertSame('189.90', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->is_active);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('22', collect($product->specs)->firstWhere('key', 'Тип панели')['value']);
        $this->assertSame('Боковое', collect($product->specs)->firstWhere('key', 'Подключение')['value']);
        $this->assertSame('904 Вт', collect($product->specs)->firstWhere('key', 'Тепловая мощность при ΔT50')['value']);
        $this->assertSame('8 бар', collect($product->specs)->firstWhere('key', 'Рабочее давление')['value']);
        $this->assertSame(['img/products/generated/engel-type-22-500x500-side-radiator.png'], $product->images);
        $this->assertFileExists(public_path('img/products/generated/engel-type-22-500x500-side-radiator.png'));
        $this->assertStringContainsString('rozetka.com.ua', $product->content_source_url);
        $this->assertStringNotContainsString('LEMAX', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($product->content));
        $this->assertStringNotContainsString('грн', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_creates_the_original_ech_tum_instead_of_linking_ech_tum3(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrolux-ech-tum',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'elektricheskie-konvektoryi'], [
            'name' => 'Электрические конвекторы',
            'parent_id' => 0,
        ]);
        $electrolux = Brand::query()->firstOrCreate(['slug' => 'electrolux'], ['name' => 'Electrolux']);
        $wrongTum3Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'PS-000.18660',
            'name' => 'Блок управления Electrolux ECH/TUM3 Transformer Mechanic',
            'slug' => 'electrolux-ech-tum3-transformer-mechanic-template',
            'price' => 105,
            'images' => ['product/0000/00018660/electrolux-ech-tum3.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'electrolux-ech-tum-original',
            'external_code' => 'УТ-00003236',
            'external_sku' => null,
            'name' => 'Блок управления Transformer Mechanic Electrolux ECH/TUM',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongTum3Candidate->id, 'score' => 0.85]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ax',
            '--code' => ['УТ-00003236'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongTum3Candidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($electrolux->id, $product->brand_id);
        $this->assertSame('Electrolux ECH/TUM Transformer Mechanic механический блок управления', $product->name);
        $this->assertSame('106.90', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->is_active);
        $this->assertNull($item->fresh()->normalizedPriceByn());
        $this->assertSame('ECH/TUM', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('НС-1081907', collect($product->specs)->firstWhere('key', 'Код товара')['value']);
        $this->assertSame('Механический', collect($product->specs)->firstWhere('key', 'Термостат')['value']);
        $this->assertSame('Половинная / полная', collect($product->specs)->firstWhere('key', 'Режимы мощности')['value']);
        $this->assertSame('148 × 91 × 86 мм', collect($product->specs)->firstWhere('key', 'Габариты')['value']);
        $this->assertSame(['img/products/electrolux/electrolux-ech-tum-transformer-mechanic.png'], $product->images);
        $this->assertFileExists(public_path('img/products/electrolux/electrolux-ech-tum-transformer-mechanic.png'));
        $this->assertStringContainsString('aquadom.info', $product->content_source_url);
        $this->assertStringNotContainsString('ECH/TUM3', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertSame('manual_created', $item->fresh()->match_method);
    }

    public function test_it_links_eft_ag2r_to_the_existing_exact_card_without_creating_a_duplicate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-electrolux-eft-ag2r',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'obogrevateli'], [
            'name' => 'Обогреватели',
            'parent_id' => 0,
        ]);
        $electrolux = Brand::query()->firstOrCreate(['slug' => 'electrolux'], ['name' => 'Electrolux']);
        $wrongEfa2Candidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'KOTLOV-000745',
            'name' => 'Комплект опор Electrolux EFA2 для напольной установки',
            'slug' => 'electrolux-efa2-wrong-candidate',
            'price' => 30,
            'images' => ['product/electrolux/efa2.jpg'],
        ]);
        $exactProduct = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $electrolux->id,
            'sku' => 'KOTLOV-000727',
            'name' => 'Electrolux EFT/AG2R',
            'slug' => 'electrolux-eftag2r',
            'price' => 33,
            'images' => ['product/electrolux/eft-ag2r-old.jpg'],
            'content' => '<p>Старое описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'electrolux-eft-ag2r-chassis',
            'external_code' => 'УТ-00003235',
            'external_sku' => null,
            'name' => 'Комплект шасси EFT/AG2R для напольной установки конвектора Electrolux',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongEfa2Candidate->id, 'score' => 0.80]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ay',
            '--code' => ['УТ-00003235'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $exactProduct->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->product_id);
        $this->assertNotSame($wrongEfa2Candidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Комплект шасси Electrolux EFT/AG2R для Air Gate Transformer', $exactProduct->name);
        $this->assertSame('33.00', $exactProduct->price);
        $this->assertSame('1', (string) $exactProduct->stock_qty);
        $this->assertTrue((bool) $exactProduct->in_stock);
        $this->assertTrue((bool) $exactProduct->is_active);
        $this->assertFalse((bool) $exactProduct->is_archived);
        $this->assertSame('EFT/AG2R', collect($exactProduct->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('НС-1117333', collect($exactProduct->specs)->firstWhere('key', 'Код товара')['value']);
        $this->assertSame('2 колёсные опоры', collect($exactProduct->specs)->firstWhere('key', 'Комплектация')['value']);
        $this->assertSame('226 × 70 × 40 мм', collect($exactProduct->specs)->firstWhere('key', 'Габариты одной опоры')['value']);
        $this->assertSame(['img/products/electrolux/electrolux-eft-ag2r-chassis.jpg'], $exactProduct->images);
        $this->assertFileExists(public_path('img/products/electrolux/electrolux-eft-ag2r-chassis.jpg'));
        $this->assertStringContainsString('rusklimat.ru', $exactProduct->content_source_url);
        $this->assertStringContainsString('Air Gate Transformer System', $exactProduct->content);
        $this->assertStringNotContainsString('EFA2', $exactProduct->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($exactProduct->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00003235',
            'product_id' => $exactProduct->id,
            'product_sku' => 'KOTLOV-000727',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ay',
            '--code' => ['УТ-00003235'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->fresh()->product_id);
    }

    public function test_it_links_the_exact_aisi_304_d150_umbrella_without_using_the_ferrum_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-zm-r-304-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zonty-deflektory'], [
            'name' => 'Зонты и дефлекторы',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], ['name' => 'Теплов и Сухов']);
        $wrongFerrumCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-007.788',
            'name' => 'Зонт-К FERRUM (430/0,5) Ф150',
            'slug' => 'ferrum-430-d150-wrong-candidate',
            'price' => 26,
            'images' => ['product/ferrum/zont-k-430-d150.jpg'],
        ]);
        $exactProduct = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.529',
            'name' => 'Зонт Теплов и Сухов ЗМ-Р AISI 304 0,5-150',
            'slug' => 'zont-teplov-i-suhov-zm-r-aisi-304-0-5-150',
            'price' => 27.20,
            'images' => ['product/009/009529/zont_304_1.jpeg'],
            'content' => '<p>Старое общее описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-zm-r-304-d150',
            'external_code' => 'УТ-00002699',
            'external_sku' => 'TS.ST5.ZNT.0150.32500',
            'name' => 'Зонт ЗМ-Р 304-0.5 D150',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongFerrumCandidate->id, 'score' => 0.72]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-az',
            '--code' => ['УТ-00002699'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $exactProduct->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->product_id);
        $this->assertNotSame($wrongFerrumCandidate->id, $item->product_id);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Теплов и Сухов ЗМ-Р AISI 304 0,5 мм D150 зонт для дымохода', $exactProduct->name);
        $this->assertSame('27.20', $exactProduct->price);
        $this->assertSame('3', (string) $exactProduct->stock_qty);
        $this->assertTrue((bool) $exactProduct->in_stock);
        $this->assertSame('AISI 304', collect($exactProduct->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('150 мм', collect($exactProduct->specs)->firstWhere('key', 'Диаметр подключения')['value']);
        $this->assertSame('260 мм', collect($exactProduct->specs)->firstWhere('key', 'Диаметр колпака')['value']);
        $this->assertSame('TS.ST5.ZNT.0150.32500', collect($exactProduct->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-i-suhov-zm-r-304-05-d150.png'], $exactProduct->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-i-suhov-zm-r-304-05-d150.png'));
        $this->assertStringContainsString('banikaminy.ru', $exactProduct->content_source_url);
        $this->assertStringContainsString('утеплённого дымохода', $exactProduct->content);
        $this->assertStringNotContainsString('FERRUM', $exactProduct->content);
        $this->assertStringNotContainsString('AISI 430', $exactProduct->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('нбрб', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($exactProduct->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.ST5.ZNT.0150.32500',
            'product_id' => $exactProduct->id,
            'product_sku' => 'PS-009.529',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-az',
            '--code' => ['УТ-00002699'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->fresh()->product_id);
    }

    public function test_it_links_the_exact_tenko_bnr_6kw_220v_without_using_the_9kw_380v_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-tenko-bnr-6-220',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'elektricheskie-teny-dlya-otopleniya'], [
            'name' => 'Электрические ТЭНы для отопления',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'tenko'], ['name' => 'Tenko']);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.834',
            'name' => 'Блок нагревателей регулируемый TENKO 9 кВт 380 V',
            'slug' => 'tenko-bnr-9kw-380v-wrong-candidate',
            'price' => 397,
            'images' => ['product/tenko/bnr-9-380.jpg'],
        ]);
        $exactProduct = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.835',
            'name' => 'Блок нагревателей регулируемый (БНР) блок-тэн TENKO 6 кВт 220 V',
            'slug' => 'blok-nagrevateley-reguliruemyiy-bnr-blok-ten-tenko-6-kvt-220-v',
            'price' => 387,
            'images' => ['product/tenko/old-bnr-6-220.jpg'],
            'content' => null,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'tenko-bnr-6-220',
            'external_code' => 'УТ-00001966',
            'external_sku' => null,
            'name' => 'Блок нагревательный регулируемый 6,0 _ 220 2" TENKO',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.74]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ba',
            '--code' => ['УТ-00001966'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $exactProduct->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Блок нагревателей регулируемый Tenko БНР 6 кВт 220 В, 2″', $exactProduct->name);
        $this->assertSame('387.00', $exactProduct->price);
        $this->assertSame('2', (string) $exactProduct->stock_qty);
        $this->assertTrue((bool) $exactProduct->in_stock);
        $this->assertSame('6 кВт', collect($exactProduct->specs)->firstWhere('key', 'Мощность')['value']);
        $this->assertSame('220 В', collect($exactProduct->specs)->firstWhere('key', 'Номинальное напряжение')['value']);
        $this->assertSame('2″', collect($exactProduct->specs)->firstWhere('key', 'Резьбовое подключение')['value']);
        $this->assertSame('480 мм', collect($exactProduct->specs)->firstWhere('key', 'Погружная длина')['value']);
        $this->assertSame(['img/products/tenko/tenko-bnr-6kw-220v-2in.png'], $exactProduct->images);
        $this->assertFileExists(public_path('img/products/tenko/tenko-bnr-6kw-220v-2in.png'));
        $this->assertStringContainsString('tenko.ua', $exactProduct->content_source_url);
        $this->assertStringContainsString('0 до 77 °C', $exactProduct->content);
        $this->assertStringNotContainsString('9 кВт', $exactProduct->content);
        $this->assertStringNotContainsString('380', $exactProduct->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($exactProduct->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001966',
            'product_id' => $exactProduct->id,
            'product_sku' => 'PS-008.835',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-ba',
            '--code' => ['УТ-00001966'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_stahlmann_dn20_female_thread_tee_instead_of_linking_an_unrelated_tee(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-stahlmann-tee-20-f',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'komplektuyushhie-dlya-otopleniya'], [
            'name' => 'Комплектующие для отопления',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-007773',
            'name' => 'Тройник 20х32х20',
            'slug' => 'troynik-20h32h20-wrong-candidate',
            'price' => 20,
            'images' => ['product/fittings/tee-20-32-20.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'stahlmann-tee-f-20-3-4-20',
            'external_code' => 'УТ-00009494',
            'external_sku' => null,
            'name' => 'Тройник Stahlmann (F) 20х3/4х20 EF',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.73]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bb',
            '--code' => ['УТ-00009494'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('Тройник Stahlmann (F) 20×3/4″×20 EF для гофрированной трубы', $product->name);
        $this->assertSame('35.00', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('Stahlmann', $product->brand?->name);
        $this->assertSame('20 мм (DN20)', collect($product->specs)->firstWhere('key', 'Диаметр трубы')['value']);
        $this->assertSame('Внутренняя (F)', collect($product->specs)->firstWhere('key', 'Тип резьбы')['value']);
        $this->assertSame('STFL2003/420BEF', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/stahlmann/stahlmann-tee-f-20-3-4-20-ef.png'], $product->images);
        $this->assertFileExists(public_path('img/products/stahlmann/stahlmann-tee-f-20-3-4-20-ef.png'));
        $this->assertStringContainsString('teremonline.ru', $product->content_source_url);
        $this->assertStringContainsString('гофрированной нержавеющей трубы', $product->content);
        $this->assertStringNotContainsString('20х32х20', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00009494',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bb',
            '--code' => ['УТ-00009494'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_links_and_corrects_the_exact_tmf_avoska_00902_without_using_the_ogon_batareya_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-tmf-avoska-00902',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'burzhuiki-pechi'], [
            'name' => 'Буржуйки',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'tmf'], ['name' => 'TMF']);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-004602',
            'name' => 'ПО ОГОНЬ-БАТАРЕЯ 5 антрацит',
            'slug' => 'ogon-batareya-5-wrong-candidate',
            'price' => 1000,
            'images' => ['product/tmf/ogon-batareya-5.jpg'],
        ]);
        $exactProduct = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-012.085',
            'name' => 'Печь TMF АВОСЬКА антрацит',
            'slug' => 'pech-otopitelnaya-avoska-tmf',
            'price' => 0,
            'images' => ['product/0012/012085/old-photo.jpg'],
            'short_description' => 'Ошибочно: дверца со стеклом и мощность 8 кВт.',
            'content' => '<p>Старое неточное описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'tmf-avoska-00902',
            'external_code' => 'УТ-00008428',
            'external_sku' => '00902',
            'name' => 'ПО Авоська, портативная, антрацит',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.72]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bc',
            '--code' => ['УТ-00008428'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $exactProduct->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Печь портативная TMF Авоська антрацит, арт. 00902', $exactProduct->name);
        $this->assertSame('445.00', $exactProduct->price);
        $this->assertSame('1', (string) $exactProduct->stock_qty);
        $this->assertTrue((bool) $exactProduct->in_stock);
        $this->assertSame('4 кВт', collect($exactProduct->specs)->firstWhere('key', 'Мощность')['value']);
        $this->assertSame('Глухая стальная, без стекла', collect($exactProduct->specs)->firstWhere('key', 'Дверца')['value']);
        $this->assertSame('00902', collect($exactProduct->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/tmf/tmf-avoska-anthracite-00902.png'], $exactProduct->images);
        $this->assertFileExists(public_path('img/products/tmf/tmf-avoska-anthracite-00902.png'));
        $this->assertStringContainsString('tmf-market.ru', $exactProduct->content_source_url);
        $this->assertStringContainsString('глухой стальной дверцей без стекла', $exactProduct->content);
        $this->assertStringNotContainsString('ОГОНЬ-БАТАРЕЯ', $exactProduct->content);
        $this->assertStringNotContainsString('8 кВт', $exactProduct->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($exactProduct->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => '00902',
            'product_id' => $exactProduct->id,
            'product_sku' => 'PS-012.085',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bc',
            '--code' => ['УТ-00008428'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->fresh()->product_id);
    }

    public function test_it_marks_the_confirmed_archived_fresca_facing_as_not_for_site(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-archived-fresca-cpf',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'pechnoe-i-kaminnoe-lite'], [
            'name' => 'Печное и каминное литьё',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-FIREWAY-PR04',
            'name' => 'Дверца каминная Fireway PR-04',
            'slug' => 'fireway-dverza-pr-04-kaminnaya',
            'price' => 786,
            'images' => ['product/fireway/pr-04.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'archived-fresca-cpf',
            'external_code' => 'УТ-00002114',
            'name' => 'Облицовка каминная Fresca CPF',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.67,
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.67]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-20261013-bj',
            '--code' => ['УТ-00002114'],
        ])->assertSuccessful();
        $this->assertSame('ambiguous', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-20261013-bj',
            '--code' => ['УТ-00002114'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('ignored', $item->match_status);
        $this->assertSame('manual_ignore', $item->match_method);
        $this->assertNull($item->match_confidence);
        $this->assertNull($item->matched_at);
        $this->assertSame($wrongCandidate->id, (int) $item->candidates[0]['product_id']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-20261013-bj',
            '--code' => ['УТ-00002114'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame('ignored', $item->fresh()->match_status);
    }

    public function test_it_marks_the_old_ofc_oxi_controller_as_not_for_site(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-old-ofc-oxi',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], [
            'name' => 'Регуляторы',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-005.590',
            'name' => 'Контроллер SALUS PC11',
            'slug' => 'kontroller-salus-pc11',
            'price' => 190,
            'images' => ['product/salus/pc11.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'old-ofc-oxi',
            'external_code' => 'УТ-00006721',
            'name' => 'Контроллер OFC-OXI',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.75,
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.75]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-20261013-bk',
            '--code' => ['УТ-00006721'],
        ])->assertSuccessful();
        $this->assertSame('ambiguous', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-20261013-bk',
            '--code' => ['УТ-00006721'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('ignored', $item->match_status);
        $this->assertSame('manual_ignore', $item->match_method);
        $this->assertNull($item->match_confidence);
        $this->assertNull($item->matched_at);
        $this->assertSame($wrongCandidate->id, (int) $item->candidates[0]['product_id']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-20261013-bk',
            '--code' => ['УТ-00006721'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame('ignored', $item->fresh()->match_status);
    }

    public function test_it_archives_the_old_ole_pro_sensor_card_and_excludes_its_high_power_sibling(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-archived-ole-pro-sensors',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], [
            'name' => 'Регуляторы',
            'parent_id' => 0,
        ]);
        $oldSensor = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-022609',
            'name' => 'OLE-PRO OS-H-TP датчик температуры горелки 20–50 кВт',
            'slug' => 'ole-pro-os-h-tp-datcik-temperatury-gorelki-20-50-kvt',
            'price' => 61,
            'images' => ['product/ole-pro/os-h-tp.jpg'],
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => true,
            'stock_qty' => 6,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.326',
            'name' => 'Датчик температуры горячей воды AS1.6 (Buderus)',
            'slug' => 'datchik-temperatury-goryachej-vody-as16',
            'price' => 93.20,
            'images' => ['product/buderus/as16.jpg'],
        ]);
        $linkedItem = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'old-os-h-tp-20-50',
            'external_code' => 'УТ-00000144',
            'name' => 'Датчик температуры OS-H-TP (20-50)',
            'price_byn' => null,
            'stock_quantity' => 6,
            'product_id' => $oldSensor->id,
            'match_status' => 'matched',
            'match_method' => 'manual_created',
            'match_confidence' => 1,
            'matched_at' => now(),
        ]);
        $siblingItem = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'old-os-h-tp-75-150',
            'external_code' => 'УТ-00001965',
            'name' => 'Датчик температуры горелки OS-H-TP (75-150) HOTTA',
            'price_byn' => null,
            'stock_quantity' => 7,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.71,
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-ole-pro-20261013-bl',
        ])->assertSuccessful();
        $this->assertTrue($oldSensor->fresh()->is_active);
        $this->assertSame('matched', $linkedItem->fresh()->match_status);
        $this->assertSame('ambiguous', $siblingItem->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-ole-pro-20261013-bl',
            '--apply' => true,
        ])->assertSuccessful();

        $oldSensor->refresh();
        $this->assertFalse($oldSensor->is_active);
        $this->assertTrue($oldSensor->is_archived);
        $this->assertFalse($oldSensor->in_stock);
        $this->assertNull($oldSensor->stock_qty);

        foreach ([$linkedItem, $siblingItem] as $item) {
            $item->refresh();
            $this->assertNull($item->product_id);
            $this->assertSame('ignored', $item->match_status);
            $this->assertSame('manual_ignore', $item->match_method);
            $this->assertNull($item->match_confidence);
            $this->assertNull($item->matched_at);
        }

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-ole-pro-20261013-bl',
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame('ignored', $linkedItem->fresh()->match_status);
        $this->assertSame('ignored', $siblingItem->fresh()->match_status);
    }

    public function test_it_excludes_the_archived_hotta_os_p_3_sensor_from_the_site(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-archived-hotta-os-p-3',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], [
            'name' => 'Регуляторы',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.326',
            'name' => 'Датчик температуры горячей воды AS1.6 (Buderus)',
            'slug' => 'datchik-temperatury-goryachej-vody-as16',
            'price' => 93.20,
            'images' => ['product/buderus/as16.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'archived-hotta-os-p-3',
            'external_code' => 'УТ-00002594',
            'name' => 'Датчик температуры горелки OS-P-3 (75-150) HOTTA',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => 0.71,
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.71]],
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-hotta-sensor-20261013-bm',
            '--code' => ['УТ-00002594'],
        ])->assertSuccessful();
        $this->assertSame('ambiguous', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-hotta-sensor-20261013-bm',
            '--code' => ['УТ-00002594'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertSame('ignored', $item->match_status);
        $this->assertSame('manual_ignore', $item->match_method);
        $this->assertNull($item->match_confidence);
        $this->assertNull($item->matched_at);
        $this->assertSame($wrongCandidate->id, (int) $item->candidates[0]['product_id']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'archived-hotta-sensor-20261013-bm',
            '--code' => ['УТ-00002594'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame('ignored', $item->fresh()->match_status);
    }

    public function test_it_links_and_refreshes_the_exact_unmatched_fireway_master_flash_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-master-flash',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], [
            'name' => 'Крепления и монтаж',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Fireway', 'slug' => 'fireway']);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-023001',
            'name' => 'Master Flash №8 прямой (178-330 мм) Fireway',
            'slug' => 'fireway-master-flash-8-pryamoj',
            'price' => 56,
            'images' => [],
            'short_description' => 'Старая неполная карточка.',
            'content' => '<p>Старое описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-master-flash-180-330',
            'external_code' => 'УТ-00001953',
            'name' => 'Проходник кровли (мастер-флеш прямой) (180-330)',
            'price_byn' => null,
            'stock_quantity' => 6,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bn',
            '--code' => ['УТ-00001953'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('56.00', $product->fresh()->price);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bn',
            '--code' => ['УТ-00001953'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Fireway Master Flash №8 прямой 178–330 мм', $product->name);
        $this->assertSame('85.00', $product->price);
        $this->assertSame('6', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(['img/products/master-flash/master-flash-8-180-330-black.png'], $product->images);
        $this->assertFileExists(public_path('img/products/master-flash/master-flash-8-180-330-black.png'));
        $this->assertSame('178–330 мм', collect($product->specs)->firstWhere('key', 'Диаметр трубы')['value']);
        $this->assertStringContainsString('pechisamara.ru', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bn',
            '--code' => ['УТ-00001953'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_alukraft_1200_roll(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-alukraft-1200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'otdelka-dlya-parnoj'], [
            'name' => 'Отделка для парной',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'PS-012.090',
            'name' => 'Фольга для бани Алукрафт на бумажной основе 30м2',
            'slug' => 'folga-dlya-bani-alukraft-na-bumajnoy-osnove-30m2',
            'price' => 68,
            'images' => ['product/alukraft-30.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-alukraft-1200-18m2',
            'external_code' => 'УТ-00002162',
            'name' => 'Отражающая изоляция "Алукрафт 1200", рулон 1,2м*15мп',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bo',
            '--code' => ['УТ-00002162'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame($productCount, Product::query()->count());

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bo',
            '--code' => ['УТ-00002162'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Отражающая изоляция Алукрафт 1200, 1,2×15 м (18 м²)', $product->name);
        $this->assertNull($product->brand_id);
        $this->assertSame('55.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(['img/products/alukraft/alukraft-1200-1-2x15m-18m2.png'], $product->images);
        $this->assertFileExists(public_path('img/products/alukraft/alukraft-1200-1-2x15m-18m2.png'));
        $this->assertSame('18 м²', collect($product->specs)->firstWhere('key', 'Площадь рулона')['value']);
        $this->assertStringContainsString('pechy-kaminy.ru', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bo',
            '--code' => ['УТ-00002162'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_mut_vm3_dn25_valve(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-mut-vm3-dn25',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'smesitelnye-klapany-i-uzly'], [
            'name' => 'Смесительные клапаны и узлы',
            'parent_id' => 0,
        ]);
        $templateBrand = Brand::query()->create(['name' => 'ESBE', 'slug' => 'esbe']);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $templateBrand->id,
            'sku' => 'PS-004.426',
            'name' => 'Клапан смесительный серии ESBE VRG 131',
            'slug' => 'klapan-smesitelnyj-serii-esbe-vrg-131',
            'price' => 139.80,
            'images' => ['product/esbe-vrg-131.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-mut-vm3-dn25',
            'external_code' => 'УТ-00002159',
            'external_sku' => null,
            'name' => 'Клапан смеситель 3-ходовой VM3 (латунь) d25 VVM3Z25',
            'price_byn' => 84.96,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bp',
            '--code' => ['УТ-00002159'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertNull($item->fresh()->external_sku);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bp',
            '--code' => ['УТ-00002159'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertSame('VVM3Z25', $item->external_sku);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('MUT', $product->brand?->name);
        $this->assertSame('mut', $product->brand?->slug);
        $this->assertSame('Трёхходовой смесительный клапан MUT VM3 3000 DN25, 1″', $product->name);
        $this->assertSame('169.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(['img/products/mut/mut-vm3-3000-dn25.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/mut/mut-vm3-3000-dn25.jpg'));
        $this->assertSame('DN25', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertStringContainsString('mutmeccanica.com', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('eur', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bp',
            '--code' => ['УТ-00002159'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
        $this->assertSame(1, Brand::query()->where('slug', 'mut')->count());
    }

    public function test_it_creates_the_exact_teplov_i_suhov_l500_d120_200_pipe_instead_of_linking_the_l250_d120_180_candidate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-suhov-l500-d120-200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'truby-sendvich'], [
            'name' => 'Трубы сэндвич',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'teplov-i-suhov'],
            ['name' => 'Теплов и Сухов'],
        );
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.041',
            'name' => 'Труба Теплов и Сухов термо ТТ-Р 430, 0.8/430, 0.5 120/180 L250',
            'slug' => 'truba-teplov-i-suhov-termo-tt-r-430-08-430-05-120-180-l250',
            'price' => 60,
            'images' => ['product/teplov/l250-120-180.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-suhov-l500-d120-200',
            'external_code' => 'УТ-00009448',
            'external_sku' => 'TS.FRT.TRB.0120.61045',
            'name' => 'Труба Термо L 500 ТТ-Р 430-0.8/430 D120/200 арт. TS.FRT.TRB.0120.61045',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.67]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bd',
            '--code' => ['УТ-00009448'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('Теплов и Сухов Труба Термо L500 ТТ-Р 430-0.8/430 D120/200', $product->name);
        $this->assertSame('99.00', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('Теплов и Сухов', $product->brand?->name);
        $this->assertSame('500 мм', collect($product->specs)->firstWhere('key', 'Номинальная длина')['value']);
        $this->assertSame('447 мм', collect($product->specs)->firstWhere('key', 'Монтажная длина')['value']);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('200 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('TS.FRT.TRB.0120.61045', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teplov-i-suhov-tt-r-l500-430-08-430-d120-200.png'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teplov-i-suhov-tt-r-l500-430-08-430-d120-200.png'));
        $this->assertStringContainsString('kaminchi.ru', $product->content_source_url);
        $this->assertStringContainsString('монтажная длина после соединения — 447 мм', $product->content);
        $this->assertStringNotContainsString('L250', $product->content);
        $this->assertStringNotContainsString('120/180', $product->content);
        $this->assertStringNotContainsString('кандидат', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.FRT.TRB.0120.61045',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bd',
            '--code' => ['УТ-00009448'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_links_and_refreshes_the_exact_small_fraction_raspberry_quartzite_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-raspberry-quartzite-small',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'kamni-dlya-bani'], [
            'name' => 'Камни для бани',
            'parent_id' => 0,
        ]);
        $exactProduct = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-000.124',
            'name' => 'Камни Малиновый кварцит колотый (20 кг)',
            'slug' => 'kamni-malinovyj-kvarcit-kolotyj',
            'price' => 35,
            'images' => ['product/000/000124/208-kvarc.jpg'],
            'short_description' => 'Старая карточка без указания фракции.',
            'content' => '<p>Старое описание с недоказанными лечебными утверждениями.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'raspberry-quartzite-small-box',
            'external_code' => 'УТ-00009367',
            'external_sku' => null,
            'name' => 'Камень для саун (малиновый кварцит, кор) мелкий',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $exactProduct->id, 'score' => 0.70]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-be',
            '--code' => ['УТ-00009367'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $exactProduct->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Камни для бани Малиновый кварцит колотый, мелкая фракция, 20 кг', $exactProduct->name);
        $this->assertSame('38.00', $exactProduct->price);
        $this->assertSame('3', (string) $exactProduct->stock_qty);
        $this->assertTrue((bool) $exactProduct->in_stock);
        $this->assertSame('Мелкая', collect($exactProduct->specs)->firstWhere('key', 'Фракция')['value']);
        $this->assertSame('20 кг', collect($exactProduct->specs)->firstWhere('key', 'Фасовка')['value']);
        $this->assertSame(['img/products/sauna-stones/malinovyi-kvartsit-kolotyi-melkii-20kg.png'], $exactProduct->images);
        $this->assertFileExists(public_path('img/products/sauna-stones/malinovyi-kvartsit-kolotyi-melkii-20kg.png'));
        $this->assertStringContainsString('banipechi.by', $exactProduct->content_source_url);
        $this->assertStringContainsString('поставляется в коробке массой 20 кг', $exactProduct->content);
        $this->assertStringNotContainsString('лечеб', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('давлен', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($exactProduct->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($exactProduct->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00009367',
            'product_id' => $exactProduct->id,
            'product_sku' => 'PS-000.124',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-be',
            '--code' => ['УТ-00009367'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($exactProduct->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_full_size_skamol_board_instead_of_linking_the_small_mbs_spare_part(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-skamol-board-1260-1000-25',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'oblicovki'], [
            'name' => 'Облицовки и термоизоляция',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-022510',
            'name' => 'MBS Плита вермикулитовая к MBS Vesta (~500х300мм)',
            'slug' => 'mbs-plita-vermikulitovaia-k-mbs-vesta-500x300mm',
            'price' => 120,
            'images' => ['product/mbs/vesta-vermiculite.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'skamol-board-1260-1000-25',
            'external_code' => 'УТ-00008344',
            'external_sku' => null,
            'name' => 'Плита вермикулитовая 25х1260х1000 мм',
            'price_byn' => null,
            'stock_quantity' => 0.41,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.78]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bf',
            '--code' => ['УТ-00008344'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('Вермикулитовая плита Skamol V-1100-700 1260×1000×25 мм', $product->name);
        $this->assertSame('349.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertSame('0.410', $item->stock_quantity);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('Scamol', $product->brand?->name);
        $this->assertSame('1260 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Ширина')['value']);
        $this->assertSame('25 мм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame('700 кг/м³', collect($product->specs)->firstWhere('key', 'Плотность')['value']);
        $this->assertSame(['img/products/skamol/skamol-v1100-700-1260-1000-25.png'], $product->images);
        $this->assertFileExists(public_path('img/products/skamol/skamol-v1100-700-1260-1000-25.png'));
        $this->assertStringContainsString('kaminnext.ru', $product->content_source_url);
        $this->assertStringContainsString('отдельный полноразмерный лист', $product->content);
        $this->assertStringNotContainsString('MBS Vesta', $product->content);
        $this->assertStringNotContainsString('500×300', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00008344',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bf',
            '--code' => ['УТ-00008344'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_darco_ys125_90_ventilation_tee(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-darco-ys125',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'troyniki-mono'], [
            'name' => 'Тройники моно',
            'parent_id' => 0,
        ]);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.495',
            'name' => 'Тройник тип Y Y150/120-CZ2-G/D',
            'slug' => 'troynik-y-darco-150-120-cz2-g',
            'price' => 441,
            'images' => ['product/darco/wrong-tee.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'darco-ys125-90-oc',
            'external_code' => 'УТ-00009142',
            'external_sku' => null,
            'name' => 'Тройник тип Y YS125/90-OC',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.80]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bg',
            '--code' => ['УТ-00009142'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame('YS125/90-OC', $item->external_sku);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('Тройник вентиляционный Y Darco YS125/90-OC Ø125 мм', $product->name);
        $this->assertSame('35.00', $product->price);
        $this->assertSame('Darco', $product->brand?->name);
        $this->assertSame('125 мм', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('123 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр патрубков')['value']);
        $this->assertSame(['img/products/darco/darco-ys125-90-oc.webp'], $product->images);
        $this->assertFileExists(public_path('img/products/darco/darco-ys125-90-oc.webp'));
        $this->assertStringContainsString('sklep.darco.pl', $product->content_source_url);
        $this->assertStringContainsString('вентиляции', mb_strtolower($product->content));
        $this->assertStringContainsString('не тройник дымохода', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'YS125/90-OC',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bg',
            '--code' => ['УТ-00009142'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_aisi_430_one_mm_d150_tee(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-teplov-suhov-tee-one-mm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'troyniki-mono'], [
            'name' => 'Тройники моно',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'Теплов и Сухов', 'slug' => 'teplov-i-suhov']);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008004',
            'name' => 'Теплов и Сухов Тройник 87° ТРМ(М)-Р 430-0.5 D150',
            'slug' => 'teplov-i-suhov-troynik-87-430-05-d150',
            'price' => 73.39,
            'images' => ['product/teplov-suhov/wrong-05-mm.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-suhov-tee-one-mm-d150',
            'external_code' => 'УТ-00009517',
            'external_sku' => 'TS.FRT.T87.0150.82195',
            'name' => 'Тройник 87* ТРМ(М)-Р 430-1.0 D150',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.73]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bh',
            '--code' => ['УТ-00009517'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('Теплов и Сухов Тройник 87° ТРМ(М)-Р AISI 430 1,0 мм Ø150', $product->name);
        $this->assertSame('99.00', $product->price);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('1,0 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/troynik-87-trmm-r-430-1-0-d150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/troynik-87-trmm-r-430-1-0-d150.jpg'));
        $this->assertStringContainsString('saunaopt.ru', $product->content_source_url);
        $this->assertStringContainsString('сухой режим', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.FRT.T87.0150.82195',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bh',
            '--code' => ['УТ-00009517'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_kpd_black_d120_one_meter_pipe_instead_of_the_shiber(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-kpd-black-d120-pipe',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'truby-mono'], [
            'name' => 'Трубы моно',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create(['name' => 'КПД', 'slug' => 'kpd']);
        $wrongCandidate = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022436',
            'name' => 'КПД КПД ЧЕРНЫЙ Шибер 1000мм 2мм ф120',
            'slug' => 'kpd-chernyy-shiber-1000-d120',
            'price' => 74,
            'images' => ['product/kpd/wrong-shiber.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'kpd-black-pipe-1000-d120-2mm',
            'external_code' => 'УТ-00008904',
            'external_sku' => null,
            'name' => 'КПД Труба ЧЁРНАЯ 1000мм 2мм ф120',
            'price_byn' => null,
            'stock_quantity' => 3,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $wrongCandidate->id, 'score' => 0.75]],
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bi',
            '--code' => ['УТ-00008904'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($item->product_id);
        $this->assertNotSame($wrongCandidate->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame('6716', $item->external_sku);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);

        $product = $item->product;
        $this->assertSame('КПД ЧЁРНЫЙ Труба дымоходная 1000 мм, Ø120, 2 мм', $product->name);
        $this->assertSame('99.00', $product->price);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('2 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame(['img/products/kpd/truba-chernaya-1000-d120-2mm.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/kpd/truba-chernaya-1000-d120-2mm.jpg'));
        $this->assertStringContainsString('1001pech.ru', $product->content_source_url);
        $this->assertStringContainsString('стали 08пс', mb_strtolower($product->content));
        $this->assertStringContainsString('до 800 °c', mb_strtolower($product->content));
        $this->assertStringNotContainsString('шибер', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => '6716',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-bi',
            '--code' => ['УТ-00008904'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_links_and_refreshes_the_exact_unmatched_ma25_aluminium_tape(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-ma25-tape',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'otdelka-dlya-parnoj'], [
            'name' => 'Отделка для парной',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'PS-012.089',
            'name' => 'Скотч-фольга (алюминиевая клейкая лента) 50мм х 50м',
            'slug' => 'skotch-folga-alyuminievaya-kleykaya-lenta-50mm-h-50m',
            'price' => 15,
            'images' => [
                'product/0012/012089/679b60b309dbc.png',
                'product/0012/012089/679b60b309e57.jpg',
            ],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-ma25-tape',
            'external_code' => 'УТ-00002120',
            'name' => 'Фольгоскотч (лента МА25 50ммх50м)',
            'price_byn' => null,
            'stock_quantity' => 21,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bq',
            '--code' => ['УТ-00002120'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('15.00', $product->fresh()->price);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bq',
            '--code' => ['УТ-00002120'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Алюминиевая самоклеящаяся лента МА-25, 50 мм × 50 м', $product->name);
        $this->assertSame('16.00', $product->price);
        $this->assertSame('21', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame([
            'product/0012/012089/679b60b309dbc.png',
            'product/0012/012089/679b60b309e57.jpg',
        ], $product->images);
        $this->assertSame('25 мкм', collect($product->specs)->firstWhere('key', 'Толщина алюминиевой основы')['value']);
        $this->assertSame('от −40 до +100 °C', collect($product->specs)->firstWhere('key', 'Температура эксплуатации')['value']);
        $this->assertStringContainsString('amazonka.by', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bq',
            '--code' => ['УТ-00002120'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_teplov_i_suhov_800x960_protective_screen(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tis-screen-800x960',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'otdelka-dlya-parnoj'], [
            'name' => 'Отделка для парной',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'teplov-i-suhov'],
            ['name' => 'Теплов и Сухов'],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'PS-012.089',
            'name' => 'Скотч-фольга (алюминиевая клейкая лента) 50мм х 50м',
            'slug' => 'skotch-folga-alyuminievaya-kleykaya-lenta-50mm-h-50m',
            'price' => 15,
            'images' => ['product/0012/012089/679b60b309e57.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-tis-screen-800x960',
            'external_code' => 'УТ-00001176',
            'external_sku' => 'TS.KMP.LST.0000.65833',
            'name' => 'Экран 800х960',
            'price_byn' => 109.32,
            'stock_quantity' => 5,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-br',
            '--code' => ['УТ-00001176'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-br',
            '--code' => ['УТ-00001176'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Защитный экран Теплов и Сухов, 800 × 960 мм', $product->name);
        $this->assertSame('139.00', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('800 × 960 мм', collect($product->specs)->firstWhere('key', 'Размер')['value']);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,5 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.KMP.LST.0000.65833', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame([
            'img/products/teplov-i-suhov/ekran-zashchitnyy-800x960-2.webp',
            'img/products/teplov-i-suhov/ekran-zashchitnyy-800x960.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/ekran-zashchitnyy-800x960-2.webp'));
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/ekran-zashchitnyy-800x960.webp'));
        $this->assertStringContainsString('domingo.su', $product->content_source_url);
        $this->assertStringContainsString('снять защитную плёнку', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.KMP.LST.0000.65833',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-br',
            '--code' => ['УТ-00001176'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_oxi_shp4_70_70_2000_pellet_auger(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-oxi-shp4-70-70-2000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'pelletnye-gorelki'], [
            'name' => 'Пеллетные горелки',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'oxi'], ['name' => 'OXI']);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-000900',
            'name' => 'OXI колосник нижнего ряда для пеллетной горелки Керамик 75-1000 кВт',
            'slug' => 'oxi-kolosnik-niznego-riada-dlia-pelletnoi-gorelki-ceramik-75-1000-kvt',
            'price' => 100,
            'images' => ['product/oxi/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'unmatched-oxi-shp4-70-70-2000',
            'external_code' => 'УТ-00001169',
            'external_sku' => null,
            'name' => 'Шнек ШП4-70-70-2000',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bs',
            '--code' => ['УТ-00001169'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bs',
            '--code' => ['УТ-00001169'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Шнек подачи пеллет OXI ШП4-70-70-2000, 2 м', $product->name);
        $this->assertSame('1190.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('2000 мм', collect($product->specs)->firstWhere('key', 'Номинальная длина')['value']);
        $this->assertSame('75–150 кВт', collect($product->specs)->firstWhere('key', 'Совместимая мощность горелки')['value']);
        $this->assertSame(['img/products/oxi/oxi-shp4-70-70-2000.png'], $product->images);
        $this->assertFileExists(public_path('img/products/oxi/oxi-shp4-70-70-2000.png'));
        $this->assertStringContainsString('gazal.com.ua', $product->content_source_url);
        $this->assertStringContainsString('из топливного бункера', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($product->content));
        $this->assertStringNotContainsString('eur', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001169',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bs',
            '--code' => ['УТ-00001169'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_zurflex_dn16_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-zurflex-dn16-1200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000901',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['product/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc9c-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001167',
            'external_sku' => null,
            'name' => 'ШЛКг-I-0,5-DN16-1,2(Шланги гибкие гофр.из нерж.ста',
            'price_byn' => null,
            'stock_quantity' => 2,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bt',
            '--code' => ['УТ-00001167'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bt',
            '--code' => ['УТ-00001167'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'zurflex')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Газовый шланг ZURFLEX DN16, гайка/штуцер, 1,2 м', $product->name);
        $this->assertSame('34.00', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('DN16', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('3/4″', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/штуцер', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('0,005 МПа', collect($product->specs)->firstWhere('key', 'Рабочее давление')['value']);
        $this->assertSame([
            'img/products/zurflex/zurflex-gas-hose-dn16-1200-fm-1.webp',
            'img/products/zurflex/zurflex-gas-hose-dn16-1200-fm-2.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/zurflex/zurflex-gas-hose-dn16-1200-fm-1.webp'));
        $this->assertFileExists(public_path('img/products/zurflex/zurflex-gas-hose-dn16-1200-fm-2.webp'));
        $this->assertStringContainsString('bereg.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001167',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bt',
            '--code' => ['УТ-00001167'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_cemflex_dn12_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-cemflex-dn12-2500',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000902',
            'name' => 'Газовый шланг ZURFLEX DN16, гайка/штуцер, 1,2 м',
            'slug' => 'gazovyi-slang-zurflex-dn16-gaikastucer-12-m',
            'price' => 34,
            'images' => ['img/products/zurflex/template.webp'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc9a-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001165',
            'external_sku' => null,
            'name' => 'ШЛКг-1-0,5-DN12-2,5',
            'price_byn' => null,
            'stock_quantity' => 6,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bu',
            '--code' => ['УТ-00001165'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bu',
            '--code' => ['УТ-00001165'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'cemflex')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Газовый шланг CEMFLEX DN12, гайка/штуцер, 2,5 м', $product->name);
        $this->assertSame('22.00', $product->price);
        $this->assertSame('6', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('DN12', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('1/2″ ВР × 1/2″ НР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/штуцер', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('2500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/cemflex/cemflex-gas-hose-dn12-2500-fm-1.jpg',
            'img/products/cemflex/cemflex-gas-hose-dn12-2500-fm-2.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/cemflex/cemflex-gas-hose-dn12-2500-fm-1.jpg'));
        $this->assertFileExists(public_path('img/products/cemflex/cemflex-gas-hose-dn12-2500-fm-2.jpg'));
        $this->assertStringContainsString('santeh24.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001165',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bu',
            '--code' => ['УТ-00001165'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_zurflex_dn16_600_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-zurflex-dn16-600',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000903',
            'name' => 'Газовый шланг ZURFLEX DN16, гайка/штуцер, 1,2 м',
            'slug' => 'gazovyi-slang-zurflex-dn16-gaikastucer-12-m',
            'price' => 34,
            'images' => ['img/products/zurflex/template.webp'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc97-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001162',
            'external_sku' => null,
            'name' => 'ШЛК-I-0,5-DN16-0,6',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bv',
            '--code' => ['УТ-00001162'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bv',
            '--code' => ['УТ-00001162'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'zurflex')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Газовый шланг ZURFLEX DN16, гайка/штуцер, 0,6 м', $product->name);
        $this->assertSame('27.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШЛК-I-0,5-DN16-0,6', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('3/4″ ВР × 3/4″ НР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/штуцер', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('600 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/zurflex/zurflex-gas-hose-dn16-600-fm-1.webp',
            'img/products/zurflex/zurflex-gas-hose-dn16-600-fm-2.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/zurflex/zurflex-gas-hose-dn16-600-fm-1.webp'));
        $this->assertFileExists(public_path('img/products/zurflex/zurflex-gas-hose-dn16-600-fm-2.webp'));
        $this->assertStringContainsString('bereg.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001162',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bv',
            '--code' => ['УТ-00001162'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_cemflex_dn16_1500_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-cemflex-dn16-1500',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000904',
            'name' => 'Газовый шланг CEMFLEX DN12, гайка/штуцер, 2,5 м',
            'slug' => 'gazovyi-slang-cemflex-dn12-gaikastucer-25-m',
            'price' => 22,
            'images' => ['img/products/cemflex/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc95-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001160',
            'external_sku' => null,
            'name' => 'ШЛКг-1-0,5-DN16-1,5',
            'price_byn' => null,
            'stock_quantity' => 1,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bw',
            '--code' => ['УТ-00001160'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bw',
            '--code' => ['УТ-00001160'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'cemflex')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Газовый шланг CEMFLEX DN16, гайка/штуцер, 1,5 м', $product->name);
        $this->assertSame('28.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШЛКг-1-0,5-DN16-1,5', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('DN16', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('3/4″ ВР × 3/4″ НР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/штуцер', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('25 бар', collect($product->specs)->firstWhere('key', 'Максимальное давление')['value']);
        $this->assertSame('от −15 до +150 °C', collect($product->specs)->firstWhere('key', 'Рабочая температура')['value']);
        $this->assertSame([
            'img/products/cemflex/cemflex-gas-hose-dn16-1500-fm-1.jpg',
            'img/products/cemflex/cemflex-gas-hose-dn16-1500-fm-2.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/cemflex/cemflex-gas-hose-dn16-1500-fm-1.jpg'));
        $this->assertFileExists(public_path('img/products/cemflex/cemflex-gas-hose-dn16-1500-fm-2.jpg'));
        $this->assertStringContainsString('santeh24.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001160',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bw',
            '--code' => ['УТ-00001160'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_taim_3000_rubber_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-taim-rubber-gas-hose-3000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000905',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc91-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001154',
            'external_sku' => null,
            'name' => 'Шланг газовый ШГ-Ш1/2-3,0-Г1/2-',
            'price_byn' => null,
            'stock_quantity' => 16,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bx',
            '--code' => ['УТ-00001154'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bx',
            '--code' => ['УТ-00001154'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'taim')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('ТАиМ Шланг газовый резиновый 1/2″, 3,0 м, штуцер–гайка', $product->name);
        $this->assertSame('23.00', $product->price);
        $this->assertSame('16', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШГ-Ш1/2-3,0-Г1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('Армированная резина', collect($product->specs)->firstWhere('key', 'Материал')['value']);
        $this->assertSame('G1/2″ НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер–гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('3000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('0,05 МПа', collect($product->specs)->firstWhere('key', 'Максимальное давление')['value']);
        $this->assertSame('от −20 до +70 °C', collect($product->specs)->firstWhere('key', 'Рабочая температура')['value']);
        $this->assertSame([
            'img/products/taim/taim-gas-hose-rubber-12-3000-fm-1.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/taim/taim-gas-hose-rubber-12-3000-fm-1.jpg'));
        $this->assertStringContainsString('minskgas.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('minskgas.by', mb_strtolower(implode(' ', $product->images)));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001154',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bx',
            '--code' => ['УТ-00001154'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_taim_2000_rubber_gas_hose(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-taim-rubber-gas-hose-2000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-shlangi'], [
            'name' => 'Газовые шланги',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000906',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc90-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001153',
            'external_sku' => null,
            'name' => 'Шланг газовый ШГ-Ш1/2-2,0-Г1/2-',
            'price_byn' => null,
            'stock_quantity' => 90,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-by',
            '--code' => ['УТ-00001153'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-by',
            '--code' => ['УТ-00001153'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'taim')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('ТАиМ Шланг газовый резиновый 1/2″, 2,0 м, штуцер–гайка', $product->name);
        $this->assertSame('17.50', $product->price);
        $this->assertSame('90', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШГ-Ш1/2-2,0-Г1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('Армированная резина', collect($product->specs)->firstWhere('key', 'Материал')['value']);
        $this->assertSame('G1/2″ НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер–гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('2000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('0,05 МПа', collect($product->specs)->firstWhere('key', 'Максимальное давление')['value']);
        $this->assertSame('от −20 до +70 °C', collect($product->specs)->firstWhere('key', 'Рабочая температура')['value']);
        $this->assertSame([
            'img/products/taim/taim-gas-hose-rubber-12-3000-fm-1.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/taim/taim-gas-hose-rubber-12-3000-fm-1.jpg'));
        $this->assertStringContainsString('minskgas.by', $product->content_source_url);
        $this->assertStringContainsString('проверку герметичности', mb_strtolower($product->content));
        $this->assertStringNotContainsString('minskgas.by', mb_strtolower(implode(' ', $product->images)));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001153',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-by',
            '--code' => ['УТ-00001153'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_mixer_water_hose_1000(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-water-hose-1000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000906',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc90-1f62-11e9-bad6-1c1b0d1de7bd',
            'external_code' => 'УТ-00001148',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-ШК М10х1-1,0-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bz',
            '--code' => ['УТ-00001148'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bz',
            '--code' => ['УТ-00001148'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий М10×1–G1/2″, 1,0 м', $product->name);
        $this->assertSame('5.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-ШК М10×1-1,0-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('M10×1 НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер для смесителя/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('Нержавеющая сталь', collect($product->specs)->firstWhere('key', 'Материал оплётки')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-m10-g12-1000-1.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-m10-g12-1000-1.jpg'));
        $this->assertStringContainsString('sterh.by', $product->content_source_url);
        $this->assertStringContainsString('системе холодного или горячего водоснабжения', mb_strtolower($product->content));
        $this->assertStringNotContainsString('sterh.by', mb_strtolower(implode(' ', $product->images)));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertStringNotContainsString('13.10.2026', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001148',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-bz',
            '--code' => ['УТ-00001148'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_mixer_water_hose_800(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-water-hose-800',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000907',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'c1ad2cb9-1f62-11e9-bad6-1c1b0d1de7bd',
            'external_code' => 'УТ-00001147',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-ШК М10х1-0,8-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 9,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ca',
            '--code' => ['УТ-00001147'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ca',
            '--code' => ['УТ-00001147'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий М10×1–G1/2″, 0,8 м', $product->name);
        $this->assertSame('4.50', $product->price);
        $this->assertSame('9', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-ШК М10×1-0,8-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('M10×1 НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('800 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('Нержавеющая сталь', collect($product->specs)->firstWhere('key', 'Материал оплётки')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-m10-g12-1000-1.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-m10-g12-1000-1.jpg'));
        $this->assertStringContainsString('catalog.onliner.by', $product->content_source_url);
        $this->assertStringContainsString('800 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001147',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ca',
            '--code' => ['УТ-00001147'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_long_stem_mixer_water_hose_1000(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-long-water-hose-1000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000908',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc87-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001146',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-ШД М10х1-1,0-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 7,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cb',
            '--code' => ['УТ-00001146'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cb',
            '--code' => ['УТ-00001146'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий М10×1 длинный–G1/2″, 1,0 м', $product->name);
        $this->assertSame('6.00', $product->price);
        $this->assertSame('7', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-ШД М10×1-1,0-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('Длинный штуцер для смесителя/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('Нержавеющая сталь', collect($product->specs)->firstWhere('key', 'Материал оплётки')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-long-m10-g12-1000-1.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-long-m10-g12-1000-1.webp'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('длинным штуцером', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001146',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cb',
            '--code' => ['УТ-00001146'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_male_female_water_hose_1200(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-mf-water-hose-1200',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000909',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc85-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001144',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-Ш 1/2-1,2-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 10,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cc',
            '--code' => ['УТ-00001144'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cc',
            '--code' => ['УТ-00001144'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий G1/2″ НР–G1/2″ ВР, 1,2 м', $product->name);
        $this->assertSame('6.50', $product->price);
        $this->assertSame('10', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-Ш G1/2-1,2-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('G1/2″ НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1200 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('1200 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001144',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cc',
            '--code' => ['УТ-00001144'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_male_female_water_hose_1000(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-mf-water-hose-1000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000909',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc85-1f62-11e9-bad6-1c1b0d1de7c3',
            'external_code' => 'УТ-00001143',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-Ш 1/2-1,0-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 8,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cd',
            '--code' => ['УТ-00001143'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cd',
            '--code' => ['УТ-00001143'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий G1/2″ НР–G1/2″ ВР, 1,0 м', $product->name);
        $this->assertSame('6.00', $product->price);
        $this->assertSame('8', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-Ш G1/2-1,0-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('G1/2″ НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('1000 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001143',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cd',
            '--code' => ['УТ-00001143'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_male_female_water_hose_600(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-mf-water-hose-600',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000909',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'a13f8d35-62a9-4cea-9c84-0000011142aa',
            'external_code' => 'УТ-00001142',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-Ш 1/2-0,6-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 9,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ce',
            '--code' => ['УТ-00001142'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ce',
            '--code' => ['УТ-00001142'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий G1/2″ НР–G1/2″ ВР, 0,6 м', $product->name);
        $this->assertSame('4.50', $product->price);
        $this->assertSame('9', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-Ш G1/2-0,6-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('G1/2″ НР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Штуцер/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('600 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-g12-male-female-family.webp'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('600 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001142',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ce',
            '--code' => ['УТ-00001142'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_female_female_water_hose_1000(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-ff-water-hose-1000',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000909',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'a13f8d35-62a9-4cea-9c84-0000011141aa',
            'external_code' => 'УТ-00001141',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-Г 1/2-1-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 7,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cf',
            '--code' => ['УТ-00001141'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cf',
            '--code' => ['УТ-00001141'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий G1/2″ ВР–G1/2″ ВР, 1,0 м', $product->name);
        $this->assertSame('7.00', $product->price);
        $this->assertSame('7', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-Г G1/2-1,0-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('G1/2″ ВР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-g12-female-female-family.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-g12-female-female-family.png'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('1000 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001141',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cf',
            '--code' => ['УТ-00001141'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_tiflos_female_female_water_hose_800(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-tiflos-ff-water-hose-800',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000909',
            'name' => 'ТАиМ Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 11,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'a13f8d35-62a9-4cea-9c84-0000011140aa',
            'external_code' => 'УТ-00001140',
            'external_sku' => null,
            'name' => 'Шланг водопроводный гибкий ШВГ-Г 1/2-0,8-Г1/2',
            'price_byn' => null,
            'stock_quantity' => 10,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cg',
            '--code' => ['УТ-00001140'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cg',
            '--code' => ['УТ-00001140'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'tiflos')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Тифлос Шланг водопроводный гибкий G1/2″ ВР–G1/2″ ВР, 0,8 м', $product->name);
        $this->assertSame('6.50', $product->price);
        $this->assertSame('10', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('ШВГ-Г G1/2-0,8-G1/2', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('G1/2″ ВР × G1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('Гайка/гайка', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame('800 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/tiflos/tiflos-water-hose-g12-female-female-family.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/tiflos/tiflos-water-hose-g12-female-female-family.png'));
        $this->assertStringContainsString('tiflos.biz', $product->content_source_url);
        $this->assertStringContainsString('800 мм', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001140',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cg',
            '--code' => ['УТ-00001140'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_chistodym_chimney_cleaner_1kg(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-chistodym-1kg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary-kaminy'], [
            'name' => 'Аксессуары для каминов',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000910',
            'name' => 'Nordflam Решетка каминная AERO 90×800 белая',
            'slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia',
            'price' => 104,
            'images' => ['img/products/nordflam/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc70-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001123',
            'external_sku' => null,
            'name' => 'Чистодым 1,0 кг',
            'price_byn' => 15.53,
            'stock_quantity' => 10,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ch',
            '--code' => ['УТ-00001123'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ch',
            '--code' => ['УТ-00001123'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'chistodym')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Чистодым Очиститель дымохода, 1 кг', $product->name);
        $this->assertSame('22.00', $product->price);
        $this->assertSame('10', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Очиститель дымохода', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('1 кг', collect($product->specs)->firstWhere('key', 'Масса нетто')['value']);
        $this->assertSame('Порошок', collect($product->specs)->firstWhere('key', 'Форма')['value']);
        $this->assertSame('Около 20', collect($product->specs)->firstWhere('key', 'Количество применений')['value']);
        $this->assertSame([
            'img/products/chistodym/chistodym-chimney-cleaner-1kg.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/chistodym/chistodym-chimney-cleaner-1kg.png'));
        $this->assertStringContainsString('biodso.ru', $product->content_source_url);
        $this->assertStringContainsString('механическую чистку', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001123',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ch',
            '--code' => ['УТ-00001123'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_chistodym_chimney_cleaner_05kg(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-chistodym-05kg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary-kaminy'], [
            'name' => 'Аксессуары для каминов',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000910',
            'name' => 'Nordflam Решетка каминная AERO 90×800 белая',
            'slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia',
            'price' => 104,
            'images' => ['img/products/nordflam/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc6f-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001122',
            'external_sku' => null,
            'name' => 'Чистодым 0,5кг',
            'price_byn' => 10.56,
            'stock_quantity' => 7,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ci',
            '--code' => ['УТ-00001122'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ci',
            '--code' => ['УТ-00001122'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'chistodym')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Чистодым Очиститель дымохода, 0,5 кг', $product->name);
        $this->assertSame('12.00', $product->price);
        $this->assertSame('7', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Очиститель дымохода', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('0,5 кг', collect($product->specs)->firstWhere('key', 'Масса нетто')['value']);
        $this->assertSame('Порошок', collect($product->specs)->firstWhere('key', 'Форма')['value']);
        $this->assertSame('Около 10', collect($product->specs)->firstWhere('key', 'Количество применений')['value']);
        $this->assertSame([
            'img/products/chistodym/chistodym-chimney-cleaner-05kg.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/chistodym/chistodym-chimney-cleaner-05kg.png'));
        $this->assertStringContainsString('biodso.ru', $product->content_source_url);
        $this->assertStringContainsString('механическую чистку', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001122',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ci',
            '--code' => ['УТ-00001122'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_albertoni_chromed_copper_tube_5m(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-albertoni-tube-5m',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zhestkaya-podvodka-dlya-vody'], [
            'name' => 'Жёсткая подводка для воды',
            'parent_id' => 0,
        ]);
        $templateCategory = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'sku' => 'KOTLOV-000812',
            'name' => 'Тайм Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 14,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc66-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001113',
            'external_sku' => null,
            'name' => 'Хромовая трубка длина 5м',
            'price_byn' => null,
            'stock_quantity' => 5,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cj',
            '--code' => ['УТ-00001113'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cj',
            '--code' => ['УТ-00001113'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'albertoni')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Италия', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Albertoni 238/5 Трубка медная хромированная Ø10 мм, 5 м', $product->name);
        $this->assertSame('275.00', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Трубка для жёсткой подводки воды', collect($product->specs)->firstWhere('key', 'Тип')['value']);
        $this->assertSame('10 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('Около 5 м', collect($product->specs)->firstWhere('key', 'Длина бухты')['value']);
        $this->assertSame([
            'img/products/albertoni/albertoni-238-5-chrome-copper-tube-d10-5m.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/albertoni/albertoni-238-5-chrome-copper-tube-d10-5m.png'));
        $this->assertStringContainsString('modernsys.com.ua', $product->content_source_url);
        $this->assertStringContainsString('Фитинги в комплект не входят', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001113',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cj',
            '--code' => ['УТ-00001113'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_albertoni_chromed_copper_tube_500mm(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-albertoni-tube-500mm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zhestkaya-podvodka-dlya-vody'], [
            'name' => 'Жёсткая подводка для воды',
            'parent_id' => 0,
        ]);
        $templateCategory = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'sku' => 'KOTLOV-000812',
            'name' => 'Тайм Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 14,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc64-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001111',
            'external_sku' => null,
            'name' => 'Хром. трубка медная d10 с гайкой 1/2 500mmx1/2"',
            'price_byn' => null,
            'stock_quantity' => 4,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ck',
            '--code' => ['УТ-00001111'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ck',
            '--code' => ['УТ-00001111'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'albertoni')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Италия', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Albertoni 130/1 M272708 Трубка медная хромированная Ø10 мм, 1/2″ ВР–1/2″ ВР, 500 мм', $product->name);
        $this->assertSame('50.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('M272708', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('1/2″ ВР × 1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('500 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/albertoni/albertoni-m272708-chrome-copper-tube-500mm.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/albertoni/albertoni-m272708-chrome-copper-tube-500mm.png'));
        $this->assertStringContainsString('pivduyma.ua', $product->content_source_url);
        $this->assertStringContainsString('допустимый радиус изгиба', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001111',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ck',
            '--code' => ['УТ-00001111'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_albertoni_chromed_copper_tube_400mm(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-albertoni-tube-400mm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zhestkaya-podvodka-dlya-vody'], [
            'name' => 'Жёсткая подводка для воды',
            'parent_id' => 0,
        ]);
        $templateCategory = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'sku' => 'KOTLOV-000812',
            'name' => 'Тайм Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 14,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc63-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001110',
            'external_sku' => null,
            'name' => 'Хром. трубка медная d10 с гайкой 1/2 400mmx1/2"',
            'price_byn' => null,
            'stock_quantity' => 5,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cl',
            '--code' => ['УТ-00001110'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cl',
            '--code' => ['УТ-00001110'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'albertoni')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Италия', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Albertoni 130/1 M272706 Трубка медная хромированная Ø10 мм, 1/2″ ВР–1/2″ ВР, 400 мм', $product->name);
        $this->assertSame('47.00', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('M272706', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('1/2″ ВР × 1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('400 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/albertoni/albertoni-m272706-chrome-copper-tube-400mm.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/albertoni/albertoni-m272706-chrome-copper-tube-400mm.png'));
        $this->assertStringContainsString('pivduyma.ua', $product->content_source_url);
        $this->assertStringContainsString('допустимый радиус изгиба', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001110',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cl',
            '--code' => ['УТ-00001110'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_albertoni_chromed_copper_tube_300mm(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-albertoni-tube-300mm',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zhestkaya-podvodka-dlya-vody'], [
            'name' => 'Жёсткая подводка для воды',
            'parent_id' => 0,
        ]);
        $templateCategory = Category::query()->firstOrCreate(['slug' => 'gibkaya-podvodka-dlya-vody'], [
            'name' => 'Гибкая подводка для воды',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'sku' => 'KOTLOV-000812',
            'name' => 'Тайм Шланг газовый резиновый 1/2″, 1,0 м, штуцер–гайка',
            'slug' => 'taim-slang-gazovyi-rezinovyi-12-10-m-stucer-gaika',
            'price' => 14,
            'images' => ['img/products/taim/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc62-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001109',
            'external_sku' => null,
            'name' => 'Хром. трубка медная d10 с гайкой 1/2 300mmx1/2"',
            'price_byn' => null,
            'stock_quantity' => 11,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cm',
            '--code' => ['УТ-00001109'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cm',
            '--code' => ['УТ-00001109'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $brand = Brand::query()->where('slug', 'albertoni')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($brand);
        $this->assertSame('Италия', $brand->country);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertNull($item->external_sku);
        $this->assertSame('Albertoni 130/1 M272704 Трубка медная хромированная Ø10 мм, 1/2″ ВР–1/2″ ВР, 300 мм', $product->name);
        $this->assertSame('41.00', $product->price);
        $this->assertSame('11', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('M272704', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('1/2″ ВР × 1/2″ ВР', collect($product->specs)->firstWhere('key', 'Размер присоединения')['value']);
        $this->assertSame('300 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame([
            'img/products/albertoni/albertoni-m272704-chrome-copper-tube-300mm.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/albertoni/albertoni-m272704-chrome-copper-tube-300mm.png'));
        $this->assertStringContainsString('pivduyma.ua', $product->content_source_url);
        $this->assertStringContainsString('допустимый радиус изгиба', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001109',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cm',
            '--code' => ['УТ-00001109'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_links_and_refreshes_the_exact_unmatched_teplov_i_suhov_guy_wire_clamp_d260(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-guy-wire-clamp-d260',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], [
            'name' => 'Крепления и монтаж',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Теплов и Сухов',
            'slug' => 'teplov-i-suhov',
            'country' => 'Россия',
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.065',
            'name' => 'Хомут растяжки Теплов и Сухов 260',
            'slug' => 'homut-rastyajki-teplov-i-suhov-260',
            'price' => 10.08,
            'images' => ['product/009/009065/homut_r.jpeg'],
            'short_description' => 'Старая карточка с непроверенным изображением.',
            'content' => '<p>Старое описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc50-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001091',
            'external_sku' => 'TS.KMP.HMR.0260.74106',
            'barcode' => '4680205305887',
            'name' => 'Хомут растяжки D 260 (У)',
            'price' => 8.40,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 10.08,
            'stock_quantity' => 8,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cn',
            '--code' => ['УТ-00001091'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('10.08', $product->fresh()->price);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cn',
            '--code' => ['УТ-00001091'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Теплов и Сухов Хомут растяжки Ø260 мм', $product->name);
        $this->assertSame('14.40', $product->price);
        $this->assertSame('8', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('TS.KMP.HMR.0260.74106', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame('260 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('0,5 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame([
            'img/products/teplov-i-suhov/homut-rastyazhki-d260-clean.png',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/homut-rastyazhki-d260-clean.png'));
        $this->assertStringContainsString('resan.ru', $product->content_source_url);
        $this->assertStringContainsString('не является самостоятельным несущим элементом', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.KMP.HMR.0260.74106',
            'product_id' => $product->id,
            'product_sku' => 'PS-009.065',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cn',
            '--code' => ['УТ-00001091'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_sauna_windows_category_and_unmatched_400x400_vent_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-sauna-window-400',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->create([
            'name' => 'Бани и сауны',
            'slug' => 'bani-i-sauny',
            'parent_id' => 0,
        ]);
        $templateCategory = Category::query()->create([
            'name' => 'Двери для бани и сауны',
            'slug' => 'dveri-dlya-ban-i-saun',
            'parent_id' => $parent->id,
        ]);
        $templateBrand = Brand::query()->firstOrCreate([
            'name' => 'DoorWood',
            'slug' => 'doorwood',
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'brand_id' => $templateBrand->id,
            'sku' => 'PS-SAUNA-TEMPLATE',
            'name' => 'Коробка Осина (85*55*2,0/АВ/под 10мм)',
            'slug' => 'korobka-osina-855520avpod-10mm',
            'price' => 61,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc37-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001066',
            'external_sku' => null,
            'name' => 'Форточка 400*400',
            'price_byn' => null,
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-co',
            '--code' => ['УТ-00001066'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertDatabaseMissing('categories', ['slug' => 'okna-dlya-bani-i-sauny']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-co',
            '--code' => ['УТ-00001066'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $category = Category::query()->where('slug', 'okna-dlya-bani-i-sauny')->first();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertNotNull($category);
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame('Окна для бани и сауны', $category->name);
        $this->assertSame('main-child', $category->type);
        $this->assertTrue((bool) $category->is_active);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Форточка для бани 400×400 мм', $product->name);
        $this->assertSame('82.00', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertNull($product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('400×400 мм', collect($product->specs)->firstWhere('key', 'Размер')['value']);
        $this->assertSame([
            'img/products/sauna/fortochka-400x400-clean.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/sauna/fortochka-400x400-clean.jpg'));
        $this->assertStringContainsString('dymohodoff.by', $product->content_source_url);
        $this->assertStringContainsString('размер проёма', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001066',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $product->forceFill(['brand_id' => $templateBrand->id])->save();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-co',
            '--code' => ['УТ-00001066'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
        $this->assertNull($product->fresh()->brand_id);
        $this->assertSame(1, Category::query()->where('slug', 'okna-dlya-bani-i-sauny')->count());
    }

    public function test_it_links_and_refreshes_the_exact_unmatched_teplov_i_suhov_apron_d260(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-apron-d260',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zaglushki-dymohod'], [
            'name' => 'Заглушки и оголовки',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'teplov-i-suhov'], [
            'name' => 'Теплов и Сухов',
            'country' => 'Россия',
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008116',
            'name' => 'Теплов и Сухов Фартук D 260 (М)',
            'slug' => 'teplov-i-suxov-fartuk-d-260-m',
            'price' => 61.62,
            'images' => ['img/products/source-enrichment/watermarked-apron.jpeg'],
            'short_description' => 'Старое описание.',
            'content' => '<p>Старое описание.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cc36-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001048',
            'external_sku' => 'TS.KMP.FRK.0260.79671',
            'name' => 'Фартук D 260 (М)',
            'price' => 35.95,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 43.14,
            'stock_quantity' => 21,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cp',
            '--code' => ['УТ-00001048'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('61.62', $product->fresh()->price);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cp',
            '--code' => ['УТ-00001048'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Теплов и Сухов Фартук для дымохода Ø260 мм', $product->name);
        $this->assertSame('43.14', $product->price);
        $this->assertSame('21', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('TS.KMP.FRK.0260.79671', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame('260 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame([
            'img/products/teplov-i-suhov/fartuk-d260-clean.jpeg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/fartuk-d260-clean.jpeg'));
        $this->assertStringContainsString('resan.ru', $product->content_source_url);
        $this->assertStringContainsString('не заменяет обязательную теплоизоляцию', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.KMP.FRK.0260.79671',
            'product_id' => $product->id,
            'product_sku' => 'KOTLOV-008116',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cp',
            '--code' => ['УТ-00001048'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_albertoni_k04106_mixer_tube(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-albertoni-k04106',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zhestkaya-podvodka-dlya-vody'], [
            'name' => 'Жёсткая подводка для воды',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'albertoni'], [
            'name' => 'Albertoni',
            'country' => 'Италия',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008072',
            'name' => 'Albertoni 238/5 Трубка медная хромированная Ø10 мм, 5 м',
            'slug' => 'albertoni-2385-trubka-mednaia-xromirovannaia-o10-mm-5-m',
            'price' => 199,
            'images' => ['img/products/albertoni/template.webp'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cbf5-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001000',
            'external_sku' => null,
            'name' => 'Трубка для смесителя М10 35 cm',
            'price_byn' => null,
            'stock_quantity' => 14,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cq',
            '--code' => ['УТ-00001000'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertNull($item->fresh()->external_sku);
        $this->assertSame($productCount, Product::query()->count());

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cq',
            '--code' => ['УТ-00001000'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product;
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertNotNull($product);
        $this->assertSame('K04106', $item->external_sku);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Albertoni K04106 Трубка для смесителя M10×Ø10, 350 мм', $product->name);
        $this->assertSame('22.00', $product->price);
        $this->assertSame('14', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('K04106', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame('350 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('M10 × Ø10 мм', collect($product->specs)->firstWhere('key', 'Тип соединения')['value']);
        $this->assertSame([
            'img/products/albertoni/albertoni-k04106-m10-d10-350mm-clean.webp',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/albertoni/albertoni-k04106-m10-d10-350mm-clean.webp'));
        $this->assertStringContainsString('prom.ua', $product->content_source_url);
        $this->assertStringContainsString('цанговый фитинг', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('uah', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'K04106',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cq',
            '--code' => ['УТ-00001000'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
        $this->assertSame(1, Product::query()->where('name', $product->name)->count());
    }

    public function test_it_links_and_refreshes_the_exact_unmatched_eurotis_skac20_pipe(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-eurotis-skac20',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $pipes = Category::query()->create([
            'name' => 'Трубы и фитинги',
            'slug' => 'truby-i-fitingi',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $corrugated = Category::query()->create([
            'name' => 'Гофрированные трубы',
            'slug' => 'gofrirovanye-truby',
            'parent_id' => $pipes->id,
            'type' => 'catalog',
            'is_active' => false,
        ]);
        $oldCategory = Category::query()->create([
            'name' => 'Баки для воды',
            'slug' => 'kupeli',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $oldCategory->id,
            'brand_id' => null,
            'sku' => 'PS-005.763',
            'name' => 'Труба EUROTIS (Италия) из нержавеющей стали 3/4',
            'slug' => 'truba-eurotis-italiya-iz-nerzhaveyushhej-stali-34',
            'price' => 31,
            'images' => [
                'product/005/005763/765-truba-gofra.jpg',
                'product/005/005763/962-nhef-ujahf.jpg',
            ],
            'short_description' => 'Старое описание.',
            'content' => '<p>Старое описание.</p>',
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cbd8-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000971',
            'external_sku' => null,
            'name' => 'Труба гофрированная Eurotis d20 SKAC20',
            'price' => 17.27,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 20.72,
            'stock_quantity' => 7,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cr',
            '--code' => ['УТ-00000971'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertNull($item->fresh()->external_sku);
        $this->assertFalse((bool) $corrugated->fresh()->is_active);
        $this->assertSame('31.00', $product->fresh()->price);
        $this->assertDatabaseMissing('brands', ['slug' => 'eurotis']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cr',
            '--code' => ['УТ-00000971'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $corrugated->refresh();
        $brand = Brand::query()->where('slug', 'eurotis')->sole();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('SKAC20', $item->external_sku);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($corrugated->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertTrue((bool) $corrugated->is_active);
        $this->assertSame($pipes->id, $corrugated->parent_id);
        $this->assertSame('Гофрированные трубы из нержавеющей стали', $corrugated->h1);
        $this->assertStringContainsString('CSST', $corrugated->content);
        $this->assertSame('Eurotis TFA Труба гофрированная CSST AISI 304 Ø20 мм (DN15, 3/4″), 1 м', $product->name);
        $this->assertSame('49.00', $product->price);
        $this->assertSame('7', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertFalse((bool) $product->requires_price_confirmation);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Италия', $brand->country);
        $this->assertTrue((bool) $brand->is_active);
        $this->assertSame($corrugated->id, $product->category_id);
        $this->assertSame('DN15', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('20,0 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('3/4″', collect($product->specs)->firstWhere('key', 'Присоединительный размер')['value']);
        $this->assertSame([
            'product/005/005763/765-truba-gofra.jpg',
        ], $product->images);
        $this->assertStringContainsString('metagas.sk', $product->content_source_url);
        $this->assertStringContainsString('d20 относится к наружному диаметру', $product->content);
        $this->assertStringNotContainsString('рыночная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'SKAC20',
            'product_id' => $product->id,
            'product_sku' => 'PS-005.763',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-cr',
            '--code' => ['УТ-00000971'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame(1, Brand::query()->where('slug', 'eurotis')->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
        $this->assertTrue((bool) $corrugated->fresh()->is_active);
    }

    public function test_it_documents_an_unidentifiable_positive_stock_item_as_a_durable_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-angular-10x10',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cbff-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00001010',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Угловое соединение 10х10',
            'price' => null,
            'price_byn' => null,
            'stock_quantity' => 25,
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261013-cs',
            '--code' => ['УТ-00001010'],
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261013-cs',
            '--code' => ['УТ-00001010'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertNull($item->match_confidence);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('нескольким несовместимым типам товара', $dispute['reason']);
        $this->assertContains('артикул производителя', $dispute['required_data']);
        $this->assertCount(3, $dispute['evidence']);
        $this->assertSame('Угловое соединение 10х10', $dispute['source_identity']['name']);

        $stats = app(\App\Services\Integrations\CommerceMlCatalogImporter::class)
            ->rematchSource($source->code);
        $this->assertSame(['matched' => 0, 'suggested' => 0, 'ambiguous' => 0, 'unmatched' => 0], $stats);
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);

        $offerXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<КоммерческаяИнформация>
  <ПакетПредложений>
    <Предложения>
      <Предложение>
        <Ид>bbd2cbff-1f62-11e9-bad6-1c1b0d1de7c2</Ид>
        <Наименование>Угловое соединение 10х10</Наименование>
        <Количество>26</Количество>
      </Предложение>
    </Предложения>
  </ПакетПредложений>
</КоммерческаяИнформация>
XML;
        app(\App\Services\Integrations\CommerceMlCatalogImporter::class)->import($offerXml, $source->code);
        $item->refresh();
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertSame('26.000', $item->stock_quantity);
        $this->assertSame('research_dispute', $item->candidates[0]['type']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261013-cs',
            '--code' => ['УТ-00001010'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_unmatched_eurotis_skac25_pipe(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-eurotis-skac25',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $pipes = Category::query()->create([
            'name' => 'Трубы и фитинги',
            'slug' => 'truby-i-fitingi',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Гофрированные трубы',
            'slug' => 'gofrirovanye-truby',
            'parent_id' => $pipes->id,
            'type' => 'catalog',
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-005.763',
            'name' => 'Eurotis TFA Труба гофрированная CSST AISI 304 Ø20 мм (DN15, 3/4″), 1 м',
            'slug' => 'truba-eurotis-italiya-iz-nerzhaveyushhej-stali-34',
            'price' => 49,
            'images' => ['product/005/005763/765-truba-gofra.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cbd6-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000969',
            'external_sku' => null,
            'name' => 'Труба гофрированная Eurotis d25 SKAC25',
            'price' => 24.73,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 29.68,
            'stock_quantity' => 0.01,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $dryRunStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ct',
            '--code' => ['УТ-00000969'],
        ]);
        $this->assertSame(0, $dryRunStatus, \Illuminate\Support\Facades\Artisan::output());
        $this->assertNull($item->fresh()->product_id);
        $this->assertNull($item->fresh()->external_sku);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ct',
            '--code' => ['УТ-00000969'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'eurotis')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('SKAC25', $item->external_sku);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Eurotis TFA Труба гофрированная CSST AISI 304 Ø25 мм (DN20, 1″), 1 м', $product->name);
        $this->assertSame('72.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('DN20', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('25,0 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('1″', collect($product->specs)->firstWhere('key', 'Присоединительный размер')['value']);
        $this->assertSame(['product/005/005763/765-truba-gofra.jpg'], $product->images);
        $this->assertStringContainsString('metagas.sk', $product->content_source_url);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'SKAC25',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261013-ct',
            '--code' => ['УТ-00000969'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_unmatched_teplov_i_suhov_d250_tee(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-teplov-suhov-d250-tee',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'troyniki-mono'], [
            'name' => 'Тройники моно',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Теплов и Сухов',
            'slug' => 'teplov-i-suhov',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-008004',
            'name' => 'Теплов и Сухов Тройник 87° ТРМ(М)-Р 430-0.5 D150',
            'slug' => 'teplov-i-suhov-troynik-87-430-05-d150-template',
            'price' => 73.39,
            'images' => ['product/teplov-suhov/old-tee.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'teplov-suhov-tee-87-d250-430-08',
            'external_code' => 'УТ-00000915',
            'external_sku' => 'TS.FRT.T87.0250.68993',
            'name' => 'Тройник 87* ТРМ(М)-Р 430-0.8 D250',
            'price' => 93.84,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 112.61,
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $dryRunStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cu',
            '--code' => ['УТ-00000915'],
        ]);
        $this->assertSame(0, $dryRunStatus, \Illuminate\Support\Facades\Artisan::output());
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cu',
            '--code' => ['УТ-00000915'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('TS.FRT.T87.0250.68993', $item->external_sku);
        $this->assertSame('Теплов и Сухов Тройник 87° ТРМ(М)-Р AISI 430 0,8 мм Ø250', $product->name);
        $this->assertSame('122.00', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('87°', collect($product->specs)->firstWhere('key', 'Угол')['value']);
        $this->assertSame('250 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame([
            'img/products/teplov-i-suhov/troynik-87-trmm-r-430-1-0-d150.jpg',
        ], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/troynik-87-trmm-r-430-1-0-d150.jpg'));
        $this->assertStringContainsString('enginer-pro.ru', $product->content_source_url);
        $this->assertStringContainsString('ревизию', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.FRT.T87.0250.68993',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cu',
            '--code' => ['УТ-00000915'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_brandless_sml_sheet_and_its_fireproof_category(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-sml-sheet',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->firstOrCreate(['slug' => 'aksessuary-dlya-bani'], [
            'name' => 'Аксессуары для бани',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $templateCategory = Category::query()->firstOrCreate(['slug' => 'oblicovki'], [
            'name' => 'Облицовки',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $templateCategory->id,
            'sku' => 'KOTLOV-022510',
            'name' => 'Вермикулитовая плита Skamol V-1100-700 1260×1000×25 мм',
            'slug' => 'skamol-v1100-template-for-sml',
            'price' => 349,
            'images' => ['img/products/skamol/template.png'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cb67-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000858',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Стекломагниевый лист 1220*610*10',
            'price' => 28.33,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 34.00,
            'stock_quantity' => 28,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cv',
            '--code' => ['УТ-00000858'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cv',
            '--code' => ['УТ-00000858'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $category = Category::query()->where('slug', 'ognezashchitnye-plity-i-listy')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->external_sku);
        $this->assertNull($item->barcode);
        $this->assertSame('Стекломагниевый лист СМЛ 1220×610×10 мм', $product->name);
        $this->assertNull($product->brand_id);
        $this->assertSame('34.00', $product->price);
        $this->assertSame('28', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame('1220 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('610 мм', collect($product->specs)->firstWhere('key', 'Ширина')['value']);
        $this->assertSame('10 мм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame(['img/products/fireproof/sml-1220-610-10.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/fireproof/sml-1220-610-10.jpg'));
        $this->assertStringContainsString('vishop.by', $product->content_source_url);
        $this->assertStringContainsString('не заменяет расчёт', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00000858',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cv',
            '--code' => ['УТ-00000858'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_brandless_pervyi_ogonek_32_piece_firelighter(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-pervyi-ogonek',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Аксессуары для каминов',
            'slug' => 'aksessuary-kaminy',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000910',
            'name' => 'Nordflam Решетка каминная AERO 90×800 белая',
            'slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia',
            'price' => 104,
            'images' => ['img/products/nordflam/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'bbd2cb5f-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000850',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Средство для разжигания огня "Первый огонек" 2017',
            'price' => null,
            'price_byn' => null,
            'stock_quantity' => 870,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cw',
            '--code' => ['УТ-00000850'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cw',
            '--code' => ['УТ-00000850'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Первый огонёк Средство для розжига, 32 брикета', $product->name);
        $this->assertNull($product->brand_id);
        $this->assertSame('6.50', $product->price);
        $this->assertSame('870', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame('32 шт.', collect($product->specs)->firstWhere('key', 'Количество в упаковке')['value']);
        $this->assertSame(['img/products/firelighters/pervyi-ogonek-32-briquettes.png'], $product->images);
        $this->assertFileExists(public_path('img/products/firelighters/pervyi-ogonek-32-briquettes.png'));
        $this->assertStringContainsString('1000brest.by', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00000850',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cw',
            '--code' => ['УТ-00000850'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_documents_the_unidentifiable_vtpm25_mixing_valve_as_a_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-vtpm25',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b547050e-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000834',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Смесительные терм.клапаны на 3 вых. 1" VTPM25',
            'price' => null,
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-cx',
            '--code' => ['УТ-00000834'],
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-cx',
            '--code' => ['УТ-00000834'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertNull($item->match_confidence);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('VTPM25', $dispute['reason']);
        $this->assertContains('артикул производителя', $dispute['required_data']);
        $this->assertContains('пропускная способность Kvs', $dispute['required_data']);
        $this->assertCount(3, $dispute['evidence']);
        $this->assertSame('УТ-00000834', $dispute['source_identity']['external_code']);
        $this->assertSame('Смесительные терм.клапаны на 3 вых. 1" VTPM25', $dispute['source_identity']['name']);

        $stats = app(\App\Services\Integrations\CommerceMlCatalogImporter::class)
            ->rematchSource($source->code);
        $this->assertSame(['matched' => 0, 'suggested' => 0, 'ambiguous' => 0, 'unmatched' => 0], $stats);
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-cx',
            '--code' => ['УТ-00000834'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_ecolinex_two_metre_pellet_feeder(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-ecolinex-feeder',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate([
            'slug' => 'pelletnye-gorelki',
        ], [
            'name' => 'Пеллетные горелки',
            'parent_id' => 0,
        ]);
        Product::query()->firstOrCreate([
            'slug' => 'snek-podaci-pellet-oxi-sp4-70-70-2000-2-m',
        ], [
            'category_id' => $category->id,
            'sku' => 'KOTLOV-004507',
            'name' => 'OXI Шнек подачи пеллет ШП4-70-70-2000, 2 м',
            'price' => 1190,
            'images' => ['img/products/oxi/oxi-shp4-70-70-2000.png'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b54704b7-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000747',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Подача для пелет 2,0M - 15W - fi60mm Ecolinex',
            'price' => null,
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cy',
            '--code' => ['УТ-00000747'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cy',
            '--code' => ['УТ-00000747'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'ecolinex')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Ecolinex Подача шнековая для пеллет 2 м, 15 Вт, Ø60 мм', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Польша', $brand->country);
        $this->assertSame('832.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame('2000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('60 мм', collect($product->specs)->firstWhere('key', 'Диаметр трубы')['value']);
        $this->assertSame('15 Вт', collect($product->specs)->firstWhere('key', 'Мощность двигателя')['value']);
        $this->assertSame(['img/products/ecolinex/ecolinex-pellet-feeder-2m-15w-60mm.png'], $product->images);
        $this->assertFileExists(public_path('img/products/ecolinex/ecolinex-pellet-feeder-2m-15w-60mm.png'));
        $this->assertStringContainsString('eco-palnik.pl', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00000747',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cy',
            '--code' => ['УТ-00000747'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_psm_260_platform(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-psm-260',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate([
            'slug' => 'krepleniya-dymohod',
        ], [
            'name' => 'Крепления и монтаж',
            'parent_id' => 0,
        ]);
        Product::query()->firstOrCreate([
            'slug' => 'ploschadka-montajnaya-teplov-i-suhov-mono-pmm-r-aisi-304-08-mm',
        ], [
            'category_id' => $category->id,
            'sku' => 'PS-009.684',
            'name' => 'Площадка монтажная Теплов и Сухов Моно ПММ-Р AISI 304 0,8-120',
            'price' => 42.50,
            'images' => ['img/products/teplov-i-suhov/pmm-r-120.png'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b54704b6-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000746',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Площадка сквозная монтажная ПСМ D 260',
            'price' => null,
            'price_byn' => null,
            'stock_quantity' => 12,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cz',
            '--code' => ['УТ-00000746'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cz',
            '--code' => ['УТ-00000746'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'teplov-i-suhov')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Теплов и Сухов Площадка сквозная монтажная ПСМ Ø260', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Россия', $brand->country);
        $this->assertSame('77.00', $product->price);
        $this->assertSame('12', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $product->effectiveAvailabilityStatus());
        $this->assertSame('260 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('400×200×400 мм', collect($product->specs)->firstWhere('key', 'Габариты (Ш×В×Г)')['value']);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/psm-260-through-mounting-platform.png'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/psm-260-through-mounting-platform.png'));
        $this->assertStringContainsString('promindex.ru', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00000746',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-cz',
            '--code' => ['УТ-00000746'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_plum_ecotouch_850i_4_screw_panel(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-plum-ecotouch', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], ['name' => 'Регуляторы', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'kontroller-salus-pc11'], ['category_id' => $category->id, 'sku' => 'PS-005.590', 'name' => 'Контроллер SALUS PC11', 'price' => 190, 'images' => ['product/salus/pc11.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id, 'external_id' => 'b547043c-1f62-11e9-bad6-1c1b0d1de7c2', 'external_code' => 'УТ-00000624',
            'name' => 'Панель сенсорная ecoTOUCH для 850i 4 /винт.соед.', 'stock_quantity' => 1, 'stock_confirmed_at' => now(), 'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-da', '--code' => ['УТ-00000624']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-da', '--code' => ['УТ-00000624'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'plum')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('PLUM ecoTOUCH Сенсорная панель для ecoMAX 850i 4, винтовое подключение', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('565.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('PLUM ecoMAX 850i 4', collect($product->specs)->firstWhere('key', 'Совместимость')['value']);
        $this->assertSame('Винтовой клеммный разъём', collect($product->specs)->firstWhere('key', 'Тип подключения')['value']);
        $this->assertCount(2, $product->images);
        foreach ($product->images as $image) {
            $this->assertFileExists(public_path($image));
        }
        $this->assertStringContainsString('gemashop.de', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-da', '--code' => ['УТ-00000624'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_chistoglass_plus_250ml_fireplace_glass_cleaner(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-chistoglass-plus-250', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary-kaminy'], ['name' => 'Аксессуары для каминов', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia'], ['category_id' => $category->id, 'sku' => 'KOTLOV-000910', 'name' => 'Nordflam Решетка каминная AERO 90×800 белая', 'price' => 104, 'images' => ['img/products/nordflam/template.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'chistoglass-plus-250-production-row',
            'external_code' => 'УТ-00000621',
            'name' => 'Очиститель кам.стекол "ЧистоGLASS" плюс 250 мл',
            'price_byn' => 6.16,
            'stock_quantity' => 27,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-db', '--code' => ['УТ-00000621']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-db', '--code' => ['УТ-00000621'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'chistoglass')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('ЧистоGLASS+ Очиститель каминных стёкол, 250 мл', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Беларусь', $brand->country);
        $this->assertSame('11.18', $product->price);
        $this->assertSame('27', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('250 мл', collect($product->specs)->firstWhere('key', 'Объём')['value']);
        $this->assertSame('Вода, гидроксид натрия', collect($product->specs)->firstWhere('key', 'Состав')['value']);
        $this->assertSame(['img/products/chistoglass/chistoglass-plus-fireplace-glass-cleaner-250ml.png'], $product->images);
        $this->assertFileExists(public_path('img/products/chistoglass/chistoglass-plus-fireplace-glass-cleaner-250ml.png'));
        $this->assertStringContainsString('flagma.by', $product->content_source_url);
        $this->assertStringContainsString('3–5 минут', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-db', '--code' => ['УТ-00000621'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_chistoglass_700ml_fireplace_glass_cleaner(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-chistoglass-700', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary-kaminy'], ['name' => 'Аксессуары для каминов', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia'], ['category_id' => $category->id, 'sku' => 'KOTLOV-000910', 'name' => 'Nordflam Решетка каминная AERO 90×800 белая', 'price' => 104, 'images' => ['img/products/nordflam/template.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'chistoglass-700-production-row',
            'external_code' => 'УТ-00000620',
            'name' => 'Очиститель для каминных стекол "ЧистоGLASS" 700 мл',
            'price_byn' => 10.56,
            'stock_quantity' => 16,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dc', '--code' => ['УТ-00000620']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dc', '--code' => ['УТ-00000620'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'chistoglass')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('ЧистоGLASS Очиститель каминных стёкол, 700 мл', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('18.79', $product->price);
        $this->assertSame('16', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('700 мл', collect($product->specs)->firstWhere('key', 'Объём')['value']);
        $this->assertSame('5×10×20 см', collect($product->specs)->firstWhere('key', 'Габариты упаковки')['value']);
        $this->assertSame('ИП Потапов Роман Александрович', collect($product->specs)->firstWhere('key', 'Изготовитель')['value']);
        $this->assertSame(['img/products/chistoglass/chistoglass-fireplace-glass-cleaner-700ml.png'], $product->images);
        $this->assertFileExists(public_path('img/products/chistoglass/chistoglass-fireplace-glass-cleaner-700ml.png'));
        $this->assertStringContainsString('oma.by', $product->content_source_url);
        $this->assertStringContainsString('3–5 минут', $product->content);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('byn', mb_strtolower($product->content));

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dc', '--code' => ['УТ-00000620'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_475x580_hearth_sheet(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-tis-hearth-sheet-475x580', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'aksessuary-kaminy'], ['name' => 'Аксессуары для каминов', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia'], ['category_id' => $category->id, 'sku' => 'KOTLOV-000910', 'name' => 'Nordflam Решетка каминная AERO 90×800 белая', 'price' => 104, 'images' => ['img/products/nordflam/template.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b547039e-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000466',
            'external_sku' => 'TS.KMP.LST.0000.64132',
            'barcode' => '4680205250682',
            'name' => 'Лист предтопочный 475 х 580',
            'price_byn' => 38.60,
            'stock_quantity' => 17,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dd', '--code' => ['УТ-00000466']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dd', '--code' => ['УТ-00000466'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'teplov-i-suhov')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Теплов и Сухов Лист предтопочный 475 × 580 мм', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('43.00', $product->price);
        $this->assertSame('17', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('475 × 580 мм', collect($product->specs)->firstWhere('key', 'Размер')['value']);
        $this->assertSame('TS.KMP.LST.0000.64132', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame('4680205250682', collect($product->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/list-predtopochnyy-475x580.jpeg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/list-predtopochnyy-475x580.jpeg'));
        $this->assertStringContainsString('pechiteplov.ru', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.KMP.LST.0000.64132',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-dd', '--code' => ['УТ-00000466'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_siemens_gdb331_2e_linear_damper_actuator(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-siemens-gdb331-2e', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'regulyatory'], ['name' => 'Регуляторы', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'kontroller-salus-pc11'], ['category_id' => $category->id, 'sku' => 'KOTLOV-003041', 'name' => 'Контроллер Salus PC11', 'price' => 205, 'images' => ['img/products/salus/template.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'siemens-gdb331-2e-production-row',
            'external_code' => 'УТ-00000459',
            'name' => 'Линейный привод АС240В, 3-поз.125НМ 150с Siemens',
            'price_byn' => null,
            'stock_quantity' => 2,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-de', '--code' => ['УТ-00000459']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-de', '--code' => ['УТ-00000459'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'siemens')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame('GDB331.2E', $item->external_sku);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Siemens GDB331.2E Линейный привод воздушной заслонки, 230 В', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('443.00', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('125 Н', collect($product->specs)->firstWhere('key', 'Линейное усилие')['value']);
        $this->assertSame('230 В AC', collect($product->specs)->firstWhere('key', 'Напряжение питания')['value']);
        $this->assertSame('7612914003386', collect($product->specs)->firstWhere('key', 'GTIN')['value']);
        $this->assertSame(['img/products/siemens/siemens-gdb331-2e.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/siemens/siemens-gdb331-2e.jpg'));
        $this->assertStringContainsString('hvac.de', $product->content_source_url);
        $this->assertStringNotContainsString('125 н·м', mb_strtolower($product->content));
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'GDB331.2E',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-de', '--code' => ['УТ-00000459'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_documents_ksru_180_as_a_priced_but_unidentifiable_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-ksru-180',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b5470393-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000455',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'КСРУ-180',
            'price_byn' => 39.34,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-df',
            '--code' => ['УТ-00000455'],
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-df',
            '--code' => ['УТ-00000455'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('КСРУ-180', $dispute['reason']);
        $this->assertStringContainsString('39,34 BYN', $dispute['reason']);
        $this->assertContains('артикул производителя', $dispute['required_data']);
        $this->assertContains('фотография маркировки или упаковки', $dispute['required_data']);
        $this->assertCount(3, $dispute['evidence']);
        $this->assertSame('УТ-00000455', $dispute['source_identity']['external_code']);
        $this->assertSame('КСРУ-180', $dispute['source_identity']['name']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-df',
            '--code' => ['УТ-00000455'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);
    }

    public function test_it_documents_ksr_80_as_an_unverifiable_discontinued_variant(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-ksr-80',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b5470392-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000454',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'КСР-80',
            'price_byn' => null,
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dg',
            '--code' => ['УТ-00000454'],
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dg',
            '--code' => ['УТ-00000454'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('КСР-80', $dispute['reason']);
        $this->assertStringContainsString('диаметром 80 мм', $dispute['reason']);
        $this->assertContains('артикул производителя', $dispute['required_data']);
        $this->assertContains('фотография маркировки или упаковки', $dispute['required_data']);
        $this->assertCount(3, $dispute['evidence']);
        $this->assertSame('УТ-00000454', $dispute['source_identity']['external_code']);
        $this->assertSame('КСР-80', $dispute['source_identity']['name']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dg',
            '--code' => ['УТ-00000454'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);
    }

    public function test_it_documents_the_model_less_plum_pellet_controller_as_a_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-plum-controller',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f3671-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000357',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Контроллер пеллетных горелок PLUM',
            'price_byn' => null,
            'stock_quantity' => 2,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dh',
            '--code' => ['УТ-00000357'],
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dh',
            '--code' => ['УТ-00000357'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('ecoMAX/evoMAX', $dispute['reason']);
        $this->assertStringContainsString('не связывается со старыми пультами OXI', $dispute['reason']);
        $this->assertContains('точная модель и полное обозначение контроллера', $dispute['required_data']);
        $this->assertContains('фотография шильдика и разъёмов', $dispute['required_data']);
        $this->assertCount(3, $dispute['evidence']);
        $this->assertSame('УТ-00000357', $dispute['source_identity']['external_code']);
        $this->assertSame('Контроллер пеллетных горелок PLUM', $dispute['source_identity']['name']);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dh',
            '--code' => ['УТ-00000357'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame('manual_research_unresolved', $item->fresh()->match_method);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_ppu_insulation_kit(): void
    {
        $source = IntegrationSource::query()->create(['code' => 'onec-sbg-unmatched-ppu-insulation', 'name' => '1С СанБизнесГруп', 'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'], 'is_active' => true, 'update_stock' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], ['name' => 'Крепления для дымохода', 'parent_id' => 0]);
        Product::query()->firstOrCreate(['slug' => 'ploschadka-montajnaya-teplov-i-suhov-mono-pmm-r-aisi-304-08-mm'], ['category_id' => $category->id, 'sku' => 'KOTLOV-003039', 'name' => 'Площадка монтажная Теплов и Сухов', 'price' => 100, 'images' => ['img/products/teplov-i-suhov/template.jpg'], 'is_active' => true, 'is_archived' => false]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f365b-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000335',
            'external_sku' => 'TS.KMP.PPU.0000.63144',
            'name' => 'Комплект теплоиз. 80х400 для ППУ - Р 500х500',
            'price_byn' => null,
            'stock_quantity' => 4,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-di', '--code' => ['УТ-00000335']])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-di', '--code' => ['УТ-00000335'], '--apply' => true])->assertSuccessful();

        $item->refresh();
        $product = $item->product()->sole();
        $brand = Brand::query()->where('slug', 'teplov-i-suhov')->sole();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_created', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertSame('Теплов и Сухов Комплект теплоизоляционный 80 × 400 мм для ППУ-Р 500 × 500 мм', $product->name);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('16.00', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('80 × 400 мм', collect($product->specs)->firstWhere('key', 'Размер элементов')['value']);
        $this->assertSame('ППУ-Р 500 × 500 мм', collect($product->specs)->firstWhere('key', 'Совместимый проход')['value']);
        $this->assertSame('TS.KMP.PPU.0000.63144', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/teploizolyatsionnyy-komplekt-80x400-ppu-r-500x500.png'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/teploizolyatsionnyy-komplekt-80x400-ppu-r-500x500.png'));
        $this->assertStringContainsString('radiat.ru', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('rub', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.KMP.PPU.0000.63144',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', ['--batch' => 'unmatched-researched-20261014-di', '--code' => ['УТ-00000335'], '--apply' => true])->assertSuccessful();
        $this->assertSame($productCount + 1, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_links_and_refreshes_the_exact_darco_roz120_black_rosette(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-darco-roz120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'krepleniya-dymohod'], [
            'name' => 'Крепления и монтаж',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'darco'], [
            'name' => 'Darco',
            'country' => 'Польша',
            'is_active' => true,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-009.438',
            'name' => 'Розета Darco ROZ120-CZ (ML)',
            'slug' => 'rozeta-darco-roz120-cz-ml',
            'price' => 19,
            'images' => ['product/009/009438/old.jpg'],
            'short_description' => 'Старое общее описание.',
            'content' => '<p>Старое общее описание.</p>',
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f3652-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000326',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Кольцо декоративное черное 120-CZ (ML)',
            'price_byn' => 9.60,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dj',
            '--code' => ['УТ-00000326'],
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);
        $this->assertSame('19.00', $product->fresh()->price);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dj',
            '--code' => ['УТ-00000326'],
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $product->refresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('WC-ROZ120-CZ', $item->external_sku);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($category->id, $item->target_category_id);
        $this->assertNull($item->candidates);
        $this->assertSame('Darco ROZ120-CZ (ML) Кольцо декоративное чёрное Ø120 мм', $product->name);
        $this->assertSame('12.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertFalse((bool) $product->requires_price_confirmation);
        $this->assertSame('ROZ120-CZ (ML)(SPK)', collect($product->specs)->firstWhere('key', 'Модель')['value']);
        $this->assertSame('WC-ROZ120-CZ', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
        $this->assertSame(['img/products/darco/darco-roz120-cz-black-rosette.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/darco/darco-roz120-cz-black-rosette.jpg'));
        $this->assertStringContainsString('kominki-kominy.pl', $product->content_source_url);
        $this->assertStringNotContainsString('розничная цена', mb_strtolower($product->content));
        $this->assertStringNotContainsString('pln', mb_strtolower($product->content));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'WC-ROZ120-CZ',
            'product_id' => $product->id,
            'product_sku' => 'PS-009.438',
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dj',
            '--code' => ['УТ-00000326'],
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_it_creates_the_exact_dm_r_deflectors_and_documents_the_unidentified_gsk_collector(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-dm-r-deflectors',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'zonty-deflektory'], [
            'name' => 'Зонты и дефлекторы',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->firstOrCreate(['sku' => 'PS-007.806'], [
            'category_id' => $category->id,
            'name' => 'Зонт дымохода D115',
            'slug' => 'zont-dymohoda-d115-template',
            'price' => 20,
            'images' => ['img/products/template-zont.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);

        $items = collect([
            [
                'external_id' => 'af1f35b5-1f62-11e9-bad6-1c1b0d1de7c2',
                'external_code' => 'УТ-00000169',
                'external_sku' => 'TS.FRT.DFR.0180.72739',
                'barcode' => '4680205311772',
                'name' => 'Дефлектор ДМ-Р 430-0.5 D180 М',
                'price_byn' => 60,
                'stock_quantity' => 4,
            ],
            [
                'external_id' => 'af1f35b3-1f62-11e9-bad6-1c1b0d1de7c2',
                'external_code' => 'УТ-00000167',
                'external_sku' => 'TS.FRT.DFR.0130.74685',
                'barcode' => null,
                'name' => 'Дефлектор ДМ-Р 430-0.5 D130 М У1',
                'price_byn' => 43.20,
                'stock_quantity' => 6,
            ],
        ])->map(fn (array $data): IntegrationProduct => IntegrationProduct::query()->create(array_merge($data, [
            'integration_source_id' => $source->id,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ])));
        $gsk = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f364f-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000323',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Коллектор Distant GSK 4.22',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dk',
        ])->assertSuccessful();
        $this->assertTrue($items->every(fn (IntegrationProduct $item): bool => $item->fresh()->product_id === null));

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dk',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($productCount + 2, Product::query()->count());
        $d180 = $items[0]->fresh()->product()->sole();
        $d130 = $items[1]->fresh()->product()->sole();
        $this->assertSame('matched', $items[0]->fresh()->match_status);
        $this->assertSame('manual_created', $items[0]->fresh()->match_method);
        $this->assertSame($category->id, $items[0]->fresh()->target_category_id);
        $this->assertSame('60.00', $d180->price);
        $this->assertSame('4', (string) $d180->stock_qty);
        $this->assertSame('180 мм', collect($d180->specs)->firstWhere('key', 'Диаметр подключения')['value']);
        $this->assertSame('4680205311772', collect($d180->specs)->firstWhere('key', 'Штрихкод')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/deflektor-dm-r-430-05-d180.jpg'], $d180->images);
        $this->assertSame('43.20', $d130->price);
        $this->assertSame('6', (string) $d130->stock_qty);
        $this->assertSame('130 мм', collect($d130->specs)->firstWhere('key', 'Диаметр подключения')['value']);
        $this->assertSame('У1', collect($d130->specs)->firstWhere('key', 'Исполнение')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/deflektor-dm-r-430-05-d130.jpg'], $d130->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/deflektor-dm-r-430-05-d180.jpg'));
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/deflektor-dm-r-430-05-d130.jpg'));
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.FRT.DFR.0180.72739',
            'product_id' => $d180->id,
            'confidence' => 'manual',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'TS.FRT.DFR.0130.74685',
            'product_id' => $d130->id,
            'confidence' => 'manual',
            'is_active' => true,
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dk',
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount + 2, Product::query()->count());

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dl',
        ])->assertSuccessful();
        $this->assertSame('unmatched', $gsk->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dl',
            '--apply' => true,
        ])->assertSuccessful();
        $gsk->refresh();
        $this->assertNull($gsk->product_id);
        $this->assertSame('ambiguous', $gsk->match_status);
        $this->assertSame('manual_research_unresolved', $gsk->match_method);
        $this->assertSame('research_dispute', $gsk->candidates[0]['type']);
        $this->assertStringContainsString('производитель', $gsk->candidates[0]['reason']);
        $this->assertContains('закупочная цена', $gsk->candidates[0]['required_data']);
        $this->assertCount(3, $gsk->candidates[0]['evidence']);
        $this->assertSame($productCount + 2, Product::query()->count());
    }

    public function test_it_creates_the_exact_cast_plate_and_hanging_heater_and_documents_two_generic_rows(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-plate-heater',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $casting = Category::query()->firstOrCreate(['slug' => 'pechnoe-i-kaminnoe-lite'], [
            'name' => 'Печное и каминное литьё',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $accessories = Category::query()->firstOrCreate(['slug' => 'aksessuary-dlya-bani'], [
            'name' => 'Аксессуары для бани',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $casting->id,
            'name' => 'Плита Т202 с двумя конфорками Fireway',
            'slug' => 'fireway-plita-t202-dve-konforki',
            'price' => 398,
            'images' => ['img/products/template-plate.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        Product::query()->create([
            'category_id' => $accessories->id,
            'sku' => 'PS-009.932',
            'name' => 'Царь-Печи Буржуйка 8 мм',
            'slug' => 'tsar-pechi-burzhuyka-template',
            'price' => 100,
            'images' => ['img/products/template-tsar-pechi.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);

        $plate = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '6c628574-be8d-11ee-abaa-1c1b0d1de7c2',
            'external_code' => 'УТ-00008383',
            'name' => 'Плита цельная ПЦ (Б) 710х410мм',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $heater = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f360e-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000258',
            'name' => 'Каменка навесная "30"',
            'price_byn' => 92,
            'stock_quantity' => 2,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $headrest = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '783fcb3d-d82b-11ed-a6f2-1c1b0d1de7c2',
            'external_code' => 'УТ-00008006',
            'name' => 'Подголовник деревянный ComfortProm',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $cauldron = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f360b-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000255',
            'name' => 'Казан 20 л',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dm',
        ])->assertSuccessful();
        $this->assertNull($plate->fresh()->product_id);
        $this->assertNull($heater->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dm',
            '--apply' => true,
        ])->assertSuccessful();
        $plateProduct = $plate->fresh()->product()->sole();
        $heaterProduct = $heater->fresh()->product()->sole();
        $this->assertSame($productCount + 2, Product::query()->count());
        $this->assertSame('149.00', $plateProduct->price);
        $this->assertSame('1', (string) $plateProduct->stock_qty);
        $this->assertSame($casting->id, $plateProduct->category_id);
        $this->assertSame('23 кг', collect($plateProduct->specs)->firstWhere('key', 'Масса')['value']);
        $this->assertSame(['img/products/balezino/plita-celnaya-pc-b-710x410.jpg'], $plateProduct->images);
        $this->assertSame('92.00', $heaterProduct->price);
        $this->assertSame('2', (string) $heaterProduct->stock_qty);
        $this->assertSame($accessories->id, $heaterProduct->category_id);
        $this->assertSame('до 30 кг', collect($heaterProduct->specs)->firstWhere('key', 'Масса камней')['value']);
        $this->assertSame(['img/products/tsar-pechi/kamenka-navesnaya-30.jpg'], $heaterProduct->images);
        $this->assertFileExists(public_path('img/products/balezino/plita-celnaya-pc-b-710x410.jpg'));
        $this->assertFileExists(public_path('img/products/tsar-pechi/kamenka-navesnaya-30.jpg'));

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dn',
        ])->assertSuccessful();
        $this->assertSame('unmatched', $headrest->fresh()->match_status);
        $this->assertSame('unmatched', $cauldron->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dn',
            '--apply' => true,
        ])->assertSuccessful();
        $headrest->refresh();
        $cauldron->refresh();
        $this->assertSame('ambiguous', $headrest->match_status);
        $this->assertSame('manual_research_unresolved', $headrest->match_method);
        $this->assertContains('закупочная и розничная цена', $headrest->candidates[0]['required_data']);
        $this->assertSame('ambiguous', $cauldron->match_status);
        $this->assertSame('manual_research_unresolved', $cauldron->match_method);
        $this->assertCount(3, $cauldron->candidates[0]['evidence']);
        $this->assertSame($productCount + 2, Product::query()->count());
    }

    public function test_it_excludes_the_confirmed_archived_ct6w_sensor_without_creating_a_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-archived-ct6w',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $sensor = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f35a0-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000148',
            'name' => 'Датчик температуры коллектора CT6W L=1m OLE-PRO',
            'price_byn' => null,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-unmatched-20261014-do',
        ])->assertSuccessful();
        $this->assertSame('unmatched', $sensor->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-unmatched-20261014-do',
            '--apply' => true,
        ])->assertSuccessful();
        $sensor->refresh();
        $this->assertNull($sensor->product_id);
        $this->assertNull($sensor->target_category_id);
        $this->assertSame('ignored', $sensor->match_status);
        $this->assertSame('manual_ignore', $sensor->match_method);
        $this->assertSame($productCount, Product::query()->count());

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-unmatched-20261014-do',
            '--apply' => true,
        ])->assertSuccessful();
        $this->assertSame($productCount, Product::query()->count());
    }

    public function test_it_creates_the_three_current_plum_sensor_cards(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-plum-sensors',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'datchiki'], [
            'name' => 'Датчики',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-004.481',
            'name' => 'Шаблон датчика',
            'slug' => 'sensor-template-plum',
            'price' => 1,
            'images' => ['img/products/template-sensor.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);

        $rows = [
            ['af1f359b-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000143', 'Датчик температуры ECOMAX 4 M PLUM ECOMAX', 1],
            ['af1f3599-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000141', 'Датчик пламени ОСР-2 PLUM Polska', 2],
            ['af1f3598-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000140', 'Датчик комнатный СТ-7 PLUM Polska', 2],
        ];
        $items = collect($rows)->mapWithKeys(function (array $row) use ($source): array {
            $item = IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => $row[0],
                'external_code' => $row[1],
                'name' => $row[2],
                'price_byn' => null,
                'stock_quantity' => $row[3],
                'stock_confirmed_at' => now(),
                'match_status' => 'unmatched',
            ]);

            return [$row[1] => $item];
        });
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dp',
        ])->assertSuccessful();
        $this->assertTrue($items->every(fn (IntegrationProduct $item): bool => $item->fresh()->product_id === null));

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dp',
            '--apply' => true,
        ])->assertSuccessful();

        $ct4 = $items['УТ-00000143']->fresh()->product()->sole();
        $ocp2 = $items['УТ-00000141']->fresh()->product()->sole();
        $ct7 = $items['УТ-00000140']->fresh()->product()->sole();
        $this->assertSame($productCount + 3, Product::query()->count());
        $this->assertSame(['52.00', '112.00', '67.00'], [$ct4->price, $ocp2->price, $ct7->price]);
        $this->assertSame(['1', '2', '2'], [(string) $ct4->stock_qty, (string) $ocp2->stock_qty, (string) $ct7->stock_qty]);
        $this->assertSame([$category->id, $category->id, $category->id], [$ct4->category_id, $ocp2->category_id, $ct7->category_id]);
        $this->assertSame('4 м', collect($ct4->specs)->firstWhere('key', 'Длина кабеля')['value']);
        $this->assertSame('OCP-5 / OCP-6', collect($ocp2->specs)->firstWhere('key', 'Актуальные совместимые исполнения')['value']);
        $this->assertSame('Настенный', collect($ct7->specs)->firstWhere('key', 'Монтаж')['value']);
        $this->assertSame(['img/products/plum/plum-ct4-4m.webp'], $ct4->images);
        $this->assertSame(['img/products/plum/plum-ocp-2.webp'], $ocp2->images);
        $this->assertSame(['img/products/plum/plum-ct7.webp'], $ct7->images);
        $this->assertFileExists(public_path('img/products/plum/plum-ct4-4m.webp'));
        $this->assertFileExists(public_path('img/products/plum/plum-ocp-2.webp'));
        $this->assertFileExists(public_path('img/products/plum/plum-ct7.webp'));
    }

    public function test_it_creates_six_whole_unit_air_ducts_and_flags_two_fractional_rows(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-flexible-air-ducts',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->firstOrCreate(['slug' => 'komplektuyushhie-dlya-otopleniya'], [
            'name' => 'Комплектующие для отопления',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $parent->id,
            'sku' => 'PS-005.763',
            'name' => 'Шаблон гофрированной трубы',
            'slug' => 'corrugated-pipe-template-for-air-ducts',
            'price' => 1,
            'images' => ['img/products/template-pipe.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);

        $rows = [
            ['af1f3577-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000107', 'Воздуховод гибкий гофррованный 150 (алюминий 3 м)', 7.60, 1],
            ['af1f3574-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000104', 'Воздуховод гибкий гофрированный 200 (алюминий)', 8.56, 1],
            ['af1f3571-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000101', 'Воздуховод гибкий гофрированный 130 (алюминий)', 4.64, 2],
            ['af1f356f-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000099', 'Воздуховод гибкий гофрированный 130 (3м нерж.ст.)', 22.00, 6.5],
            ['af1f356b-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000095', 'Воздуховод гибкий гофрированный 120 (3м нерж.ст)', 18.56, 0.5],
            ['af1f356a-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000094', 'Воздуховод гибкий гофрированный 110 (алюминий)', 3.28, 2],
            ['af1f3568-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000092', 'Воздуховод гибкий гофрированный 110 (3м нерж.ст)', 17.68, 8],
            ['af1f3567-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000091', 'Воздуховод гибкий гофрированный 100 (алюминий)', 3.84, 3],
        ];
        $items = collect($rows)->mapWithKeys(function (array $row) use ($source): array {
            $item = IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => $row[0],
                'external_code' => $row[1],
                'name' => $row[2],
                'price_byn' => $row[3],
                'stock_quantity' => $row[4],
                'stock_confirmed_at' => now(),
                'match_status' => 'unmatched',
            ]);

            return [$row[1] => $item];
        });
        $productCount = Product::query()->count();

        $dryRunStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dq',
        ]);
        $this->assertSame(0, $dryRunStatus, \Illuminate\Support\Facades\Artisan::output());
        $this->assertSame($productCount, Product::query()->count());

        $applyStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dq',
            '--apply' => true,
        ]);
        $this->assertSame(0, $applyStatus, \Illuminate\Support\Facades\Artisan::output());
        $products = collect(['УТ-00000107', 'УТ-00000104', 'УТ-00000101', 'УТ-00000094', 'УТ-00000092', 'УТ-00000091'])
            ->map(fn (string $code): Product => $items[$code]->fresh()->product()->sole());
        $category = Category::query()->where('slug', 'gibkie-vozduhovody')->sole();
        $this->assertSame($productCount + 6, Product::query()->count());
        $this->assertSame($parent->id, $category->parent_id);
        $this->assertSame(['7.60', '8.56', '4.64', '3.28', '17.68', '3.84'], $products->pluck('price')->all());
        $this->assertSame(['1', '1', '2', '2', '8', '3'], $products->map(fn (Product $product): string => (string) $product->stock_qty)->all());
        $this->assertTrue($products->every(fn (Product $product): bool => $product->category_id === $category->id && $product->brand_id === null));
        $this->assertSame('Нержавеющая сталь', collect($products[4]->specs)->firstWhere('key', 'Материал')['value']);
        $this->assertFileExists(public_path('img/products/air-ducts/flexible-aluminum-3m.jpg'));
        $this->assertFileExists(public_path('img/products/air-ducts/flexible-stainless-3m.jpg'));

        $disputeStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dr',
            '--apply' => true,
        ]);
        $this->assertSame(0, $disputeStatus, \Illuminate\Support\Facades\Artisan::output());
        foreach (['УТ-00000099', 'УТ-00000095'] as $code) {
            $item = $items[$code]->fresh();
            $this->assertNull($item->product_id);
            $this->assertSame('ambiguous', $item->match_status);
            $this->assertSame('manual_research_unresolved', $item->match_method);
            $this->assertContains('единица измерения в 1С', $item->candidates[0]['required_data']);
        }
        $this->assertSame($productCount + 6, Product::query()->count());
    }

    public function test_it_links_two_old_burner_rows_to_the_existing_clearance_cards(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-old-burner-packages',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Пеллетные горелки',
            'slug' => 'pelletnye-gorelki',
            'parent_id' => 0,
        ]);
        $products = collect([
            'УТ-00000133' => [
                'sku' => 'KTL-HOTTA-CERAMIK-30-K3',
                'name' => 'HOTTA Ceramik 30 кВт с Wi‑Fi — комплект распродажи',
                'slug' => 'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3',
                'price' => 4600,
                'stock_qty' => 3,
                'images' => ['img/products/hotta/hotta-ceramik-30.jpg'],
            ],
            'УТ-00000130' => [
                'sku' => 'PS-012.203',
                'name' => 'KOTLOV XO Ceramic PRO 100 кВт',
                'slug' => 'pelletnaya-gorelka-kotlov-xo-ceramic-pro-100-kvt',
                'price' => 12960,
                'stock_qty' => 1,
                'images' => ['img/products/kotlov-xo/ceramic-pro-100.jpg'],
            ],
        ])->map(fn (array $data): Product => Product::query()->create($data + [
            'category_id' => $category->id,
            'brand_id' => null,
            'short_description' => 'Актуальная карточка распродажи.',
            'content' => '<p>Комплект с новой автоматикой XO.</p>',
            'is_active' => true,
            'is_archived' => false,
        ]));
        $rows = [
            ['af1f3591-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000133', 'Горелка OXI Ceramik+F30 HOTTO'],
            ['af1f358e-1f62-11e9-bad6-1c1b0d1de7c2', 'УТ-00000130', 'Горелка Cyberia 100'],
        ];
        $items = collect($rows)->mapWithKeys(function (array $row) use ($source): array {
            $item = IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => $row[0],
                'external_code' => $row[1],
                'name' => $row[2],
                'price_byn' => null,
                'stock_quantity' => 1,
                'stock_confirmed_at' => now(),
                'match_status' => 'unmatched',
            ]);

            return [$row[1] => $item];
        });
        $productCount = Product::query()->count();

        $dryRunStatus = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ds',
        ]);
        $this->assertSame(0, $dryRunStatus, \Illuminate\Support\Facades\Artisan::output());
        $this->assertTrue($items->every(fn (IntegrationProduct $item): bool => $item->fresh()->product_id === null));

        $status = \Illuminate\Support\Facades\Artisan::call('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ds',
            '--apply' => true,
        ]);
        $this->assertSame(0, $status, \Illuminate\Support\Facades\Artisan::output());
        $oxi = $items['УТ-00000133']->fresh();
        $cyberia = $items['УТ-00000130']->fresh();
        $this->assertSame($productCount, Product::query()->count());
        $this->assertSame([$products['УТ-00000133']->id, $products['УТ-00000130']->id], [$oxi->product_id, $cyberia->product_id]);
        $this->assertSame(['matched', 'matched'], [$oxi->match_status, $cyberia->match_status]);
        $this->assertSame(['manual_search', 'manual_search'], [$oxi->match_method, $cyberia->match_method]);
        $this->assertSame([$category->id, $category->id], [$oxi->target_category_id, $cyberia->target_category_id]);
        $this->assertNull($oxi->candidates);
        $this->assertNull($cyberia->candidates);
        $this->assertSame(['4600.00', '12960.00'], $products->map(fn (Product $product): string => $product->fresh()->price)->values()->all());
        $this->assertSame(['1', '1'], $products->map(fn (Product $product): string => (string) $product->fresh()->stock_qty)->values()->all());
        $this->assertTrue($products->every(fn (Product $product): bool => $product->fresh()->is_active && ! $product->fresh()->is_archived));
        $this->assertStringContainsString('новой автоматикой XO', $products['УТ-00000133']->fresh()->content);
    }

    public function test_it_corrects_the_two_metre_duct_then_creates_two_distinct_three_metre_cards(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-next-air-ducts',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Гибкие воздуховоды',
            'slug' => 'gibkie-vozduhovody',
            'parent_id' => 0,
        ]);
        $aluminium = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'KOTLOV-022720',
            'name' => 'Воздуховод гибкий алюминиевый Ø100 мм, 3 м',
            'slug' => 'vozduxovod-gibkii-aliuminievyi-o100-mm-3-m',
            'price' => 3.84,
            'stock_qty' => 3,
            'images' => ['img/products/air-ducts/flexible-aluminum-3m.jpg'],
            'specs' => [['key' => 'Длина', 'value' => '3 м']],
            'is_active' => true,
            'is_archived' => false,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => null,
            'sku' => 'KOTLOV-022719',
            'name' => 'Воздуховод гибкий из нержавеющей стали Ø110 мм, 3 м',
            'slug' => 'vozduxovod-gibkii-iz-nerzaveiushhei-stali-o110-mm-3-m',
            'price' => 17.68,
            'stock_qty' => 8,
            'images' => ['img/products/air-ducts/flexible-stainless-3m.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $items = collect([
            'УТ-00000091' => ['af1f3567-1f62-11e9-bad6-1c1b0d1de7c2', 'Воздуховод гибкий гофрированный 100 (алюминий)', 3.84, 3],
            'УТ-00000090' => ['af1f3566-1f62-11e9-bad6-1c1b0d1de7c2', 'Воздуховод гибкий гофрированный 100 (алюминий 3 м)', 5.28, 2],
            'УТ-00000089' => ['af1f3565-1f62-11e9-bad6-1c1b0d1de7c2', 'Воздуховод гибкий гофрированный 100 (3м нерж.ст)', 16.80, 3],
        ])->mapWithKeys(function (array $row, string $code) use ($source, $aluminium): array {
            return [$code => IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => $row[0],
                'external_code' => $code,
                'name' => $row[1],
                'price_byn' => $row[2],
                'stock_quantity' => $row[3],
                'stock_confirmed_at' => now(),
                'product_id' => $code === 'УТ-00000091' ? $aluminium->id : null,
                'match_status' => $code === 'УТ-00000091' ? 'matched' : 'ambiguous',
                'match_method' => $code === 'УТ-00000091' ? 'manual_created' : null,
            ])];
        });
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dt',
        ])->assertSuccessful();
        $this->assertSame('vozduxovod-gibkii-aliuminievyi-o100-mm-3-m', $aluminium->fresh()->slug);
        $this->assertSame($productCount, Product::query()->count());

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dt',
            '--apply' => true,
        ])->assertSuccessful();

        $aluminium->refresh();
        $threeMetreAluminium = $items['УТ-00000090']->fresh()->product()->sole();
        $threeMetreStainless = $items['УТ-00000089']->fresh()->product()->sole();
        $this->assertSame($productCount + 2, Product::query()->count());
        $this->assertSame('vozduxovod-gibkii-aliuminievyi-o100-mm-2-m', $aluminium->slug);
        $this->assertSame('Воздуховод гибкий алюминиевый Ø100 мм, 2 м', $aluminium->name);
        $this->assertSame('2 м', collect($aluminium->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame(['5.28', '16.80'], [$threeMetreAluminium->price, $threeMetreStainless->price]);
        $this->assertSame(['2', '3'], [(string) $threeMetreAluminium->stock_qty, (string) $threeMetreStainless->stock_qty]);
        $this->assertSame(['3 м', '3 м'], [
            collect($threeMetreAluminium->specs)->firstWhere('key', 'Длина')['value'],
            collect($threeMetreStainless->specs)->firstWhere('key', 'Длина')['value'],
        ]);
        $this->assertSame(['matched', 'matched'], [
            $items['УТ-00000090']->fresh()->match_status,
            $items['УТ-00000089']->fresh()->match_status,
        ]);
    }

    public function test_it_links_three_old_burner_rows_to_existing_sale_cards(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-old-burners',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Пеллетные горелки',
            'slug' => 'pelletnye-gorelki',
            'parent_id' => 0,
        ]);
        $definitions = [
            'УТ-00001956' => ['d03c5781-88f4-11e9-972a-1c1b0d1de7c2', 'Горелка пеллетная HOTTA 20', 'Пеллетная горелка HOTTA Ceramik 20 кВт с Wi‑Fi', 'pelletnaya-gorelka-hotta-ceramik-20-kvt-komplekt-1', 4300],
            'УТ-00001959' => ['d03c5784-88f4-11e9-972a-1c1b0d1de7c2', 'Горелка пеллетная HOTTA 30', 'Пеллетная горелка HOTTA Ceramik 30 кВт с Wi‑Fi', 'pelletnaya-gorelka-hotta-ceramik-30-kvt-komplekt-3', 4600],
            'УТ-00009442' => ['84c4e9c0-f533-11f0-8ad0-1c1b0d1de7c2', 'Горелка пеллетная Потапыч', 'Пеллетная горелка для печи Потапыч', 'pelletnaya-gorelka-dlya-pechi-potapyich', 165],
        ];
        $products = collect($definitions)->mapWithKeys(function (array $row, string $code) use ($category): array {
            return [$code => Product::query()->create([
                'category_id' => $category->id,
                'sku' => 'TEST-'.$code,
                'name' => $row[2],
                'slug' => $row[3],
                'price' => $row[4],
                'stock_qty' => 0,
                'images' => ['img/products/burner.jpg'],
                'is_active' => true,
                'is_archived' => false,
            ])];
        });
        $items = collect($definitions)->mapWithKeys(function (array $row, string $code) use ($source): array {
            return [$code => IntegrationProduct::query()->create([
                'integration_source_id' => $source->id,
                'external_id' => $row[0],
                'external_code' => $code,
                'name' => $row[1],
                'price_byn' => null,
                'stock_quantity' => 1,
                'stock_confirmed_at' => now(),
                'match_status' => 'unmatched',
            ])];
        });

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-du',
        ])->assertSuccessful();
        $this->assertTrue($items->every(fn (IntegrationProduct $item): bool => $item->fresh()->product_id === null));

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-du',
            '--apply' => true,
        ])->assertSuccessful();

        foreach ($items as $code => $item) {
            $this->assertSame($products[$code]->id, $item->fresh()->product_id);
            $this->assertSame('matched', $item->fresh()->match_status);
            $this->assertSame('manual_search', $item->fresh()->match_method);
            $this->assertSame('1', (string) $products[$code]->fresh()->stock_qty);
        }
        $this->assertSame(['4300.00', '4600.00', '165.00'], $products->map(fn (Product $product): string => $product->fresh()->price)->values()->all());
    }

    public function test_it_creates_the_exact_d80_mono_adapter_with_a_clean_local_image(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-d80',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'krepleniya-dymohod'],
            ['name' => 'Крепления дымохода', 'parent_id' => 0],
        );
        $template = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-007944',
            'name' => 'Теплов и Сухов Адаптер Моно М-М 430-0.8 D110 (У)',
            'slug' => 'teplov-i-suhov-adapter-mono-mm-d110-test',
            'price' => 12,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'af1f3537-1f62-11e9-bad6-1c1b0d1de7c2',
            'external_code' => 'УТ-00000043',
            'external_sku' => 'TS.FRT.AKL.0080.62699',
            'barcode' => '4680205249327',
            'name' => 'Адаптер Моно М-М 430-0.5 D80 (У)',
            'price_byn' => 6.67,
            'stock_quantity' => 20,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dv',
        ])->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dv',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertNotSame($template->id, $product->id);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('9.05', $product->price);
        $this->assertSame('20', (string) $product->stock_qty);
        $this->assertSame(['img/products/generated/teplov-suhov-adapter-mono-mm-430-05-d80.png'], $product->images);
        $this->assertSame('80 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('0,5 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame('TS.FRT.AKL.0080.62699', collect($product->specs)->firstWhere('key', 'Артикул производителя')['value']);
    }

    public function test_it_documents_the_unpriced_gefest_pb03_stone_basket_as_a_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-gefest-pb03-basket',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '506c87af-4bb1-11e9-b781-1c1b0d1de7c2',
            'external_code' => 'УТ-00001533',
            'name' => 'Сетка на печь Ураган ПБ-03 П-3К',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-dw',
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertNull($item->product_id);
        $this->assertSame('research_dispute', $item->candidates[0]['type']);
        $this->assertStringContainsString('кованая сетка-каменка', $item->candidates[0]['reason']);
        $this->assertContains('актуальная закупочная или розничная цена', $item->candidates[0]['required_data']);
    }

    public function test_it_creates_the_priced_hotta_aet24_and_disputes_the_unpriced_aet_s12(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-hotta-electric-storage-heaters',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Конвекторы',
            'slug' => 'elektricheskie-konvektoryi',
            'parent_id' => 0,
        ]);
        $brand = Brand::query()->firstOrCreate(
            ['slug' => 'hotta'],
            ['name' => 'Hotta', 'is_active' => true],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-000.18755',
            'name' => 'Электроконвектор тестовый',
            'slug' => 'electric-convector-test-template',
            'price' => 477,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $priced = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '506c878f-4bb1-11e9-b781-1c1b0d1de7c2',
            'external_code' => 'УТ-00001528',
            'name' => 'Аппарат электрический теплоаккумулирующий "HOTTA" АЭТ-2.4(220)',
            'price_byn' => 768.44,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $unpriced = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '506c878e-4bb1-11e9-b781-1c1b0d1de7c2',
            'external_code' => 'УТ-00001527',
            'name' => 'Аппарат электрический теплоаккумулирующий "HOTTA" АЭТ-С-1.2(220)',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dx',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $priced->fresh()->product()->sole();
        $this->assertSame('768.44', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertSame(['img/products/generated/hotta-aet-24-electric-storage-heater.png'], $product->images);
        $this->assertSame('2,4 кВт', collect($product->specs)->firstWhere('key', 'Номинальная мощность')['value']);
        $this->assertSame('matched', $priced->fresh()->match_status);
        $this->assertSame('ambiguous', $unpriced->fresh()->match_status);
        $this->assertSame('manual_research_unresolved', $unpriced->fresh()->match_method);
        $this->assertSame('research_dispute', $unpriced->fresh()->candidates[0]['type']);
    }

    public function test_it_creates_the_exact_chistogrill_700ml_cleaner_with_a_clean_image(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-chistogrill-700',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Аксессуары для каминов',
            'slug' => 'aksessuary-kaminy',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000910',
            'name' => 'Nordflam Решетка каминная AERO 90×800 белая',
            'slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia',
            'price' => 104,
            'images' => ['img/products/nordflam/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '7bdf3dbf-9254-11e9-b722-1c1b0d1de7c2',
            'external_code' => 'УТ-00002007',
            'name' => 'Очиститель для гриля "ЧистоGRILL" 700мл',
            'price_byn' => 12.67,
            'stock_quantity' => 8,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dy',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('ЧистоGRILL Спрей-очиститель для гриля, 700 мл', $product->name);
        $this->assertSame('12.67', $product->price);
        $this->assertSame('8', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('manual_created', $item->fresh()->match_method);
        $this->assertSame('700 мл', collect($product->specs)->firstWhere('key', 'Объём')['value']);
        $this->assertSame('ООО «Раута-Лес»', collect($product->specs)->firstWhere('key', 'Изготовитель')['value']);
        $this->assertSame(['img/products/chistogrill/chistogrill-grill-cleaner-700ml.png'], $product->images);
        $this->assertFileExists(public_path('img/products/chistogrill/chistogrill-grill-cleaner-700ml.png'));
        $this->assertStringContainsString('siteapi.org', $product->content_source_url);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_500x500_protective_screen(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-protective-screen-500',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Аксессуары для каминов',
            'slug' => 'aksessuary-kaminy',
            'parent_id' => 0,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-000910',
            'name' => 'Nordflam Решетка каминная AERO 90×800 белая',
            'slug' => 'nordflam-resetka-kaminnaia-aero-90800-belaia',
            'price' => 104,
            'images' => ['img/products/nordflam/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'f37d7c0d-9186-11e9-b722-1c1b0d1de7c2',
            'external_code' => 'УТ-00001999',
            'external_sku' => 'TS.KMP.LST.0000.75657',
            'name' => 'Экран 480х480 М',
            'price_byn' => 39.06,
            'stock_quantity' => 9,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-dz',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('Теплов и Сухов Экран защитный 500 × 500 / 480 × 480 мм', $product->name);
        $this->assertSame('TS.KMP.LST.0000.75657', $item->fresh()->external_sku);
        $this->assertSame('39.06', $product->price);
        $this->assertSame('9', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('480 × 480 мм', collect($product->specs)->firstWhere('key', 'Фактический размер')['value']);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/protective-screen-500x500-480x480.jpeg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/protective-screen-500x500-480x480.jpeg'));
        $this->assertStringContainsString('radiat.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_d80_110_adapter(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-d80-110',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'perehody-adaptery-dymohod'],
            ['name' => 'Переходы и адаптеры дымохода', 'parent_id' => 0],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.995',
            'name' => 'Теплов и Сухов Адаптер-переход Моно М-М 430-0.8 D150/200',
            'slug' => 'teplov-i-suhov-adapter-perehod-mono-mm-150-200-test',
            'price' => 41,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b55770bf-7c3a-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009600',
            'external_sku' => 'TS.FRT.AKP.0080.82687',
            'name' => 'Адаптер переход Моно М-М (С) 430-0.5 D80/110 (У)',
            'price_byn' => 17.40,
            'stock_quantity' => 4,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ea',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('17.40', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.FRT.AKP.0080.82687', $item->fresh()->external_sku);
        $this->assertSame('80 мм', collect($product->specs)->firstWhere('key', 'Входной диаметр')['value']);
        $this->assertSame('110 мм', collect($product->specs)->firstWhere('key', 'Выходной диаметр')['value']);
        $this->assertSame('230 мм', collect($product->specs)->firstWhere('key', 'Высота')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/adapter-mono-mm-430-05-d80-110.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/adapter-mono-mm-430-05-d80-110.jpg'));
        $this->assertStringContainsString('pdmakarov.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_d80_120_adapter(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-d80-120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'perehody-adaptery-dymohod'],
            ['name' => 'Переходы и адаптеры дымохода', 'parent_id' => 0],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.995',
            'name' => 'Теплов и Сухов Адаптер-переход Моно М-М 430-0.8 D150/200',
            'slug' => 'teplov-i-suhov-adapter-perehod-mono-mm-150-200-eb-test',
            'price' => 41,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'd3e53f30-6ef7-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009584',
            'external_sku' => 'TS.FRT.AKP.0080.82689',
            'name' => 'Адаптер переход Моно М-М (С) 430-0.5 D80/120 (У)',
            'price_byn' => 17.40,
            'stock_quantity' => 10,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-eb',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('17.40', $product->price);
        $this->assertSame('10', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.FRT.AKP.0080.82689', $item->fresh()->external_sku);
        $this->assertSame('80 мм', collect($product->specs)->firstWhere('key', 'Входной диаметр')['value']);
        $this->assertSame('120 мм', collect($product->specs)->firstWhere('key', 'Выходной диаметр')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/adapter-mono-mm-430-05-d80-120.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/adapter-mono-mm-430-05-d80-120.jpg'));
        $this->assertStringContainsString('virage24.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_d140_150_adapter(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-adapter-d140-150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'perehody-adaptery-dymohod'],
            ['name' => 'Переходы и адаптеры дымохода', 'parent_id' => 0],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-008.995',
            'name' => 'Теплов и Сухов Адаптер-переход Моно М-М 430-0.8 D150/200',
            'slug' => 'teplov-i-suhov-adapter-perehod-mono-mm-150-200-ec-test',
            'price' => 41,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '2b957294-2fcc-11ef-b2d3-1c1b0d1de7c2',
            'external_code' => 'УТ-00008607',
            'external_sku' => 'TS.FRT.AKP.0140.70054',
            'name' => 'Адаптер переход Моно М-М 430-0.8 D140/150',
            'price_byn' => 24.36,
            'stock_quantity' => 16,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ec',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('24.36', $product->price);
        $this->assertSame('16', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.FRT.AKP.0140.70054', $item->fresh()->external_sku);
        $this->assertSame('140 мм', collect($product->specs)->firstWhere('key', 'Входной диаметр')['value']);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Выходной диаметр')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/adapter-mono-mm-430-08-d140-150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/adapter-mono-mm-430-08-d140-150.jpg'));
        $this->assertStringContainsString('virage24.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_teplov_i_suhov_l1000_tubular_convector(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-convector-l1000-d120',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(
            ['slug' => 'teplosyomniki'],
            ['name' => 'Теплосъёмники', 'parent_id' => 0],
        );
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-011.910',
            'name' => 'Конвектор трубный L1000 D150/150 430',
            'slug' => 'konvektor-trubnyy-l1000-d150-150-ed-test',
            'price' => 265.53,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '8f0a589f-29e9-11e9-b256-1c1b0d1de7c2',
            'external_code' => 'УТ-00001311',
            'external_sku' => 'TS.KMP.KNV.0120.72311',
            'name' => 'Конвектор трубный L1000 КТр-Р 430-0,8, D120/115(120)',
            'price_byn' => 180.79,
            'stock_quantity' => 2,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ed',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('180.79', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.KMP.KNV.0120.72311', $item->fresh()->external_sku);
        $this->assertSame('115–120 мм', collect($product->specs)->firstWhere('key', 'Диаметр')['value']);
        $this->assertSame('1000 мм', collect($product->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/convector-ktr-r-l1000-430-08-d120-115-120.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/convector-ktr-r-l1000-430-08-d120-115-120.jpg'));
        $this->assertStringContainsString('vseinstrumenti.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_tsvetlit_dn15_female_to_male_ball_valve(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-tsvetlit-ball-valve',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->create([
            'name' => 'Трубы и фитинги',
            'slug' => 'truby-i-fitingi',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Шаровые краны',
            'slug' => 'sharovye-krany',
            'parent_id' => $parent->id,
            'is_active' => false,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-011.910',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'tsvetlit-ball-valve-ee-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '62cbabbe-2853-11e9-b256-1c1b0d1de7c2',
            'external_code' => 'УТ-00000399',
            'name' => 'Кран шар DN 15; PN1,6 МПа; 11Б27n5, м-ц (вода)',
            'price_byn' => 6.16,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ee',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('6.16', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('DN15', collect($product->specs)->firstWhere('key', 'Условный диаметр')['value']);
        $this->assertSame('Муфта-цапка', collect($product->specs)->firstWhere('key', 'Тип присоединения')['value']);
        $this->assertSame(['img/products/tsvetlit/ball-valve-11b27p5-dn15-mufta-capka.png'], $product->images);
        $this->assertFileExists(public_path('img/products/tsvetlit/ball-valve-11b27p5-dn15-mufta-capka.png'));
        $this->assertTrue((bool) $category->fresh()->is_active);
        $this->assertStringContainsString('ecs-sko.ru', $product->content_source_url);
    }

    public function test_it_links_and_refreshes_the_existing_skamoenclosure_board_without_a_duplicate(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-skamoenclosure-board',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->firstOrCreate(['slug' => 'aksessuary-dlya-bani'], [
            'name' => 'Аксессуары для бани',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'ognezashchitnye-plity-i-listy'], [
            'name' => 'Огнезащитные плиты и листы',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);
        $legacyCategory = Category::query()->firstOrCreate(['slug' => 'oblicovki'], [
            'name' => 'Порталы для электрокамина',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $legacyBrand = Brand::query()->firstOrCreate(['slug' => 'scamol'], [
            'name' => 'Scamol',
            'h1' => 'Scamol',
            'is_active' => true,
        ]);
        $existing = Product::query()->create([
            'category_id' => $legacyCategory->id,
            'brand_id' => $legacyBrand->id,
            'sku' => 'PS-009.751',
            'name' => 'Плита изоляционная каминная SkamoEnclosure, 1220/1000/30',
            'slug' => 'plita-izolyatsionnaya-kaminnaya-skamoenclosure-1220-1000-30',
            'price' => 207,
            'images' => [
                'product/009/009751/5e885e19da27f.png',
                'product/009/009751/skamoenclosure.jpg',
            ],
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '62fffaa2-5064-11e9-b1db-1c1b0d1de7c2',
            'external_code' => 'УТ-00001545',
            'name' => 'Плита каминная кремниево-кальциевая SkamoEnclosure, 1220/1000/30',
            'price_byn' => 184,
            'stock_quantity' => 20,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ef',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertTrue($existing->is($product));
        $this->assertSame(1, Product::query()->where('slug', $existing->slug)->count());
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('manual_search', $item->fresh()->match_method);
        $this->assertSame('184.00', $product->price);
        $this->assertSame('20', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertFalse((bool) $product->requires_price_confirmation);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame('Skamol', $product->brand?->name);
        $this->assertSame('Skamol Плита каминная SkamoEnclosure Board 1220×1000×30 мм', $product->name);
        $this->assertSame('1000 °C', collect($product->specs)->firstWhere('key', 'Максимальная рабочая температура')['value']);
        $this->assertSame('225 кг/м³', collect($product->specs)->firstWhere('key', 'Плотность')['value']);
        $this->assertSame([
            'product/009/009751/5e885e19da27f.png',
            'product/009/009751/skamoenclosure.jpg',
        ], $product->images);
        $this->assertStringContainsString('skamoenclosure.com', $product->content_source_url);
    }

    public function test_it_creates_the_exact_304_mono_to_thermo_transition_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-pmt-304-d150-210',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.149',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'pmt-eg-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'd86fa570-aaa4-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009652',
            'external_sku' => 'TS.ST3.PRT.0150.78420',
            'name' => 'Переход Моно/Термо ПМТ-Р 304-0.5/304 D150/210 М',
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-eg',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('70.50', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.ST3.PRT.0150.78420', $item->fresh()->external_sku);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('210 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/perehod-pmt-r-304-05-304-d150-210-m.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/perehod-pmt-r-304-05-304-d150-210-m.jpg'));
        $this->assertStringContainsString('kaminchi.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_304_thermo_cone_with_clamp_and_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-konus-304-d150-210',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.149',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'konus-eh-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '9d70cd63-aaa4-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009651',
            'external_sku' => 'TS.ST3.KNS.0150.78405',
            'name' => 'Конус Термо КТ-Р 304-0.5/304-0.5 D150/210 М с хомутом',
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-eh',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('80.10', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.ST3.KNS.0150.78405', $item->fresh()->external_sku);
        $this->assertSame('150 мм', collect($product->specs)->firstWhere('key', 'Внутренний диаметр')['value']);
        $this->assertSame('210 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('Конус, соединительный хомут', collect($product->specs)->firstWhere('key', 'Комплектация')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/konus-kt-r-304-05-304-05-d150-210-m-homut.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/konus-kt-r-304-05-304-05-d150-210-m-homut.jpg'));
        $this->assertStringContainsString('kaminchi.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_aisi_430_d80_rotary_shiber_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-shiber-430-d80',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'shibery-dymohod'], [
            'name' => 'Шиберы и задвижки',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.741',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'shiber-ei-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'a12d9480-708b-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009586',
            'external_sku' => 'TS.FRT.SHR.0080.83395',
            'name' => 'Шибер ШМ-Р(М2) 430-0.5 D80',
            'stock_quantity' => 4,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ei',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('30.10', $product->price);
        $this->assertSame('4', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.FRT.SHR.0080.83395', $item->fresh()->external_sku);
        $this->assertSame('80 мм', collect($product->specs)->firstWhere('key', 'Диаметр дымохода')['value']);
        $this->assertSame('AISI 430', collect($product->specs)->firstWhere('key', 'Марка стали')['value']);
        $this->assertSame('0,5 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/shiber-shm-r-m2-430-05-d80.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/shiber-shm-r-m2-430-05-d80.jpg'));
        $this->assertStringContainsString('revitech.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_aisi_430_d150_male_male_transition_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-transition-430-d150',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'perehody-adaptery-dymohod'], [
            'name' => 'Переходы и адаптеры дымохода',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-009.149',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'transition-ej-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '37dd7be0-6333-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009544',
            'external_sku' => 'TS.FRT.PRM.0150.74712',
            'name' => 'Переход Моно П-П 430-0.8 D150/150',
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ej',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('15.20', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('TS.FRT.PRM.0150.74712', $item->fresh()->external_sku);
        $this->assertSame('150/150 мм', collect($product->specs)->firstWhere('key', 'Диаметры')['value']);
        $this->assertSame('Папа-папа', collect($product->specs)->firstWhere('key', 'Соединение')['value']);
        $this->assertSame('0,8 мм', collect($product->specs)->firstWhere('key', 'Толщина стали')['value']);
        $this->assertSame(['img/products/teplov-i-suhov/perehod-mono-pp-430-08-d150-150.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/teplov-i-suhov/perehod-mono-pp-430-08-d150-150.jpg'));
        $this->assertStringContainsString('kaminchi.ru', $product->content_source_url);
    }

    public function test_it_creates_the_exact_nulite_pool_heat_pump_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-nulite-pool-heat-pump',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'teplovyie-nasosyi'], [
            'name' => 'Тепловые насосы',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-6570',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'nulite-ek-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '79f82f90-a6ac-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009646',
            'external_sku' => null,
            'name' => 'Тепловой насос NL-FLM25Y/32, (230V-50hz-1 phase, R32), Nulite',
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ek',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('4630.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('NL-FLM25Y/32', $item->fresh()->external_sku);
        $this->assertSame('15–40 м³', collect($product->specs)->firstWhere('key', 'Рекомендуемый объём бассейна')['value']);
        $this->assertSame('7,7 кВт', collect($product->specs)->firstWhere('key', 'Теплопроизводительность (воздух 27 °C)')['value']);
        $this->assertSame('R32', collect($product->specs)->firstWhere('key', 'Хладагент')['value']);
        $this->assertSame(['img/products/nulite/nl-flm25y-32.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/nulite/nl-flm25y-32.jpg'));
        $this->assertStringContainsString('sunelements.hu', $product->content_source_url);
        $this->assertStringContainsString('Pool-Heat-Pump-Catalog.pdf', $product->content);
    }

    public function test_it_documents_the_unidentified_pp_00_00_adapter_as_a_research_dispute(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unidentified-pp-adapter',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '8bf68630-96f1-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009627',
            'external_sku' => null,
            'barcode' => null,
            'name' => 'Переходник ПП 00.00',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
            'candidates' => null,
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-disputed-20261014-el',
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $dispute = $item->candidates[0];
        $this->assertSame($productCount, Product::query()->count());
        $this->assertNull($item->product_id);
        $this->assertSame('ambiguous', $item->match_status);
        $this->assertSame('manual_research_unresolved', $item->match_method);
        $this->assertSame('research_dispute', $dispute['type']);
        $this->assertStringContainsString('Переходник ПП 00.00', $dispute['reason']);
        $this->assertContains('присоединительные размеры с обеих сторон', $dispute['required_data']);
        $this->assertContains('закупочная или розничная цена', $dispute['required_data']);
        $this->assertCount(2, $dispute['evidence']);
        $this->assertSame('УТ-00009627', $dispute['source_identity']['external_code']);
    }

    public function test_it_creates_the_exact_stahlmann_f_25_by_1_easyfix_coupling_with_researched_price(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-stahlmann-f-25x1-ef',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->firstOrCreate(['slug' => 'komplektuyushhie-dlya-otopleniya'], [
            'name' => 'Комплектующие для отопления',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-007773',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'stahlmann-em-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'd84c5b80-6583-11f1-b4b9-1c1b0d1de7c2',
            'external_code' => 'УТ-00009551',
            'external_sku' => null,
            'name' => 'Муфта Stahlmann (F) 25х1 EF',
            'price_byn' => null,
            'stock_quantity' => 2,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-em',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('34.00', $product->price);
        $this->assertSame('2', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('2217160', $item->fresh()->external_sku);
        $this->assertSame('25 мм', collect($product->specs)->firstWhere('key', 'Диаметр трубы')['value']);
        $this->assertSame('1″', collect($product->specs)->firstWhere('key', 'Резьба')['value']);
        $this->assertSame('Внутренняя (F)', collect($product->specs)->firstWhere('key', 'Тип резьбы')['value']);
        $this->assertSame(['img/products/stahlmann/stahlmann-coupling-f-25-1-ef-2217160.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/stahlmann/stahlmann-coupling-f-25-1-ef-2217160.jpg'));
        $this->assertStringContainsString('chipdip.by', $product->content_source_url);
    }

    public function test_it_creates_the_exact_technoLit_polotsk_flat_cast_iron_lid_for_a_four_litre_cauldron(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-cast-iron-cauldron-lid-4l',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        Category::query()->firstOrCreate(['slug' => 'mangalyi'], [
            'name' => 'Мангалы',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => Category::query()->where('slug', 'mangalyi')->value('id'),
            'sku' => 'KOTLOV-007773',
            'name' => 'Тестовый товар-шаблон',
            'slug' => 'cauldron-lid-en-test-template',
            'price' => 10,
            'images' => ['img/products/template.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '4f58bf99-2605-11e9-b256-1c1b0d1de7c2',
            'external_code' => 'УТ-00001222',
            'external_sku' => null,
            'name' => 'Крышка плоская на казан 4л',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-en',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('42.00', $product->price);
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('707', $item->fresh()->external_sku);
        $this->assertSame('kazany', $product->category->slug);
        $this->assertSame('mangalyi', $product->category->parent->slug);
        $this->assertSame('308 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('280 мм', collect($product->specs)->firstWhere('key', 'Диаметр внутреннего паза')['value']);
        $this->assertSame(['img/products/tekhnolit-polotsk/cast-iron-flat-cauldron-lid-4l-707.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/tekhnolit-polotsk/cast-iron-flat-cauldron-lid-4l-707.jpg'));
        $this->assertStringContainsString('pcentr.by', $product->content_source_url);
    }

    public function test_it_creates_the_unmatched_brand_neutral_sauna_window_500x500_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-unmatched-sauna-window-500',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->create([
            'name' => 'Бани и сауны',
            'slug' => 'bani-i-sauny',
            'parent_id' => 0,
        ]);
        $category = Category::query()->create([
            'name' => 'Окна для бани и сауны',
            'slug' => 'okna-dlya-bani-i-sauny',
            'parent_id' => $parent->id,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Шаблонный бренд',
            'slug' => 'sauna-window-template-brand',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-SAUNA-WINDOW-400-TEMPLATE',
            'name' => 'Форточка для бани 400×400 мм',
            'slug' => 'fortocka-dlia-bani-400400-mm',
            'price' => 82,
            'images' => ['img/products/sauna/fortochka-400x400-clean.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '949b0045-255e-11e9-b256-1c1b0d1de7c2',
            'external_code' => 'УТ-00001213',
            'external_sku' => null,
            'name' => 'Форточка 500*500',
            'price_byn' => null,
            'stock_quantity' => 3,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-eo',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('Форточка для бани 500×500 мм', $product->name);
        $this->assertSame('82.00', $product->price);
        $this->assertSame('3', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertNull($product->brand_id);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('manual_created', $item->fresh()->match_method);
        $this->assertSame('okna-dlya-bani-i-sauny', $product->category->slug);
        $this->assertSame('500×500 мм', collect($product->specs)->firstWhere('key', 'Размер')['value']);
        $this->assertSame('Дерево', collect($product->specs)->firstWhere('key', 'Материал рамы')['value']);
        $this->assertSame(['img/products/sauna/fortochka-500x500-clean.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/sauna/fortochka-500x500-clean.jpg'));
        $this->assertStringContainsString('pechnoi.by', $product->content_source_url);
        $this->assertStringContainsString('фактическую комплектацию', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001213',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_creates_the_exact_stahlmann_neptun_iws_20a_annealed_pipe_per_metre(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-neptun-iws-20a',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $parent = Category::query()->create([
            'name' => 'Трубы и фитинги',
            'slug' => 'truby-i-fitingi',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Гофрированные трубы',
            'slug' => 'gofrirovanye-truby',
            'parent_id' => $parent->id,
            'is_active' => true,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-EUROTIS-PIPE-TEMPLATE',
            'name' => 'Труба EUROTIS из нержавеющей стали 3/4',
            'slug' => 'truba-eurotis-italiya-iz-nerzhaveyushhej-stali-34',
            'price' => 49,
            'images' => ['product/005/005763/765-truba-gofra.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'b322095c-9bb7-11e9-b0c6-1c1b0d1de7c2',
            'external_code' => 'УТ-00002051',
            'external_sku' => null,
            'name' => 'Труба гофр.из нерж.стали SS304 Neptun IWS 20A отожженная',
            'price_byn' => null,
            'stock_quantity' => 0.45,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-ep',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('18.53', $product->price);
        // Product stock is the integer storefront summary; the exact fractional
        // metre remains on the 1C source offer and drives positive availability.
        $this->assertSame('1', (string) $product->stock_qty);
        $this->assertSame('0.450', (string) $item->fresh()->stock_quantity);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('manual_created', $item->fresh()->match_method);
        $this->assertSame('IWS 20A', $item->fresh()->external_sku);
        $this->assertSame('stahlmann', $product->brand->slug);
        $this->assertSame('gofrirovanye-truby', $product->category->slug);
        $this->assertSame('DN20', collect($product->specs)->firstWhere('key', 'Номинальный диаметр')['value']);
        $this->assertSame('25,6 мм', collect($product->specs)->firstWhere('key', 'Наружный диаметр')['value']);
        $this->assertSame('1 погонный метр', collect($product->specs)->firstWhere('key', 'Единица продажи')['value']);
        $this->assertSame(['img/products/stahlmann/stahlmann-neptun-iws-20a-annealed.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/stahlmann/stahlmann-neptun-iws-20a-annealed.jpg'));
        $this->assertStringContainsString('santeh24.by', $product->content_source_url);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'IWS 20A',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_creates_the_exact_brand_neutral_50_micron_24_square_metre_sauna_foil(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-sauna-foil-50-24',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Отделка для парной',
            'slug' => 'otdelka-dlya-parnoj',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $templateBrand = Brand::query()->create([
            'name' => 'Бренд шаблона',
            'slug' => 'foil-template-brand',
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $templateBrand->id,
            'sku' => 'PS-012.090',
            'name' => 'Фольга для бани Алукрафт на бумажной основе 30 м²',
            'slug' => 'foil-template-24m2-test',
            'price' => 68,
            'images' => ['product/alukraft-30.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '7c6d9745-996d-11e9-b0c6-1c1b0d1de7c2',
            'external_code' => 'УТ-00002044',
            'external_sku' => null,
            'name' => 'Фольга 50 мкм (24м2)',
            'price' => 78.81,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 94.57,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-eq',
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product()->sole();
        $this->assertSame('140.00', $product->price);
        $this->assertSame('5', (string) $product->stock_qty);
        $this->assertTrue((bool) $product->in_stock);
        $this->assertNull($product->brand_id);
        $this->assertSame('matched', $item->fresh()->match_status);
        $this->assertSame('manual_created', $item->fresh()->match_method);
        $this->assertSame('otdelka-dlya-parnoj', $product->category->slug);
        $this->assertSame('50 мкм', collect($product->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertSame('24 м²', collect($product->specs)->firstWhere('key', 'Площадь рулона')['value']);
        $this->assertSame(['img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'], $product->images);
        $this->assertFileExists(public_path('img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'));
        $this->assertStringContainsString('7771.by', $product->content_source_url);
        $this->assertStringContainsString('алюминиевой лентой', $product->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00002044',
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_merges_the_generated_24_square_metre_foil_duplicate_into_the_existing_exact_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-sauna-foil-50-24-correction',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Отделка для парной',
            'slug' => 'otdelka-dlya-parnoj',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $existing = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-012.086',
            'name' => 'Фольга для бани алюминиевая 50мкм, намотка 24м2',
            'slug' => 'folga-dlya-bani-alyuminievaya-50mkm-namotka-24m2',
            'price' => 111,
            'stock_qty' => 0,
            'in_stock' => false,
            'images' => ['product/old-foil.png'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $duplicate = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'KOTLOV-022770',
            'name' => 'Фольга алюминиевая для бани 50 мкм, 1,2×20 м (24 м²)',
            'slug' => 'folga-aliuminievaia-dlia-bani-50-mkm-1220-m-24-m2',
            'price' => 140,
            'stock_qty' => 5,
            'in_stock' => true,
            'images' => ['img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'],
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '7c6d9745-996d-11e9-b0c6-1c1b0d1de7c2',
            'external_code' => 'УТ-00002044',
            'name' => 'Фольга 50 мкм (24м2)',
            'price_byn' => 94.57,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'product_id' => $duplicate->id,
            'match_status' => 'matched',
            'match_method' => 'manual_created',
            'match_confidence' => 1,
            'matched_at' => now(),
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-corrected-20261014-er',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($existing->id, $item->fresh()->product_id);
        $this->assertSame('manual_search', $item->fresh()->match_method);
        $this->assertSame('140.00', $existing->fresh()->price);
        $this->assertSame('5', (string) $existing->fresh()->stock_qty);
        $this->assertTrue((bool) $existing->fresh()->in_stock);
        $this->assertSame(['img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'], $existing->fresh()->images);
        $this->assertSame('50 мкм', collect($existing->fresh()->specs)->firstWhere('key', 'Толщина')['value']);
        $this->assertFalse((bool) $duplicate->fresh()->is_active);
        $this->assertTrue((bool) $duplicate->fresh()->is_archived);
        $this->assertSame('archived-duplicate-kotlov-022770-foil-24m2', $duplicate->fresh()->slug);
        $this->assertSame('0', (string) $duplicate->fresh()->stock_qty);
        $this->assertDatabaseHas('redirects', [
            'from_url' => '/otdelka-dlya-parnoj/folga-aliuminievaia-dlia-bani-50-mkm-1220-m-24-m2',
            'to_url' => '/otdelka-dlya-parnoj/folga-dlya-bani-alyuminievaya-50mkm-namotka-24m2',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00002044',
            'product_id' => $existing->id,
            'product_sku' => 'PS-012.086',
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_links_and_refreshes_the_existing_exact_18_square_metre_sauna_foil(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-sauna-foil-50-18',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Отделка для парной',
            'slug' => 'otdelka-dlya-parnoj',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $existing = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-005.087',
            'name' => 'Фольга для бани алюминиевая 50мкм, намотка 18м2',
            'slug' => 'ugolok-lipa',
            'price' => 62,
            'stock_qty' => 1,
            'in_stock' => true,
            'images' => ['product/old-foil-18.png'],
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '9ce95b88-5520-11e9-b09a-1c1b0d1de7c2',
            'external_code' => 'УТ-00001573',
            'name' => 'Фольга 50 мкм (18м2)',
            'price' => 55.33,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 66.40,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-es',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($existing->id, $item->fresh()->product_id);
        $this->assertSame('manual_search', $item->fresh()->match_method);
        $this->assertSame('100.00', $existing->fresh()->price);
        $this->assertSame('5', (string) $existing->fresh()->stock_qty);
        $this->assertTrue((bool) $existing->fresh()->in_stock);
        $this->assertFalse((bool) $existing->fresh()->requires_price_confirmation);
        $this->assertSame('Фольга алюминиевая для бани 50 мкм, 1,2×15 м (18 м²)', $existing->fresh()->name);
        $this->assertSame('15 м', collect($existing->fresh()->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('18 м²', collect($existing->fresh()->specs)->firstWhere('key', 'Площадь рулона')['value']);
        $this->assertSame(['img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'], $existing->fresh()->images);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001573',
            'product_id' => $existing->id,
            'product_sku' => 'PS-005.087',
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_links_and_refreshes_the_existing_exact_12_square_metre_sauna_foil(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-sauna-foil-50-12',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Отделка для парной',
            'slug' => 'otdelka-dlya-parnoj',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $existing = Product::query()->create([
            'category_id' => $category->id,
            'sku' => 'PS-012.088',
            'name' => 'Фольга для бани алюминиевая 50мкм, намотка 12м2',
            'slug' => 'folga-dlya-bani-alyuminievaya-50mkm-namotka-12m2',
            'price' => 41,
            'stock_qty' => 0,
            'in_stock' => false,
            'images' => ['product/old-foil-12.png'],
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '9ce95b87-5520-11e9-b09a-1c1b0d1de7c2',
            'external_code' => 'УТ-00001572',
            'name' => 'Фольга 50 мкм (12 м2)',
            'price' => 37.83,
            'price_currency' => 'BYN',
            'price_currency_rate' => 1,
            'price_tax_mode' => IntegrationSource::PRICE_TAX_EXCLUSIVE,
            'price_vat_rate' => 20,
            'price_byn' => 45.40,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261014-et',
            '--apply' => true,
        ])->assertSuccessful();

        $this->assertSame($existing->id, $item->fresh()->product_id);
        $this->assertSame('manual_search', $item->fresh()->match_method);
        $this->assertSame('80.00', $existing->fresh()->price);
        $this->assertSame('5', (string) $existing->fresh()->stock_qty);
        $this->assertTrue((bool) $existing->fresh()->in_stock);
        $this->assertFalse((bool) $existing->fresh()->requires_price_confirmation);
        $this->assertSame('Фольга алюминиевая для бани 50 мкм, 1,2×10 м (12 м²)', $existing->fresh()->name);
        $this->assertSame('10 м', collect($existing->fresh()->specs)->firstWhere('key', 'Длина')['value']);
        $this->assertSame('12 м²', collect($existing->fresh()->specs)->firstWhere('key', 'Площадь рулона')['value']);
        $this->assertSame(['img/products/sauna/aluminium-foil-50-micron-24m2-clean.jpg'], $existing->fresh()->images);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00001572',
            'product_id' => $existing->id,
            'product_sku' => 'PS-012.088',
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_excludes_the_misclassified_whatsminer_without_creating_a_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-misclassified-whatsminer',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '9ead3580-4751-11f0-ad62-1c1b0d1de7c2',
            'external_code' => 'УТ-00009078',
            'name' => 'М64 190Т',
            'price_byn' => null,
            'stock_quantity' => 1,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);
        $productCount = Product::query()->count();

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-unmatched-20261014-eu',
        ])->assertSuccessful();
        $this->assertSame('unmatched', $item->fresh()->match_status);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'not-for-site-unmatched-20261014-eu',
            '--apply' => true,
        ])->assertSuccessful();

        $item->refresh();
        $this->assertNull($item->product_id);
        $this->assertNull($item->target_category_id);
        $this->assertSame('ignored', $item->match_status);
        $this->assertSame('manual_ignore', $item->match_method);
        $this->assertSame($productCount, Product::query()->count());
    }

    public function test_it_links_and_refreshes_the_existing_grk_70_3_hydraulic_separator(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-grk-70-3',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $oldCategory = Category::query()->create([
            'name' => 'Группы быстрого монтажа котельных',
            'slug' => 'gruppy-bystrogo-montazha-kotelnyx',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $targetCategory = Category::query()->create([
            'name' => 'Гидравлические разделители и коллекторы',
            'slug' => 'gidravlicheskie-razdeliteli-i-kollektory',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Велес Электро',
            'slug' => 'veles-elektro',
        ]);
        $existing = Product::query()->create([
            'category_id' => $oldCategory->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.932',
            'name' => 'Гидравлический разделитель KOTLOV GE ГРК-70/3',
            'slug' => 'gidravlicheskiy-razdelitel-hotta-grk-70_3',
            'price' => 244.80,
            'stock_qty' => 0,
            'in_stock' => false,
            'images' => [
                'product/008/008932/6761781f9fb90.jpg',
                'product/008/008932/6761781f9fc2f.jpg',
            ],
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '2d42dbdd-6a78-11ea-a266-1c1b0d1de7c2',
            'external_code' => 'УТ-00003559',
            'name' => 'Гидравлический коллектор ГРК-70-3 горизонтальный',
            'price_byn' => null,
            'stock_quantity' => 5,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261015-ev',
            '--apply' => true,
        ])->assertSuccessful();

        $existing->refresh();
        $item->refresh();
        $this->assertSame($existing->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($targetCategory->id, $existing->category_id);
        $this->assertSame('244.80', $existing->price);
        $this->assertSame('5', (string) $existing->stock_qty);
        $this->assertTrue((bool) $existing->in_stock);
        $this->assertFalse((bool) $existing->requires_price_confirmation);
        $this->assertSame('Гидравлический разделитель с коллектором KOTLOV GE ГРК-70/3', $existing->name);
        $this->assertSame('На две стороны, схема 1+1+1', collect($existing->specs)->firstWhere('key', 'Расположение выводов')['value']);
        $this->assertSame('500×310×100 мм', collect($existing->specs)->firstWhere('key', 'Габариты (Ш×В×Г)')['value']);
        $this->assertSame([
            'product/008/008932/6761781f9fb90.jpg',
            'product/008/008932/6761781f9fc2f.jpg',
        ], $existing->images);
        $this->assertStringContainsString('gidropt.ru', $existing->content_source_url);
        $this->assertStringContainsString('трёх отопительных контуров', $existing->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00003559',
            'product_id' => $existing->id,
            'product_sku' => 'PS-008.932',
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }

    public function test_it_links_and_refreshes_the_existing_grk_70_3m_hydraulic_separator(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg-grk-70-3m',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $oldCategory = Category::query()->create([
            'name' => 'Группы быстрого монтажа котельных',
            'slug' => 'gruppy-bystrogo-montazha-kotelnyx',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $targetCategory = Category::query()->create([
            'name' => 'Гидравлические разделители и коллекторы',
            'slug' => 'gidravlicheskie-razdeliteli-i-kollektory',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Велес Электро',
            'slug' => 'veles-elektro',
        ]);
        $existing = Product::query()->create([
            'category_id' => $oldCategory->id,
            'brand_id' => $brand->id,
            'sku' => 'PS-008.933',
            'name' => 'Гидравлический разделитель KOTLOV GE ГРК-70/3M',
            'slug' => 'gidravlicheskiy-razdelitel-hotta-grk-70_3m',
            'price' => 244.80,
            'stock_qty' => 0,
            'in_stock' => false,
            'images' => [
                'product/008/008933/647dcebac1dd6.jpg',
                'product/008/008933/647dcebac1eaf.jpg',
            ],
            'requires_price_confirmation' => true,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => '2d42dbdc-6a78-11ea-a266-1c1b0d1de7c2',
            'external_code' => 'УТ-00003558',
            'name' => 'Гидравлический коллектор ГРК-70-3М горизонтальный',
            'price_byn' => null,
            'stock_quantity' => 7,
            'stock_confirmed_at' => now(),
            'match_status' => 'unmatched',
        ]);

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'unmatched-researched-20261015-ew',
            '--apply' => true,
        ])->assertSuccessful();

        $existing->refresh();
        $item->refresh();
        $this->assertSame($existing->id, $item->product_id);
        $this->assertSame('matched', $item->match_status);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertSame($targetCategory->id, $existing->category_id);
        $this->assertSame('244.80', $existing->price);
        $this->assertSame('7', (string) $existing->stock_qty);
        $this->assertTrue((bool) $existing->in_stock);
        $this->assertFalse((bool) $existing->requires_price_confirmation);
        $this->assertSame('Гидравлический разделитель с коллектором KOTLOV GE ГРК-70/3М', $existing->name);
        $this->assertSame('По схеме 2+1: два на одной стороне, один на противоположной', collect($existing->specs)->firstWhere('key', 'Расположение выводов')['value']);
        $this->assertSame('722×310×100 мм', collect($existing->specs)->firstWhere('key', 'Габариты (Ш×В×Г)')['value']);
        $this->assertSame([
            'product/008/008933/647dcebac1dd6.jpg',
            'product/008/008933/647dcebac1eaf.jpg',
        ], $existing->images);
        $this->assertStringContainsString('gidropt.ru', $existing->content_source_url);
        $this->assertStringContainsString('трёх отопительных контуров', $existing->content);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'supplier_code' => $source->matchingSupplierCode(),
            'supplier_article' => 'УТ-00003558',
            'product_id' => $existing->id,
            'product_sku' => 'PS-008.933',
            'confidence' => 'manual',
            'is_active' => true,
        ]);
    }
}

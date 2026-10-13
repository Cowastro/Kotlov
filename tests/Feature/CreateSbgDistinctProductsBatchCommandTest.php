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

    public function test_it_creates_the_exact_electrolux_eft_rr_chassis_instead_of_linking_efa2(): void
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

        $this->artisan('integration:create-sbg-distinct-products', [
            '--batch' => 'researched-priced-20261013-as',
            '--code' => ['УТ-00004685'],
            '--apply' => true,
        ])->assertSuccessful();

        $product = $item->fresh()->product;
        $this->assertNotNull($product);
        $this->assertNotSame($wrongSupportCandidate->id, $product->id);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($electrolux->id, $product->brand_id);
        $this->assertSame('Electrolux EFT/RR комплект шасси для конвектора Rapid Transformer', $product->name);
        $this->assertSame('34.90', $product->price);
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
        $this->assertSame('manual_created', $item->fresh()->match_method);
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
}

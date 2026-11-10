<?php

namespace Tests\Feature;

use App\Console\Commands\ChistyBeregPhotosCommand;
use App\Console\Commands\CreateSmolkomCardsCommand;
use App\Console\Commands\SmolkomPhotoCleanupCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductVariantSelector;
use App\Services\Suppliers\SmolcomSiteCatalog;
use App\Services\Suppliers\SmolkomPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncSmolkomCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_link_by_model_colour_and_article_and_new_cards_are_created(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $file = $this->priceList();
        $portals = Category::query()->create(['name' => 'Порталы', 'slug' => 'portaly-dlya-elektrokaminov', 'parent_id' => 0, 'is_active' => true]);
        $electric = Category::query()->create(['name' => 'Электрокамины', 'slug' => 'elektrokamini', 'parent_id' => 0, 'is_active' => true]);
        $smolcom = Brand::query()->create(['name' => 'Smolcom', 'slug' => 'smolcom', 'is_active' => true]);
        $royal = Brand::query()->create(['name' => 'Royal Flame', 'slug' => 'royal-flame', 'is_active' => true]);
        $card = fn (string $name, int $brand, int $category, float $price = 100) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name),
            'category_id' => $category, 'brand_id' => $brand, 'price' => $price, 'is_active' => true]);
        $govardBlack = $card('Портал Smolcom Govard V42 черный', $smolcom->id, $portals->id);
        $govardWhite = $card('Портал Smolcom GOVARD V42 бьянко белый', $smolcom->id, $portals->id);
        $lumsden = $card('Портал Smolcom Lumsden STD-ASP', $smolcom->id, $portals->id);
        $vision = $card('Электрокамин Royal Flame Vision 60 LOG LED', $royal->id, $electric->id, 2000);
        $byBarcode = $card('Электроочаг Королевское пламя Aspen', $royal->id, $electric->id);
        $other = Supplier::query()->updateOrCreate(['code' => 'ecokamin'], ['name' => 'ЭкоКамин', 'currency' => 'BYN', 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $other->id, 'product_id' => $byBarcode->id, 'supplier_article' => '2999000469475', 'supplier_name' => 'Aspen', 'price' => 500, 'currency' => 'BYN']);

        $this->artisan('supplier:sync-smolkom', ['--file' => $file, '--apply' => true])
            ->expectsOutputToContain('metric.smolkom.rows=7')
            ->expectsOutputToContain('metric.smolkom.model=3')
            ->expectsOutputToContain('metric.smolkom.article=1')
            ->expectsOutputToContain('metric.smolkom.model_base_colour=1')
            ->expectsOutputToContain('metric.smolkom.suggested=0')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'smolkom')->sole();
        $offer = fn (Product $p) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $p->id)->sole();
        $this->assertSame('Smolcom GOVARD V42, Черный', $offer($govardBlack)->supplier_name);
        $this->assertSame('a-govv4bb', $offer($govardWhite)->supplier_article);
        $this->assertSame(['in_stock', 2, 993.0], [$offer($govardWhite)->stock_status, $offer($govardWhite)->stock_quantity, (float) $offer($govardWhite)->price_byn]);
        $this->assertSame('preorder', $offer($govardBlack)->stock_status, 'All of it reserved: on order.');
        $this->assertSame(1290.0, (float) $govardWhite->fresh()->price);
        $this->assertSame(2510.0, (float) $vision->fresh()->price);
        $this->assertSame(['in_stock', 6], [$offer($vision)->stock_status, $offer($vision)->stock_quantity]);
        // Another supplier carries the Aspen card: its price stays.
        $this->assertSame(100.0, (float) $byBarcode->fresh()->price);
        // Lumsden comes in two colours; the card without a colour takes the white one.
        $this->assertSame('a-lumstbb', $offer($lumsden)->supplier_article);

        // The colourless Lumsden card got the colour of its row.
        $this->assertSame('бьянко белый', collect($lumsden->fresh()->specs)->firstWhere('key', 'Цвет')['value']);

        // The unlinked Crystal 36 becomes a hidden card in the category of the linked electric fireplaces; Lumsden in
        // oak becomes its own card, offered next to the white one as a colour.
        $this->artisan('supplier:smolkom-create', ['--apply' => true])
            ->expectsOutputToContain('metric.smolkom_create.card_exists=0')
            ->expectsOutputToContain('metric.smolkom_create.created=2')
            ->assertSuccessful();
        $oak = Product::query()->where('sku', 'SMOLKOM-A-LUMSTDA')->sole();
        $this->assertSame('Портал Smolcom Lumsden STD-ASP дуб антик', $oak->name);
        $selector = app(ProductVariantSelector::class)->forAdmin($lumsden->fresh());
        $this->assertSame('Выберите цвет:', $selector['title']);
        $this->assertSame(['Бьянко белый', 'Дуб антик'], $selector['variants']->pluck('label')->all());
        // The storefront shows only cards on sale: the oak card is hidden until it has a photo.
        $this->assertNull(app(ProductVariantSelector::class)->forProduct($lumsden->fresh()));
        $oak->forceFill(['is_active' => true])->save();
        $this->assertSame(2, app(ProductVariantSelector::class)->forProduct($lumsden->fresh())['variants']->count());
        $new = Product::query()->where('sku', 'SMOLKOM-A-64935693')->sole();
        $this->assertSame(['Электрокамин Royal Flame Crystal 36 RF', $electric->id, $royal->id, 2190.0, false],
            [$new->name, $new->category_id, $new->brand_id, (float) $new->price, (bool) $new->is_active]);
        $this->assertSame('created_from_price', SupplierProduct::query()->where('product_id', $new->id)->sole()->match_confidence);
    }

    public function test_model_key_and_titles(): void
    {
        $this->assertSame(SmolkomPriceList::modelKey('ALBION V42'), SmolkomPriceList::modelKey('Портал Smolcom Albion V42 бьянко белый'));
        $this->assertSame(SmolkomPriceList::modelKey('Vision 60 LOG LED'), SmolkomPriceList::modelKey('Электрокамин Royal Flame Vision 60 LOG LED'));
        $this->assertSame(SmolkomPriceList::modelKey('SANREMO Vis42 разборный'), SmolkomPriceList::modelKey('Портал Sanremo V42 разборный'));
        $this->assertSame(['белый', 'бьянко', 'черный'], SmolkomPriceList::colourWords('Бьянко белый/черный'));
        $this->assertSame('Портал Smolcom Albion V42 бьянко белый', CreateSmolkomCardsCommand::cardTitle(['group' => 'ПОРТАЛЫ', 'brand' => 'Smolcom', 'name' => 'ALBION V42', 'colour' => 'Бьянко белый']));
        $this->assertSame('Dimplex Cas 400 Log Led муляж дров', CreateSmolkomCardsCommand::cardTitle(['group' => 'ЭЛЕКТРОКАМИНЫ', 'brand' => 'Dimplex', 'name' => 'Cas 400 Log Led муляж дров', 'colour' => '']));
    }

    public function test_smolcom_site_photos_of_the_same_article_fill_a_hidden_colour_card(): void
    {
        $page = "<script>var ob = new JCCatalogElement({'OFFERS':[{'ID':'903','NAME':'Портал Smolcom Govard V42','TREE':{'PROP_671':'45'},"
            ."'DISPLAY_PROPERTIES':{'ARTICLE':{'NAME':'Артикул','VALUE':'GOVV4Ч','CODE':'ARTICLE'}},'SLIDER':[{'ID':'1','SRC':'/upload/iblock/a1/black.jpg','WIDTH':'1772'},"
            ."{'ID':'2','SRC':'/upload/resize_cache/iblock/a1/450_450/black.jpg'}]},"
            ."{'ID':'904','NAME':'Портал Smolcom Govard V42','DISPLAY_PROPERTIES':{'ARTICLE':{'NAME':'Артикул','VALUE':'GOVV4Бб','CODE':'ARTICLE'}},'SLIDER':[{'ID':'3','SRC':'/upload/iblock/b2/white.jpg'}]}]});</script>";
        $this->assertSame(['govv4ч' => ['https://smolcom.by/upload/iblock/a1/black.jpg'], 'govv4бб' => ['https://smolcom.by/upload/iblock/b2/white.jpg']],
            SmolcomSiteCatalog::parseOffers($page));
        // One picture for every colour of a page is not the photo of a colour.
        $shared = str_replace('/upload/iblock/b2/white.jpg', '/upload/iblock/a1/black.jpg', $page);
        $this->assertSame([], SmolcomSiteCatalog::parseOffers($shared));

        $image = imagecreatetruecolor(800, 800);
        imagefilledrectangle($image, 100, 100, 700, 700, imagecolorallocate($image, 20, 20, 20));
        imagefilledrectangle($image, 0, 0, 800, 90, imagecolorallocate($image, 200, 120, 60));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        Http::fake([
            'smolcom.by/sitemap-iblock-23.xml' => Http::response('<urlset></urlset>'),
            'smolcom.by/sitemap-iblock-26.xml' => Http::response('<urlset><url><loc>https://smolcom.by/catalog/portali/portaly-iz-dereva-mdf/796/</loc></url></urlset>'),
            'smolcom.by/catalog/*' => Http::response($page),
            'smolcom.by/upload/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            'dimplex.ru/sitemap/*' => Http::response('<urlset><url><loc>https://dimplex.ru/catalog/ochag-ignite-xlf50/</loc></url></urlset>'),
            'dimplex.ru/catalog/*' => Http::response('<h1>Очаг Ignite XLF50</h1><img src="/upload/iblock/ce0/Ignite_1500x1000.jpg"><dl class="param"><dt><span>Артикул</span></dt><dd>64920045</dd></dl>'),
            'dimplex.ru/upload/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
            'royalflame.ru/*' => Http::response('', 404),
        ]);
        $category = Category::query()->create(['name' => 'Облицовки', 'slug' => 'oblicovki', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Smolcom', 'slug' => 'smolcom', 'is_active' => true]);
        $black = Product::query()->create(['name' => 'Портал Smolcom Govard V42 черный', 'slug' => 'portal-govard-v42-black', 'sku' => 'SMOLKOM-A-GOVV4C',
            'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 1290, 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS, 'images' => []]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'smolkom'], ['name' => 'Смолком', 'currency' => 'BYN', 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $black->id, 'supplier_article' => 'a-govv4c', 'supplier_name' => 'Smolcom GOVARD V42, Черный',
            'price' => 993, 'currency' => 'BYN', 'raw' => ['article' => 'GOVV4Ч', 'colour' => 'Черный', 'name' => 'GOVARD V42', 'group' => 'ПОРТАЛЫ']]);

        $dimplex = Brand::query()->create(['name' => 'Dimplex', 'slug' => 'dimplex', 'is_active' => true]);
        $ignite = Product::query()->create(['name' => 'Электрокамин Dimplex Ignite XLF 50', 'slug' => 'ignite-xlf-50', 'sku' => 'SMOLKOM-A-64920045',
            'category_id' => $category->id, 'brand_id' => $dimplex->id, 'price' => 6780, 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS, 'images' => []]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $ignite->id, 'supplier_article' => 'a-64920045', 'supplier_name' => 'Dimplex Ignite XLF 50',
            'price' => 6103, 'currency' => 'BYN', 'raw' => ['article' => '64920045', 'colour' => '', 'name' => 'Ignite XLF 50', 'group' => 'ЭЛЕКТРОКАМИНЫ']]);

        $this->artisan('supplier:smolkom-site-photos', ['--apply' => true])
            ->expectsOutputToContain('metric.smolkom_site_photos.found_brand_site=1')
            ->expectsOutputToContain('metric.smolkom_site_photos.saved=2')
            ->expectsOutputToContain('metric.smolkom_site_photos.published=2')
            ->assertSuccessful();
        $black->refresh();
        $this->assertTrue($black->is_active);
        $this->assertCount(1, array_filter((array) $black->images));
        $this->assertTrue($ignite->fresh()->is_active);
        foreach (array_merge((array) $black->images, (array) $ignite->fresh()->images) as $path) {
            @unlink(public_path($path));
        }
    }

    public function test_wrong_and_watermarked_photos_are_taken_off_and_a_sibling_colour_shows_for_now(): void
    {
        $this->assertTrue(ChistyBeregPhotosCommand::watermarked('https://www.21vek.by/fireplaces/x.html'));
        $this->assertFalse(ChistyBeregPhotosCommand::watermarked('https://belsklad.by/x'));

        $category = Category::query()->create(['name' => 'Облицовки', 'slug' => 'oblicovki', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'Smolcom', 'slug' => 'smolcom', 'is_active' => true]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'smolkom'], ['name' => 'Смолком', 'currency' => 'BYN', 'is_active' => true]);
        $card = function (string $slug, string $colour, array $images, string $source) use ($category, $brand, $supplier) {
            $product = Product::query()->create(['name' => 'Портал Smolcom Line V42 '.$colour, 'slug' => $slug, 'sku' => 'SMOLKOM-'.strtoupper($slug),
                'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 1110, 'is_active' => true, 'images' => $images,
                'short_description' => 'Портал.', 'content_source_url' => $source]);
            SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'a-'.$slug, 'supplier_name' => 'Line V42',
                'price' => 856, 'currency' => 'BYN', 'raw' => ['article' => 'LINV4'.$slug, 'colour' => $colour, 'name' => 'LINE V42', 'group' => 'ПОРТАЛЫ', 'photo_searched_at' => '2026-11-10']]);

            return $product;
        };
        $white = $card('white', 'Бьянко белый', ['img/products/official/white.jpg'], 'https://smolcom.by/');
        $black = $card('black', 'Черный', ['img/products/official/wrong.jpg'], 'https://belsklad.by/line');
        $grey = $card('grey', 'Серый графит', ['img/products/official/21vek.jpg'], 'https://www.21vek.by/fireplaces/line.html');

        $this->artisan('supplier:smolkom-photo-cleanup', ['--slug' => ['black'], '--apply' => true])
            ->expectsOutputToContain('metric.smolkom_photo_cleanup.wrong=1')
            ->expectsOutputToContain('metric.smolkom_photo_cleanup.watermarked=1')
            ->expectsOutputToContain('metric.smolkom_photo_cleanup.temp_photo=2')
            ->assertSuccessful();
        foreach ([$black, $grey] as $product) {
            $product->refresh();
            $this->assertSame(['img/products/official/white.jpg'], $product->images);
            $this->assertStringContainsString('На фото — цвет «бьянко белый»', (string) $product->short_description);
            $offer = SupplierProduct::query()->where('product_id', $product->id)->sole();
            $this->assertSame($white->id, $offer->raw['temp_photo_from']);
            $this->assertArrayNotHasKey('photo_searched_at', $offer->raw);
        }
        $this->assertSame(['img/products/official/white.jpg'], $white->fresh()->images);
        $this->assertSame(sprintf(SmolkomPhotoCleanupCommand::NOTE, 'бьянко белый').' Портал.', $black->short_description);
        $this->assertSame('Портал.', SmolkomPhotoCleanupCommand::stripNote('Портал. На фото — цвет'));
        $this->assertSame('Портал. Белый.', SmolkomPhotoCleanupCommand::stripNote('Портал. Белый. Размеры для встраиван На фото —'));
        $this->assertSame('Портал.', SmolkomPhotoCleanupCommand::stripNote((string) $black->short_description));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('TDSheet');
        $sheet->fromArray([
            [null, null, null, null, 'Цены указаны на 09.11.2026'],
            [null, 'Заказ', 'Фото', 'Бренд', 'Номенклатура', 'Характеристика', 'Остаток', 'Резерв', 'Св. Остаток', 'Опт 3', 'РРЦ', 'Цена скидка1', 'Цена скидка2', 'Цена скидка3',
                'Габариты изделия', null, null, 'Габариты упаковки', null, null, 'Габариты встраиваемые', null, null, 'Ссылка', 'Цена действует до:', 'Номенклатура.Артикул', 'Штрихкод'],
            [null, null, null, null, 'ПОРТАЛЫ'],
            [null, null, null, null, '0 ЛИНЕЙНЫЕ 40 дюймов'],
            [null, null, null, 'Smolcom', 'GOVARD V42', 'Бьянко белый', 2, null, 2, 993, '1 290,00', null, null, null, '1 250', 732, 300, null, null, null, null, null, null, null, null, 'GOVV4Бб'],
            [null, null, null, 'Smolcom', 'GOVARD V42', 'Черный', 1, 1, null, 993, '1 290,00', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'GOVV4Ч'],
            [null, null, null, null, '2 классические STD'],
            [null, null, null, 'Smolcom', 'LUMSDEN STD-ASP', 'Бьянко белый', 6, null, 6, 606, '790', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'LUMSTБб'],
            [null, null, null, 'Smolcom', 'LUMSDEN STD-ASP', 'Дуб антик', 2, null, 2, 735, '960', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'LUMSTДа'],
            [null, null, null, null, 'ЭЛЕКТРОКАМИНЫ'],
            [null, null, null, null, '0 размер ЛИНЕЙНЫЕ'],
            [null, null, null, 'Royal Flame', 'Vision 60 LOG LED', null, 6, null, 6, '2 006,00', '2 510,00', null, null, null, null, null, null, null, null, null, null, null, null, 'https://catalog.onliner.by/electrofireplace/royalflame/vision266logled', null, 64930446],
            [null, null, null, 'Royal Flame', 'Crystal 36 RF', null, null, null, null, '1 755,00', '2 190,00', null, null, null, null, null, null, null, null, null, null, null, null, null, null, 64935693],
            [null, null, null, null, '2 размер STD-ASP'],
            [null, null, null, 'Royal Flame', 'Aspen Black', null, 2, null, 2, 548, 680, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 64934741, 2999000469475],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'smk').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

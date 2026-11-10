<?php

namespace Tests\Feature;

use App\Console\Commands\CreateSmolkomCardsCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
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

        // The unlinked Crystal 36 becomes a hidden card in the category of the linked electric fireplaces; Lumsden in
        // oak is left to the colourless Lumsden card.
        $this->artisan('supplier:smolkom-create', ['--apply' => true])
            ->expectsOutputToContain('metric.smolkom_create.card_exists=1')
            ->expectsOutputToContain('metric.smolkom_create.created=1')
            ->assertSuccessful();
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

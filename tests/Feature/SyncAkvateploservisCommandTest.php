<?php

namespace Tests\Feature;

use App\Console\Commands\CreateAkvateploservisCardsCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Marketplaces\DealByClient;
use App\Services\Suppliers\AkvateploservisPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncAkvateploservisCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The price list is in euro: the NBRB rate of the day turns it into BYN.
        Http::fake(['api.nbrb.by/*' => Http::response(['Cur_OfficialRate' => 3.3])]);
    }

    public function test_rows_link_by_article_and_model_and_share_merged_prices(): void
    {
        $file = $this->priceList();
        $category = Category::query()->create(['name' => 'Косвенные', 'slug' => 'kosvennye-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'galmet'], ['name' => 'Galmet', 'is_active' => true]);
        $card = fn (string $name) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => 1, 'is_active' => true]);
        $tower = $card('Бойлер косвенного нагрева Galmet TOWER SGW(S) 400 Skay (w/s) FL (26-408000N)');
        $neptunR = $card('Водонагреватель комбинированный Galmet Neptun Kombi SGW(S) 100-R SH правое подключение');
        $neptunL = $card('Водонагреватель комбинированный Galmet Neptun Kombi SGW(S)80 левое подключение');
        $apamet = $card('Бойлер косвенного нагрева Apamet Stil 150 Skay серый');

        $this->artisan('supplier:sync-akvateploservis', ['--file' => $file, '--apply' => true])
            ->expectsOutputToContain('metric.akvateploservis.article=1')
            ->expectsOutputToContain('metric.akvateploservis.model=2')
            ->expectsOutputToContain('metric.akvateploservis.suggested=1')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'akvateploservis')->sole();
        $offer = fn (string $code) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $code)->sole();
        $this->assertSame($tower->id, $offer('26-408000N')->product_id);
        $this->assertSame(4408.8, (float) $tower->fresh()->price);
        $this->assertSame('EUR', $offer('26-408000N')->currency);
        $this->assertSame(1109.0, (float) $offer('26-408000N')->price);
        $this->assertSame($neptunR->id, $offer('06-104870')->product_id);
        $this->assertTrue((bool) $offer('06-104870')->in_stock);
        // The L row of the pair has its price in the merged cells of the R row.
        $this->assertSame(1646.7, (float) $offer('06-104871')->price_byn);
        $this->assertSame($apamet->id, $offer('apamet-apamet-stil-150-skay-ws-cvet-seryi')->product_id);
        $this->assertSame(1686.3, (float) $apamet->fresh()->price);
        $this->assertSame($neptunL->id, $offer('06-084871')->raw['suggested_product_id']);
        $this->assertSame('out_of_stock', $offer('06-084970')->stock_status);
        $this->assertNull($offer('06-084970')->price_byn);
    }

    public function test_a_card_without_side_is_offered_to_the_first_row_and_a_discontinued_twin_never_links(): void
    {
        $file = $this->priceList();
        $category = Category::query()->create(['name' => 'Комбинированные', 'slug' => 'kombi-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['slug' => 'galmet'], ['name' => 'Galmet', 'is_active' => true]);
        $card = fn (string $name) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => 1, 'is_active' => true]);
        $sideless = $card('Водонагреватель комбинированный Galmet Neptun Kombi SGW(S)100');
        $exact = $card('Водонагреватель комбинированный Galmet Neptun Kombi SGW(S) 80-R SH');

        $this->artisan('supplier:sync-akvateploservis', ['--file' => $file, '--apply' => true])->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'akvateploservis')->sole();
        $offer = fn (string $code) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', $code)->sole();
        $this->assertSame($sideless->id, $offer('06-104870')->raw['suggested_product_id']);
        $this->assertNull($offer('06-104871')->raw['suggested_product_id'] ?? null);
        $this->assertSame($exact->id, $offer('06-084870')->product_id);
        $this->assertNull($offer('06-084970')->product_id);
    }

    public function test_titles_specs_and_categories_of_new_cards(): void
    {
        $this->assertSame('Бойлер косвенного нагрева Galmet TOWER SGW(S) 300 Skay (w/s) FL, белый (26-308070N)',
            CreateAkvateploservisCardsCommand::cardTitle('TOWER SGW(S) 300 Skay (w/s) FL, цвет - белый', 'Galmet', 'kosvennye', '26-308070N'));
        $this->assertSame('Бойлер косвенного нагрева Apamet Stil 150 Skay (w/s), серый',
            CreateAkvateploservisCardsCommand::cardTitle('Apamet Stil 150 Skay (w/s), цвет - серый', 'Apamet', 'kosvennye', ''));
        $this->assertSame(['kombinirovannye'], CreateAkvateploservisCardsCommand::targetCategories('NEPTUN KOMBI', 'NEPTUN KOMBI SGW(S) 80-R SH'));
        $this->assertSame(['electric'], CreateAkvateploservisCardsCommand::targetCategories('ЭЛЕКТРИЧЕСКИЕ', 'MAX SG 30 - водонагреватель 1,5 кВт'));
        $specs = CreateAkvateploservisCardsCommand::specsFromRow('Объем 153л, теплообменник 22.6/29,8 кВт (0,9 м2), полиуретан 50 мм, высота 1100 мм, наружный Ø 550 мм. Класс энергоэффективности - С.');
        $this->assertSame(['Объём', 'Теплообменник', 'Теплоизоляция', 'Высота', 'Наружный диаметр', 'Класс энергоэффективности'], array_column($specs, 'key'));
    }

    public function test_sbg_item_is_found_by_the_galmet_article(): void
    {
        $category = Category::query()->create(['name' => 'Косвенные', 'slug' => 'kosvennye-deal', 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'name' => 'Бойлер Galmet TOWER 300 белый', 'slug' => 'deal-galmet-tower', 'price' => 1040, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::query()->updateOrCreate(['code' => 'akvateploservis'], ['name' => 'Акватеплосервис', 'currency' => 'BYN', 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $card->id, 'supplier_article' => '26-308070N', 'supplier_name' => 'Galmet TOWER', 'match_status' => 'matched']);
        $this->mock(DealByClient::class, fn (MockInterface $mock) => $mock->shouldReceive('listProducts')->andReturn(['products' => [
            ['id' => 701, 'sku' => '26-308070N', 'name' => 'Водонагреватель Galmet Tower 300', 'price' => 990],
        ]]));

        $this->artisan('deal:sync-brand-prices', ['--supplier' => 'akvateploservis'])
            ->expectsOutputToContain("audit.deal_brand_prices=701:{$card->id}:akvateploservis:99000:104000")
            ->assertSuccessful();
    }

    public function test_model_key_reads_side_colour_and_codes(): void
    {
        $this->assertSame(AkvateploservisPriceList::modelKey('NEPTUN KOMBI SGW(S) 80-L SH (подключение теплообменника слева)'),
            AkvateploservisPriceList::modelKey('Водонагреватель комбинированный Galmet Neptun Kombi SG-W(S) 80-L SH'));
        $this->assertNotSame(AkvateploservisPriceList::modelKey('TOWER SGW(S) 300 Skay (w/s) FL, цвет - серый'),
            AkvateploservisPriceList::modelKey('TOWER SGW(S) 300 Skay (w/s) FL, цвет - белый'));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('ОБЩИЙ');
        $sheet->fromArray([
            [null, 'APAMET STIL (W/S) ВОДОНАГРЕВАТЕЛИ КОСВЕННОГО НАГРЕВА', 'ТУРЦИЯ'],
            ['Артикул', 'Наименование', 'ОПТ', 'РРЦ'],
            [null, 'Apamet Stil 150 Skay (w/s), цвет - серый', 358, 511],
            ['Объем 153л, теплообменник 22.6/29,8 кВт (0,9 м2), полиуретан 50 мм'],
            [null, 'NEPTUN KOMBI ВОДОНАГРЕВАТЕЛИ КОМБИНИРОВАННОГО НАГРЕВА'],
            ['Артикул', 'Наименование', 'ОПТ', 'РРЦ'],
            ['06-084870', 'NEPTUN KOMBI SGW(S) 80-R SH (подключение теплообменника справа )', 473, 569, 'ожидается поставка'],
            ['06-084871', 'NEPTUN KOMBI SGW(S) 80-L SH (подключение теплообменника слева)'],
            ['06-104870', 'NEPTUN KOMBI SGW(S) 100-R SH (подключение теплообменника справа)', 499, 602, 'склад'],
            ['06-104871', 'NEPTUN KOMBI SGW(S) 100-L SH (подключение теплообменника слева)'],
            [null, 'NEPTUN KOMBI ELEKTRONIK'],
            ['06-084970', 'NEPTUN KOMBI SGW(S) 80-R SH (подключение теплообменника справа )', 0, 0, 'сняты с производства'],
            [null, 'TOWER ВОДОНАГРЕВАТЕЛИ КОСВЕННОГО НАГРЕВА'],
            ['26-408000N', 'TOWER SGW(S) 400 Skay (w/s) FL, цвет - серый', 1109, 1336, 'под заказ'],
        ]);
        foreach (['C7:C8', 'D7:D8', 'E7:E8', 'C9:C10', 'D9:D10', 'E9:E10'] as $range) {
            $sheet->mergeCells($range);
        }
        $path = tempnam(sys_get_temp_dir(), 'gm').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

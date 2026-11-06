<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\PrestizhTeploPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncPrestizhTeploCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sheets_are_read_in_eur_at_the_rate_of_the_list(): void
    {
        $rows = collect(PrestizhTeploPriceList::parse($this->priceList()))->keyBy('code');

        // «Курс 3,5» in a sheet title: ОЦ 706 € → 2471 BYN, РЦ 830 € → 2905 BYN.
        $this->assertSame(['Vaillant', 2471.0, 2905.0], [$rows['0010023654']['brand'], $rows['0010023654']['wholesale'], $rows['0010023654']['rrc']]);
        $this->assertSame(['Настенные электрокотлы eloBLOCK', 'eloBLOCK VE 6/14'], [$rows['0010023654']['section'], $rows['0010023654']['name']]);
        // «по запросу», #N/A and 0 are not prices.
        $this->assertArrayNotHasKey('0010015359', $rows->all());
        $this->assertArrayNotHasKey('0010019345', $rows->all());
        // A size column joins the name; the short second header is no row.
        $this->assertSame('Металлопластиковая труба 16 x 2 мм', $rows['87.80.010']['name']);
        $this->assertSame('Comisa', $rows['87.80.010']['brand']);
        // «без насоса» is a variant of the section's item; the Meibes sheet gives Meibes.
        $this->assertSame('Насосная группа UK без насоса', $rows['МЕ26102 ЕА']['name']);
        $this->assertSame('Meibes', $rows['МЕ26102 ЕА']['brand']);
        $this->assertSame(PrestizhTeploPriceList::modelKey('Электрический котел Vaillant eloBLOCK VE 6/14'), PrestizhTeploPriceList::modelKey('eloBLOCK VE 6/14'));
        $this->assertCount(4, $rows);
    }

    public function test_rows_link_by_article_and_model_and_set_the_retail_price(): void
    {
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'kotly-prestizh-test', 'parent_id' => 0, 'is_active' => true]);
        $vaillant = Brand::query()->firstOrCreate(['slug' => 'vaillant'], ['name' => 'Vaillant', 'is_active' => true]);
        $comisa = Brand::query()->firstOrCreate(['slug' => 'comisa'], ['name' => 'Comisa', 'is_active' => true]);
        $card = fn (string $name, int $brand, ?string $sku = null) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'sku' => $sku,
            'category_id' => $category->id, 'brand_id' => $brand, 'price' => 0, 'is_active' => true]);
        $elo = $card('Электрический котел Vaillant eloBLOCK VE 6/14', $vaillant->id);
        $pipe = $card('Труба металлопластиковая Comisa 16x2', $comisa->id, '87.80.010');

        $this->artisan('supplier:sync-prestizh-teplo', ['--file' => $this->priceList(), '--apply' => true])
            ->expectsOutputToContain('metric.prestizh.article=1')
            ->expectsOutputToContain('metric.prestizh.model=1')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'prestizh_teplo')->sole();
        $offer = fn (Product $p) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $p->id)->sole();
        $this->assertSame([2471.0, 2905.0, 'preorder'], [(float) $offer($elo)->price_byn, (float) $elo->fresh()->price, $offer($elo)->stock_status]);
        $this->assertSame(5.36, (float) $pipe->fresh()->price);
    }

    public function test_card_titles_and_categories_by_the_kind_of_item(): void
    {
        $c = \App\Console\Commands\CreatePrestizhTeploCardsCommand::class;
        $case = fn (string $name, string $section, string $brand, string $sheet = '') => [$c::cardTitle(compact('name', 'section', 'brand', 'sheet')), $c::categorySlug($c::cardTitle(compact('name', 'section', 'brand', 'sheet')))];
        $this->assertSame(['Газовый котел Vaillant atmoTEC pro VUW 240/5-3', 'gazovye'], $case('atmoTEC pro VUW 240/5-3', 'Настенные газовые котлы atmoTEC, turboTEC', 'Vaillant'));
        $this->assertSame(['Газовый котел Protherm Пантера 25 KOO', 'gazovye'], $case('Пантера 25 KOO', 'Настенные газовые котлы серии Пантера', 'Protherm'));
        $this->assertSame(['Бойлер косвенного нагрева Drazice OKC 100 NTR', 'kosvennye'], $case('OKC 100 NTR мощность теплообменника 24кВт', 'магниевый анод, предохранительный клапан в бойлерах до 200л', 'Drazice'));
        $this->assertSame(['Бойлер косвенного нагрева Galmet FUSION SG(S) 100 FL', 'kosvennye'], $case('FUSION SG(S) 100 FL', 'FUSION • НАПОЛЬНЫЙ БОЙЛЕР ПОСЛОЙНОГО НАГРЕВА ДЛЯ РАБОТЫ С ДВУХКОНТУРНЫМИ ГАЗОВЫМИ КОТЛАМИ', 'Galmet'));
        $this->assertSame(['Расширительный бак Reflex N 8', 'membrannye-baki'], $case('N 8', 'Мембранные расширительные баки для отопления', 'Reflex'));
        $this->assertSame(['Насосная группа MKRE 1” без насоса, Meibes', 'gruppy-bystrogo-montazha-kotelnyx'], $case('1” без насоса', 'Насосная группа MKRE', 'Meibes'));
        $this->assertSame(['Воздухоотводчик автоматический с отсечным клапаном 1/2", Afriso', 'predokhranitelnaya-i-reguliruyushchaya-armatura'], $case('Воздухоотводчик автоматический с отсечным клапаном 1/2"', '', 'Afriso'));
        $this->assertSame(['ТЭН Galmet 2 кВт 5/4" 230В', 'elektricheskie-teny-dlya-otopleniya'], $case('2 кВт 5/4" 230В', 'ТЭНы • ДЛЯ ВОДОНАГРЕВАТЕЛЕЙ 100 - 500 л', 'Galmet'));
        $this->assertSame('Protherm Horiz. Terminal 60/100 PP', $case('Horiz. Terminal 60/100 PP', 'Бойлеры косвенного нагрева', 'Protherm')[0]);
        $this->assertSame('Apamet', $c::brandOf(['brand' => 'Galmet', 'section' => 'Apamet [Турция]']));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $vaillant = $book->getActiveSheet()->setTitle('Vaillant');
        $vaillant->fromArray([
            ['Расчет только в бел. рублях'],
            ['Артикул', 'Наименование', 'ОЦ, €', 'РЦ, €', 'Заказ, шт.', 'ОЦ, руб', 'РЦ, руб'],
            ['Настенные электрокотлы eloBLOCK'],
            ['0010023654', 'eloBLOCK VE 6/14', 706, 830, null, 706, 830],
            ['0010015359', 'atmoTEC pro VUW 280/5-3', '#N/A', '#N/A', null, '#N/A', '#N/A'],
            ['0010019345', 'atmoTEC plus VU 280/5-5', 0, 0, null, 0, 0],
        ]);
        $drazice = $book->createSheet()->setTitle('DRAZICE');
        $drazice->fromArray([
            ['Расчет только в бел. рублях. Курс 3,5'],
            ['Артикул', 'Модель', 'Описание', 'ОЦ, €', 'РЦ, €', 'Заказ, шт.'],
            ['OKC 100 NTR', 'OKC 100 NTR', 'Напольный косвенного нагрева', 'по запросу', 'по запросу'],
        ]);
        $comisa = $book->createSheet()->setTitle('Comisa');
        $comisa->fromArray([
            ['Фото', 'Наименование', 'Типоразмер', 'Артикул', 'ОЦ, €', 'РЦ, €', 'Заказ, шт.'],
            ['Фото', 'Наименование', 'Типоразмер', 'Артикул', 'ОЦ', 'РЦ', 'Заказ'],
            [null, 'Металлопластиковая труба', '16 x 2 мм', '87.80.010', 1.07, 1.53],
            [null, 'Металлопластиковая труба', '20 х 2 мм', '87.80.020', 1.85, 2.64],
        ]);
        $meibes = $book->createSheet()->setTitle('Meibes, Huch EnTEC ');
        $meibes->fromArray([
            ['Фото', 'Артикул', 'Наименование', 'Типоразмер', 'ОЦ, €', 'РЦ, €', 'Заказ, шт.'],
            ['Насосная группа UK'],
            [null, 'МЕ26102 ЕА', 'без насоса', null, 760, 950],
        ]);
        $book->createSheet()->setTitle('Смета')->fromArray([['Артикул', 'РЦ, €'], ['X1', 100]]);
        $path = tempnam(sys_get_temp_dir(), 'prestizh').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

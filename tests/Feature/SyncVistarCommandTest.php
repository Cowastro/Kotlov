<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\VistarPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncVistarCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_brands_and_stock_are_read(): void
    {
        $rows = collect(VistarPriceList::parse($this->priceList()))->keyBy('code');

        $this->assertSame(['BAXI', 2270.0, 2720.0, ''], [$rows['7659762']['brand'], $rows['7659762']['wholesale'], $rows['7659762']['rrc'], $rows['7659762']['stock']]);
        // The stock stands next to the «Изображение» column.
        $this->assertSame('В наличии', $rows['A7795896']['stock']);
        // The Ampera table names the power only; a flue table with «Мощность» over it keeps its names.
        $this->assertSame('BAXI Ampera 6 кВт (2-4-6)', $rows['E8403106']['name']);
        $this->assertSame('Коаксиальная труба дымохода 60/100 мм', $rows['KHG71405961']['name']);
        $this->assertSame('KRATS', $rows['KS-80']['brand']);
        // «Reflex» over the table gives the brand of its rows.
        $this->assertSame('Reflex', $rows['7302000']['brand']);
        $this->assertArrayNotHasKey('Стоимость предложения BYN:', $rows->all());
        $this->assertSame(VistarPriceList::modelKey('Газовый котел BAXI ECO-4s 24F'), VistarPriceList::modelKey('ECO-4s 24 F (дымоход в подарок)'));
        $this->assertSame(VistarPriceList::modelKey('BAXI SLIM 1.620 iN'), VistarPriceList::modelKey('SLIM 1.620 iN (необходим стабилизатор тяги)'));
    }

    public function test_rows_link_by_article_and_model_and_set_the_retail_price(): void
    {
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'kotly-vistar-test', 'parent_id' => 0, 'is_active' => true]);
        $baxi = Brand::query()->firstOrCreate(['slug' => 'baxi'], ['name' => 'BAXI', 'is_active' => true]);
        $card = fn (string $name, ?string $sku = null) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'sku' => $sku,
            'category_id' => $category->id, 'brand_id' => $baxi->id, 'price' => 0, 'is_active' => true]);
        $eco = $card('Газовый котел BAXI ECO-4s 24');
        $luna = $card('Конденсационный котел BAXI LUNA Classic 1.24', 'A7795896');

        $this->artisan('supplier:sync-vistar', ['--file' => $this->priceList(), '--apply' => true])
            ->expectsOutputToContain('metric.vistar.article=1')
            ->expectsOutputToContain('metric.vistar.model=1')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'vistar')->sole();
        $offer = fn (Product $p) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $p->id)->sole();
        $this->assertSame([2270.0, 2720.0, 'preorder'], [(float) $offer($eco)->price_byn, (float) $eco->fresh()->price, $offer($eco)->stock_status]);
        $this->assertSame(['in_stock', 3860.0], [$offer($luna)->stock_status, (float) $luna->fresh()->price]);
    }

    public function test_card_titles_name_the_kind_of_item(): void
    {
        $title = fn (array $raw) => \App\Console\Commands\CreateVistarCardsCommand::cardTitle($raw);
        $this->assertSame('Газовый котел BAXI ECO Four 24 F (двухконтурный)', $title(['name' => 'ECO Four 24 F ', 'brand' => 'BAXI', 'section' => 'Газовые котлы серии ECO Four', 'type' => 'Двухконтурный', 'sheet' => 'Традиционные котлы BAXI']));
        $this->assertSame('Конденсационный газовый котел BAXI LUNA Classic 1.24 (одноконтурный)', $title(['name' => 'LUNA Classic 1.24', 'brand' => 'BAXI', 'section' => 'Газовые конденсационные котлы серии LUNA Classic', 'type' => 'Одноконтурный ', 'sheet' => 'Конденсационные котлы BAXI']));
        $this->assertSame('Бойлер косвенного нагрева Viena WTD 100', $title(['name' => 'WTD 100 (Мощность при 80°С = 28кВт) 470x470x1220 Стальной корпус', 'brand' => 'Viena', 'section' => 'Бойлер', 'sheet' => 'Водонагреватели Viena']));
        $this->assertSame('Мембранный бак Reflex DE 12 л, 4 бар', $title(['name' => '12 литров, 4 бара', 'brand' => 'Reflex', 'section' => 'Для систем питьевого, горячего водоснабжения', 'sheet' => 'Расширительные баки']));
        $this->assertSame('Коаксиальная труба дымохода 60/100 мм, BAXI', $title(['name' => 'Коаксиальная труба дымохода 60/100 мм', 'brand' => 'BAXI', 'section' => 'Дымоходы из алюминия для традиционных котлов', 'sheet' => 'Традиционные котлы BAXI']));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('Традиционные котлы BAXI')->fromArray([
            ['Газовые котлы серии ECO-4s'],
            ['Артикул', 'Наименование ', 'Тип', 'Цена ОПТ, BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['7659762', 'ECO-4s 24 ', 'Двухконтурный', 2270, 2720],
            ['Принадлежности'],
            ['Артикул', 'Наименование', 'Цена со скидкой BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['KS-80', 'Крепежный хомут D=80, KRATS', 15, 20],
        ]);
        $book->createSheet()->setTitle('Конденсационные котлы BAXI')->fromArray([
            ['Газовые конденсационные котлы серии LUNA Classic'],
            ['Артикул', 'Наименование ', 'Тип', 'Цена ОПТ BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['A7795896', 'LUNA Classic 1.24', 'Одноконтурный ', 3290, 3860, null, 'В наличии'],
            ['Дымоходы'],
            ['Артикул', 'Мощность, кВт', null, 'Цена со скидкой BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['KHG71405961', 'Коаксиальная труба дымохода 60/100 мм', null, '225', '270'],
        ]);
        $book->createSheet()->setTitle('Электрокотлы')->fromArray([
            ['BAXI Ampera'],
            ['Артикул', 'Мощность кВт, (ступени кВт)', 'Электрическая коммутация', 'Цена со скидкой BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['E8403106', '6 (2-4-6)', '3х380', 2125, 2500],
        ]);
        $book->createSheet()->setTitle('Расширительные баки ')->fromArray([
            ['Reflex'],
            ['Артикул', 'Описание', 'Цена со скидкой BYN', 'Розничная стоимость, BYN', 'Изображение'],
            ['Для систем питьевого, горячего водоснабжения:'],
            ['7302000', '12 литров, 4 бара', 125, 155],
            ['Стоимость предложения BYN: ', 14775, 23990],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'vistar').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

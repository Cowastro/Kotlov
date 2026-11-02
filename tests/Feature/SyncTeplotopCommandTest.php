<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\TeplotopPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncTeplotopCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_cards_and_radiators_are_read_from_both_files(): void
    {
        [$opt, $rrc] = $this->priceLists();
        $rows = collect(TeplotopPriceList::parse($opt, $rrc))->keyBy('name');

        $this->assertSame([1670.0, 2025.0], [$rows['Classic 20']['wholesale'], $rows['Classic 20']['rrc']]);
        // «РАСПРОДАЖА!!!» under ФОРВАРД - 16: its stock is sold at these prices.
        $this->assertSame([924.84, 1109.64, true], [$rows['ФОРВАРД - 16']['wholesale'], $rows['ФОРВАРД - 16']['rrc'], $rows['ФОРВАРД - 16']['sale']]);
        $this->assertSame([1763.0, 2150.0], [$rows['PRIME V 24']['wholesale'], $rows['PRIME V 24']['rrc']]);
        // The opt file's РРЦ wins over the RRC file (which had two KRATS prices swapped).
        $this->assertSame([31.5, 47.0, 'Y80-1', 'KRATS'], [$rows['Удлинитель D 80 L 1000 мм']['wholesale'], $rows['Удлинитель D 80 L 1000 мм']['rrc'], $rows['Удлинитель D 80 L 1000 мм']['article'], $rows['Удлинитель D 80 L 1000 мм']['brand']]);
        $radiator = $rows['Радиатор стальной боковое подключение LEMAX Compact 22х500х1000'];
        $this->assertSame([300.0, 390.0, 'lemax-rad-c-22-500-1000'], [$radiator['wholesale'], $radiator['rrc'], $radiator['code']]);
        $this->assertArrayNotHasKey('РАСПРОДАЖА!!!', $rows->all());
    }

    public function test_rows_link_by_model_and_article_and_set_the_retail_price(): void
    {
        [$opt, $rrc] = $this->priceLists();
        $category = Category::query()->create(['name' => 'Котлы', 'slug' => 'kotly-tt-test', 'parent_id' => 0, 'is_active' => true]);
        $lemax = Brand::query()->firstOrCreate(['slug' => 'lemaks'], ['name' => 'Лемакс', 'is_active' => true]);
        $krats = Brand::query()->firstOrCreate(['slug' => 'krats'], ['name' => 'KRATS', 'is_active' => true]);
        $card = fn (string $name, Brand $brand, ?string $sku = null) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'sku' => $sku,
            'category_id' => $category->id, 'brand_id' => $brand->id, 'price' => 0, 'is_active' => true]);
        $classic = $card('Газовый котел Лемакс Classic 20', $lemax);
        $forward = $card('Твердотопливный котел Лемакс Форвард 16', $lemax);
        $pipe = $card('Удлинитель дымохода KRATS D80 1000 мм', $krats, 'Y80-1');

        $this->artisan('supplier:sync-teplotop', ['--opt' => $opt, '--rrc' => $rrc, '--apply' => true])
            ->expectsOutputToContain('metric.teplotop.model=1')
            ->expectsOutputToContain('metric.teplotop.article=1')
            ->expectsOutputToContain('metric.teplotop.suggested=1')
            ->assertSuccessful();

        $supplier = Supplier::query()->where('code', 'teplotop')->sole();
        $offer = fn (Product $p) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $p->id)->sole();
        $this->assertSame(2025.0, (float) $classic->fresh()->price);
        $this->assertSame(1670.0, (float) $offer($classic)->price_byn);
        $this->assertSame('preorder', $offer($classic)->stock_status);
        $this->assertSame(47.0, (float) $pipe->fresh()->price);
        $suggested = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_name', 'like', '%ФОРВАРД - 16%')->sole();
        $this->assertSame($forward->id, $suggested->raw['suggested_product_id']);
        $this->assertTrue($suggested->raw['sale']);

        // A wrong link is undone and stays undone on the next upload.
        $this->artisan('supplier:sync-teplotop', ['--opt' => $opt, '--rrc' => $rrc, '--apply' => true, '--unlink' => ['art-y80-1']])->assertSuccessful();
        $this->artisan('supplier:sync-teplotop', ['--opt' => $opt, '--rrc' => $rrc, '--apply' => true])->assertSuccessful();
        $this->assertNull(SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'art-y80-1')->sole()->product_id);
    }

    /** @return array{0:string,1:string} */
    private function priceLists(): array
    {
        $paths = [];
        foreach (['opt', 'rrc'] as $kind) {
            $book = new Spreadsheet;
            $floor = $book->getActiveSheet()->setTitle('Напольн.котлы 02.11.26');
            $floor->fromArray([
                ['Прайс-лист на напольные газовые котлы от 02.11.2026'],
                ['CLASSIC', 'S, м2 max обогрева', null, null, null, null, ' МОЦ с НДС, BYN ', 'Розница с НДС, BYN'],
                ['Classic 20', 200, null, null, null, null, 1670, 2025],
                ['ФОРВАРД', 'S, м2 max обогрева', null, null, null, null, ' МОЦ с НДС, BYN ', 'Розница с НДС, BYN'],
                ['ФОРВАРД - 16', 160, null, null, null, null, 1156.05, 1387.05],
                ['РАСПРОДАЖА!!! ', null, null, null, null, null, 924.84, 1109.64],
            ]);
            $prime = $book->createSheet()->setTitle('Настенные котлы Prime 01.11.24');
            $prime->fromArray([
                ['Модель / Стоимость (с дымоходом в комплекте) ', null, null, null, null, null, null, ' МОЦ с НДС, BYN', 'РРЦ с НДС, BYN', null, ' МОЦ с НДС, BYN', 'РРЦ с НДС, BYN'],
                [null, null, null, null, null, null, null, 1763, 2150, null, 1943.4, 2370],
                [null, null, null, null, null, null, null, 'PRIME V 24', null, null, 'PRIME V 32'],
                ['Тип камеры сгорания', null, null, null, null, null, null, 'Закрытая', null, null, 'Закрытая'],
            ]);
            $krats = $book->createSheet()->setTitle('Дымоходы  KRATS');
            $krats->fromArray($kind === 'opt'
                ? [['Название', 'Артикул', null, null, null, null, 'Опт', 'РРЦ'], ['Удлинитель D 80 L 1000 мм', 'Y80-1', null, null, null, null, 31.5, 47]]
                : [['Название', 'Артикул', null, null, null, null, 'РРЦ'], ['Удлинитель D 80 L 1000 мм', 'Y80-1', null, null, null, null, 99]]);
            $radiators = $book->createSheet()->setTitle('LEMAX-боковое 16.06.25');
            $radiators->fromArray([
                [null, 'Длина, мм', 'тип ', null, 'Compact – боковое'],
                [null, null, 11, null, null, null, null, null, null, null, null, 21, null, null, null, null, null, null, null, null, 22],
                [null, null, '↑300', null, 400, null, '↑ 500', null, 600, null, null, '↑300', null, 400, null, '↑ 500', null, 600, null, null, '↑200', null, '↑300', null, '↑400', null, '↑500'],
                [null, 1000, 846, 550, 1040, 676, 1234, 802, 1433, 932, null, 1165, 757, 1440, 936, 1788, 1162, 1865, 1212, null, 1054, 685, 1544, 1004, 1941, 1262, 2265, 1472],
                [null, null, 120, null, null, null, 140, null, null, null, null, 180, null, null, null, 210, null, null, null, null, '-', null, 240, null, 270, null, $kind === 'opt' ? 300 : 390],
            ]);
            $paths[$kind] = tempnam(sys_get_temp_dir(), 'tt').'.xlsx';
            (new Xlsx($book))->save($paths[$kind]);
        }

        return [$paths['opt'], $paths['rrc']];
    }
}

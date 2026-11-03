<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\TeplovSukhovPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncTeplovSukhovPriceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_sheet_is_read_with_its_retail_and_wholesale_price(): void
    {
        $list = TeplovSukhovPriceList::parse($this->priceList());
        $rows = collect($list['rows'])->keyBy('sku');

        $this->assertSame('2026-11-03', $list['price_date']);
        $this->assertSame(['72.93', 51.05, 'ТиС 0.8'], [$rows['TS.FRT.TRB.0115.32850']['retail'], $rows['TS.FRT.TRB.0115.32850']['wholesale'], $rows['TS.FRT.TRB.0115.32850']['sheet']]);
        // The MC Black sheet: an old matrix on the left, the name is the column before the article.
        $this->assertSame('Зонт ЗМ-Р (430-0,5) MC Black D115', $rows['TS.MCB.ZNT.0115.78181']['name']);
        // A row without a retail price («-») is not sold; a reused article gets -01/-02.
        $this->assertArrayNotHasKey('TS.FRT.OTV.0115.1', $rows->all());
        $this->assertSame(['TS.KMP.ZRV.0120.72047-01', 'TS.KMP.ZRV.0120.72047-02'], $rows->keys()->filter(fn ($sku) => str_starts_with($sku, 'TS.KMP'))->values()->all());
    }

    public function test_the_retail_price_changes_only_on_apply_and_the_wholesale_one_is_kept(): void
    {
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'dymohody-tis-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['name' => 'Теплов и Сухов'], ['slug' => 'teplov-i-sukhov-test', 'is_active' => true]);
        $supplier = Supplier::query()->firstOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов']);
        $card = fn (string $name, float $price) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => $price, 'is_active' => true]);
        $pipe = $card('Труба L1000 ТМ-Р 430-0.8 D115', 60.5);
        $cap = $card('Зонт ЗМ-Р 430-0.5 MC Black D115', 71.88);
        $file = $this->priceList();

        $this->artisan('supplier:sync-teplov-sukhov-retail', ['--file' => $file])
            ->expectsOutputToContain('metric.teplov_sukhov.changed=1')
            ->expectsOutputToContain('metric.teplov_sukhov.up=1')
            ->assertSuccessful();
        $this->assertSame(60.5, (float) $pipe->fresh()->price);

        $this->artisan('supplier:sync-teplov-sukhov-retail', ['--file' => $file, '--apply' => true])
            ->expectsOutputToContain('audit.teplov_sukhov.price='.$pipe->id.':60.50:72.93')
            ->assertSuccessful();
        $this->assertSame([72.93, 71.88], [(float) $pipe->fresh()->price, (float) $cap->fresh()->price]);
        $offer = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'TS.FRT.TRB.0115.32850')->sole();
        $this->assertSame([$pipe->id, 51.05, '2026-11-03', 'ТиС 0.8'], [$offer->product_id, $offer->raw['wholesale'], $offer->raw['price_date'], $offer->raw['sheet']]);
    }

    public function test_a_link_made_under_a_suffixed_article_moves_back_to_the_single_article(): void
    {
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'dymohody-tis-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['name' => 'Теплов и Сухов'], ['slug' => 'teplov-i-sukhov-test', 'is_active' => true]);
        $supplier = Supplier::query()->firstOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов']);
        $pipe = Product::query()->create(['name' => 'Труба L1000 ТМ-Р 430-0.8 D115', 'slug' => 'truba-tis', 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => 60, 'is_active' => true]);
        SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $pipe->id, 'supplier_article' => 'TS.FRT.TRB.0115.32850-01',
            'supplier_name' => 'Труба L1000 ТМ-Р 430-0.8 D115', 'match_status' => 'matched']);

        $this->artisan('supplier:sync-teplov-sukhov-retail', ['--file' => $this->priceList(), '--apply' => true])->assertSuccessful();

        $this->assertSame(['TS.FRT.TRB.0115.32850'], SupplierProduct::query()->where('product_id', $pipe->id)->pluck('supplier_article')->all());
        $this->assertSame(72.93, (float) $pipe->fresh()->price);
    }

    public function test_of_two_fitting_cards_the_one_with_exactly_the_diameters_of_the_row_wins(): void
    {
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'dymohody-tis-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['name' => 'Теплов и Сухов'], ['slug' => 'teplov-i-sukhov-test', 'is_active' => true]);
        Supplier::query()->firstOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов']);
        $card = fn (string $name) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => 9, 'is_active' => true]);
        $adapter = $card('Теплов и Сухов Адаптер Моно М-М AISI 430 0,5 мм Ø80 (У)');
        $reducer = $card('Теплов и Сухов Адаптер-переход Моно М-М с AISI 430 0,5 мм Ø80/110 (У)');

        $this->artisan('supplier:sync-teplov-sukhov-retail', ['--file' => $this->priceList(), '--apply' => true])->assertSuccessful();

        $this->assertSame([10.24, 9.0], [(float) $adapter->fresh()->price, (float) $reducer->fresh()->price]);
    }

    public function test_a_row_is_linked_by_hand_to_a_card_of_another_wording(): void
    {
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'dymohody-tis-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->firstOrCreate(['name' => 'Теплов и Сухов'], ['slug' => 'teplov-i-sukhov-test', 'is_active' => true]);
        $supplier = Supplier::query()->firstOrCreate(['code' => 'teplov'], ['name' => 'Теплов и Сухов']);
        $card = Product::query()->create(['name' => 'Дымоход моно 1 м ТиС ф115', 'slug' => 'dymokhod-tis-115', 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => 50, 'is_active' => true]);

        $this->artisan('supplier:sync-teplov-sukhov-retail', ['--file' => $this->priceList(), '--apply' => true, '--link' => ['TS.FRT.TRB.0115.32850=dymokhod-tis-115']])
            ->assertSuccessful();

        $this->assertSame(72.93, (float) $card->fresh()->price);
        $this->assertSame($card->id, SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'TS.FRT.TRB.0115.32850')->value('product_id'));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('ТиС 0.8')->fromArray([
            ['ООО "СанБизнесГруп"'],
            ['ПРАЙС-ЛИСТ от 03.11.2026'],
            [' Фото', 'Наименование', 'Артикул', 'Розница', 'Опт'],
            [null, 'Труба L1000 ТМ-Р 430-0.8 D115', 'TS.FRT.TRB.0115.32850', 72.9312, 51.0518],
            [null, 'Отвод 87* ОМ-Р 430-1,0 D115', 'TS.FRT.OTV.0115.1', '-', '-'],
            [null, 'Адаптер Моно М-М 430-0.5 D80', 'TS.FRT.AKL.0080.62699', 10.24, 7.17],
            [null, 'Заглушка ревизии ЗРМ-Р 430-0.5 D120', 'TS.KMP.ZRV.0120.72047', 20.1, 14],
            [null, 'Заглушка ревизии ЗРТ-Р 430-0.5/430 D120/200', 'TS.KMP.ZRV.0120.72047', 40.2, 28],
        ]);
        $book->createSheet()->setTitle('MC Black')->fromArray([
            [],
            ['Наименование / Диаметр', 115, 150, null, 'Наименование / Диаметр', 'Артикул', 'Розница', 'Опт'],
            ['Зонт ЗМ-Р (430-0,5)', 715, 820, null, 'Зонт ЗМ-Р (430-0,5) MC Black D115', 'TS.MCB.ZNT.0115.78181', 71.884, 50.319],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'tis').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

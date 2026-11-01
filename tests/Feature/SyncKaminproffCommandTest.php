<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\KaminproffPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncKaminproffCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_rows_link_to_fireway_cards_by_model(): void
    {
        $file = $this->priceList();
        $category = Category::query()->create(['name' => 'Печи', 'slug' => 'pechi-test', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'FireWay', 'slug' => 'fireway', 'is_active' => true]);
        $card = fn (string $name, float $price = 100) => Product::query()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id,
            'brand_id' => $brand->id, 'price' => $price, 'is_active' => true]);
        $door = $card('Дверца K-411 топочная 250×240 мм Fireway');
        $prut = $card('Банная печь Fireway ПароВар 24 (К201)');
        $left = $card('Каминная топка Fireway SOFIA (левая)');
        $right = $card('Каминная топка Fireway SOFIA (правая)');
        $skif = $card('Отопительно-варочная печь Fireway SKIF', 9321);
        $iskra = $card('Банная печь Fireway ИСКРА 16');
        $supplier = Supplier::query()->updateOrCreate(['code' => 'kaminproff'], ['name' => 'Каминпрофессионал', 'currency' => 'BYN', 'is_active' => true]);
        $old = SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $skif->id, 'supplier_article' => 'fireway-otopitelno-varochnaya-pech-fireway-skif',
            'supplier_name' => 'Печь SKIF отопительно-варочная', 'price' => 7170, 'currency' => 'BYN']);

        $this->artisan('supplier:sync-kaminproff', ['--file' => $file, '--apply' => true])
            ->expectsOutputToContain('metric.kaminproff.model=5')
            ->expectsOutputToContain('metric.kaminproff.suggested=1')
            ->expectsOutputToContain('metric.kaminproff.rekeyed=1')
            ->assertSuccessful();

        $offer = fn (Product $product) => SupplierProduct::query()->where('supplier_id', $supplier->id)->where('product_id', $product->id)->sole();
        $this->assertSame(250.0, (float) $offer($door)->price_byn);
        $this->assertSame('preorder', $offer($door)->stock_status);
        $this->assertSame(333.0, (float) $door->fresh()->price);
        // The forged ПароВар 24 Ковка is another stove than the plain ПароВар 24 card.
        $this->assertSame(1040.0, (float) $offer($prut)->price_byn);
        $this->assertSame(1, SupplierProduct::query()->where('product_id', $prut->id)->count());
        $this->assertSame(4078.0, (float) $offer($left)->price_byn);
        $this->assertSame(4078.0, (float) $offer($right)->price_byn);
        // The older offer of SKIF takes the new code instead of becoming a second offer.
        $this->assertSame($old->id, $offer($skif)->id);
        $this->assertSame(7735.0, (float) $skif->fresh()->price);
        $suggested = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_name', 'like', '%ИСКРА%')->sole();
        $this->assertNull($suggested->product_id);
        $this->assertSame($iskra->id, $suggested->raw['suggested_product_id']);
        $this->assertSame('unmatched', SupplierProduct::query()->where('supplier_name', 'like', '%Ковка (К201)%')->sole()->match_status);
    }

    public function test_model_key_reads_codes_in_latin_and_sizes_as_numbers(): void
    {
        $this->assertSame(KaminproffPriceList::modelKey('Дверца К-411 топочная 250х240мм'), KaminproffPriceList::modelKey('Дверца K-411 топочная 250×240 мм Fireway'));
        $this->assertSame(KaminproffPriceList::modelKey('Плита Т201 с двумя конфорками'), KaminproffPriceList::modelKey('Плита T201 с двумя конфорками Fireway'));
        $this->assertNotSame(KaminproffPriceList::modelKey('Банная печь ПароВар 24 Ковка (К201)'), KaminproffPriceList::modelKey('Банная печь ПароВар 24 (К201)'));
        $this->assertSame('лампа маяк1', KaminproffPriceList::modelKey('Лампа для бани Маяк-1'));
    }

    public function test_new_card_titles_and_specs_come_from_the_price_row(): void
    {
        $this->assertSame('Дверь для бани стеклянная Fireway 2000×800 бронза', \App\Console\Commands\CreateKaminproffCardsCommand::cardTitle('Дверь стеклянная FireWay 2х0,8 бронза'));
        $this->assertSame('Банная печь Fireway ПароВар 24 Ковка (К201)', \App\Console\Commands\CreateKaminproffCardsCommand::cardTitle('Банная Печь Паровар 24 Ковка (К201)'));
        $this->assertSame('Каминная топка Fireway DAGMAR', \App\Console\Commands\CreateKaminproffCardsCommand::cardTitle('Каминная топка DAGMAR'));
        $this->assertSame([
            ['key' => 'Мощность', 'value' => '10 кВт'],
            ['key' => 'Габариты (Ш×Г×В)', 'value' => '660×360×650 мм'],
            ['key' => 'Вес', 'value' => '101 кг'],
            ['key' => 'Диаметр дымохода', 'value' => '180 мм'],
        ], \App\Console\Commands\CreateKaminproffCardsCommand::specsFromRow('Мощность: 10 кВТ Габариты (Ш×Г×В): 660×360×650 mm. Вес: 101 кг Диаметр дымохода: 180 mm'));
        $this->assertSame(['drovianye-peci-bannye'], \App\Console\Commands\CreateKaminproffCardsCommand::targetCategories('Банные Печи', 'Банная печь ALMA c Сеткой'));
    }

    private function priceList(): string
    {
        $book = new Spreadsheet;
        $stoves = $book->getActiveSheet()->setTitle('Камины, печи');
        $stoves->fromArray([
            ['Официальный дистрибьютор фирмы Fireway в Беларуси', null, null, 'kaminproff.by kaminproff@mail.ru'],
            ['Наименование ', 'Фото', 'Характеристики', 'опт', 'ррц'],
            [7, 'Каминная топка SOFIA левая/правая', 'new', 'Мощность: 14 кВТ', 4078, 5424],
            [32, 'Печь отопительно-варочная SKIF L/R', null, 'Мощность : 8 кВт', 5816, 7735],
        ]);
        $sauna = $book->createSheet()->setTitle('Банные Печи');
        $sauna->fromArray([
            ['Наименование', 'Фото', 'опт', 'ррц'],
            ['Печь банная ИСКРА 16 c закрытой каменкой ', null, 711, 898],
            ['Паровар 24'],
            ['Банная печь ПароВар 24 Ковка (К201)', null, 1250, 1624],
            ['Банная печь ПароВар 24 (К201)', null, 1040, 1352],
        ]);
        $cast = $book->createSheet()->setTitle('Печное литье');
        $cast->fromArray([
            ['Дверцы топочные'],
            [22, 'Дверца K-411 топочная 250х240мм', 250, 333],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'fw').'.xlsx';
        (new Xlsx($book))->save($path);

        return $path;
    }
}

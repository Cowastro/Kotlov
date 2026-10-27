<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Resources\Suppliers\Pages\ViewSupplier;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Suppliers\CooperWholesalePriceList;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncCooperDoorsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function door(int $id, string $name, string $series, int $price, string $short): array
    {
        return [
            'id' => $id, 'name' => $name, 'permalink' => 'https://cooper.by/product/door-'.$id.'/',
            'prices' => ['price' => (string) ($price * 100), 'currency_minor_unit' => 2],
            'categories' => [['name' => 'Серия '.$series]], 'is_in_stock' => true, 'images' => [],
            'short_description' => $short,
        ];
    }

    public function test_first_import_creates_cards_with_retail_price_and_wholesale_cost(): void
    {
        Category::query()->create(['name' => 'Двери для бани и сауны', 'slug' => 'dveri-dlya-ban-i-saun', 'is_active' => true]);
        Storage::fake('local');
        CooperWholesalePriceList::save([
            ['name' => 'БДК "COOPER" Стандарт, бронза матовая, 200х80', 'made_to_order' => false, 'wholesale' => 399.17, 'rrc' => 519],
            ['name' => 'БДК "COOPER" PRO, бронза, 200х81,5', 'made_to_order' => false, 'wholesale' => 678.43, 'rrc' => 882],
        ], 'opt.xlsx');
        Http::fake(['cooper.by/wp-json/*' => Http::response([
            $this->door(36360, 'Дверь стеклянная для бани 200*80 Бронза матовая', 'Стандарт', 519,
                '<ul><li>Дверная коробка (40*75мм) — ОЛЬХА;</li><li>Закаленное стекло — 8 мм;</li><li>Петли овальные — 3 шт;</li></ul>'),
            $this->door(36385, 'Дверь стеклянная для бани PRO 200*81,5 Бронза', 'PRO', 882, '<p>Петли усиленные G60 — 3 шт;</p>'),
            ['id' => 36422, 'name' => 'Дверная коробка из Ольхи', 'permalink' => 'https://cooper.by/product/box/', 'prices' => ['price' => '0', 'currency_minor_unit' => 2],
                'categories' => [['name' => 'Дверная коробка из ОЛЬХИ']], 'is_in_stock' => true, 'images' => [], 'short_description' => ''],
        ])]);

        $this->artisan('supplier:sync-cooper-doors')->assertSuccessful();
        $this->assertSame(0, Product::query()->where('sku', 'like', 'COOPER-%')->count());

        $this->artisan('supplier:sync-cooper-doors --apply --create-new')->assertSuccessful();

        $door = Product::query()->where('sku', 'COOPER-36360')->firstOrFail();
        $this->assertSame('Дверь стеклянная для бани Cooper Стандарт 200×80, бронза матовая', $door->name);
        $this->assertSame(519.0, (float) $door->price);
        $this->assertSame('Cooper', $door->brand->name);
        $this->assertContains(['key' => 'Материал коробки', 'value' => 'Ольха'], $door->specs);
        $this->assertContains(['key' => 'Поверхность стекла', 'value' => 'Матовая'], $door->specs);
        $this->assertContains(['key' => 'Стекло', 'value' => 'Закалённое, 8 мм'], $door->specs);
        $this->assertSame('Дверь стеклянная для бани Cooper PRO 200×81,5, бронза', Product::query()->where('sku', 'COOPER-36385')->value('name'));

        $link = SupplierProduct::query()->where('product_id', $door->id)->firstOrFail();
        // The cost from the wholesale list: «БДК "COOPER" Стандарт, бронза матовая, 200х80».
        $this->assertSame(399.17, (float) $link->price_byn);
        $this->assertSame(678.43, (float) SupplierProduct::query()->where('supplier_article', 'cooper-36385')->value('price_byn'));
        $this->assertSame('saunaopt', $link->supplier->code);
        $this->assertSame(2, Product::query()->where('sku', 'like', 'COOPER-%')->count());

        // The daily run updates; it never creates twice.
        $this->artisan('supplier:sync-cooper-doors --apply --create-new')->assertSuccessful();
        $this->assertSame(2, Product::query()->where('sku', 'like', 'COOPER-%')->count());
        $this->assertSame(2, SupplierProduct::query()->where('supplier_article', 'like', 'cooper-%')->count());
    }

    public function test_door_key_reads_the_price_list_and_the_site_the_same_way(): void
    {
        $key = fn (string $name) => \App\Console\Commands\SyncCooperDoorsCommand::doorKey($name);

        $this->assertSame($key('БДК "COOPER" Стандарт, бронза матовая, 200х80'), $key('Серия Стандарт Дверь стеклянная для бани 200*80 Бронза матовая'));
        $this->assertSame($key('БДК "COOPER" PRO, бронза, 200х81,5'), $key('Серия PRO Дверь стеклянная для бани PRO 200*81,5 Бронза'));
        $this->assertNotSame($key('БДК "COOPER" Лайт, бронза, 190х70'), $key('БДК "COOPER" Стандарт, бронза, 190х70'));
        $this->assertNotSame($key('БДК "COOPER" Стандарт, бронза, 190х70'), $key('БДК "COOPER" Стандарт, бронза, 190х70 ВР'));
        $this->assertNotSame($key('БДК "COOPER" Стандарт, графит, 190х70'), $key('БДК "COOPER" Стандарт, графит матовый, 190х70'));
    }

    public function test_the_price_list_workbook_gives_the_cooper_rows_with_cost_and_recommended_retail(): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['ООО "ВТОРОЙ ДОМ"'],
            [null, 'Бренд', 'Фото', 'Наименование', 'Остаток, шт', 'Цена с ндс', 'РРЦ', 'Описание'],
            ['Серия "PRO"'],
            [null, null, null, 'БДК "COOPER" PRO, бронза, 170х71,5                         ЗАКАЗНАЯ', null, 652.53, 848],
            [null, null, null, 'Дверь глухая со стеклом (прямоуг.)700х1700 Ольха', '3 шт', 231.9, 342],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'opt').'.xlsx';
        (new Xlsx($book))->save($path);

        $this->assertSame([['name' => 'БДК "COOPER" PRO, бронза, 170х71,5', 'made_to_order' => true, 'wholesale' => 652.53, 'rrc' => 848.0]],
            CooperWholesalePriceList::parse($path));
        @unlink($path);
    }

    public function test_admin_uploads_the_price_list_on_the_supplier_page_and_the_doors_get_their_cost(): void
    {
        Storage::fake('local');
        Category::query()->create(['name' => 'Двери для бани и сауны', 'slug' => 'dveri-dlya-ban-i-saun', 'is_active' => true]);
        Http::fake(['cooper.by/wp-json/*' => Http::response([
            $this->door(36385, 'Дверь стеклянная для бани PRO 200*81,5 Бронза', 'PRO', 882, '<p>Петли усиленные G60 — 3 шт;</p>'),
        ])]);
        $this->artisan('supplier:sync-cooper-doors --apply --create-new')->assertSuccessful();
        $supplier = Supplier::query()->where('code', 'saunaopt')->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([[null, null, null, 'БДК "COOPER" PRO, бронза, 200х81,5', null, 678.43, 882]]);
        $path = tempnam(sys_get_temp_dir(), 'opt').'.xlsx';
        (new Xlsx($book))->save($path);

        Livewire::actingAs($admin)
            ->test(ViewSupplier::class, ['record' => $supplier->getRouteKey()])
            ->callAction('cooper_wholesale', ['file' => UploadedFile::fake()->createWithContent('opt.xlsx', (string) file_get_contents($path))])
            ->assertHasNoActionErrors();
        @unlink($path);

        $this->assertSame(678.43, (float) SupplierProduct::query()->where('supplier_article', 'cooper-36385')->value('price_byn'));
        $this->assertSame(882.0, (float) Product::query()->where('sku', 'COOPER-36385')->value('price'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncTeploshopCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_cards_creates_biotep_boilers_and_keeps_retail_only_costs_empty(): void
    {
        $gas = Category::query()->firstOrCreate(['slug' => 'gazovye-kotly'], ['name' => 'Газовые котлы', 'parent_id' => 0, 'is_active' => true]);
        Category::query()->firstOrCreate(['slug' => 'kotly-na-pelletah'], ['name' => 'Котлы на пеллетах', 'parent_id' => 0, 'is_active' => true]);
        $vaillant = Brand::query()->create(['name' => 'Vaillant', 'slug' => 'vaillant', 'is_active' => true]);
        $protherm = Brand::query()->create(['name' => 'Protherm', 'slug' => 'protherm', 'is_active' => true]);
        $kospel = Brand::query()->create(['name' => 'Kospel', 'slug' => 'kospel', 'is_active' => true]);
        $turbo = Product::query()->create(['category_id' => $gas->id, 'brand_id' => $vaillant->id, 'is_active' => true, 'price' => 4000, 'name' => 'Газовый котел Vaillant turboTEC pro VUW 242/5-3', 'slug' => 'turbotec-pro-242']);
        $panther = Product::query()->create(['category_id' => $gas->id, 'brand_id' => $protherm->id, 'is_active' => true, 'price' => 3900, 'name' => 'Котел газовый Protherm Пантера 12 KTO', 'slug' => 'pantera-12-kto']);
        $ekco = Product::query()->create(['category_id' => $gas->id, 'brand_id' => $kospel->id, 'is_active' => true, 'price' => 2500, 'name' => 'Электрический котел Kospel EKCO.L3-6.PL', 'slug' => 'ekco-l3-6']);

        $links = SupplierProduct::query()->count();
        $book = new Spreadsheet;
        $book->getActiveSheet()->setTitle('BIOTEP')->fromArray([
            [null, null, null, 'Котлы пеллетные BIOTEP', null, null, null, null, 'опт', 'РРЦ'],
            [null, 'BIOTEP-18', 'Мощность: 4-18 кВт дымоход - Ø80 мм', null, null, 'Модуляция мощностью', null, null, 11685.06, 13700],
        ]);
        $book->createSheet()->setTitle('VAILLANT')->fromArray([
            ['Артикул', 'Наименование', 'Описание', 'ОПТ BYN', 'РРЦ BYN'],
            ['0010015249', 'turboTEC pro VUW 242/5-3', '24 кВт', 2809.17, 3990],
        ]);
        $book->createSheet()->setTitle('PROTHERM')->fromArray([
            ['0010015240', 'Пантера 12КТО', '12 кВт', 0, 3929.42],
        ]);
        $book->createSheet()->setTitle('Kospel котлы')->fromArray([
            ['EKCO.L3'],
            [null, 'EKCO.L3-04/06/08.PL', '04/06/08 кВт', '— Насос Wilo', '2 года', 798.62],
        ]);
        $book->createSheet()->setTitle('Насосы')->fromArray(array_fill(0, 120, [null, null, null, 'X', 'Насос Wester WPE 25-60G', 180, 45, '1"', 6, 3.4, 'чугун', 264.7, 345]));
        $file = tempnam(sys_get_temp_dir(), 'teploshop').'.xlsx';
        (new Xlsx($book))->save($file);

        $this->artisan('supplier:sync-teploshop', ['--file' => $file, '--eur-rate' => '3.5'])
            ->expectsOutputToContain('metric.teploshop.matched_vaillant=1')
            ->assertSuccessful();
        $this->assertSame($links, SupplierProduct::query()->count());

        $this->artisan('supplier:sync-teploshop', ['--file' => $file, '--eur-rate' => '3.5', '--apply' => true, '--create-biotep' => true])
            ->expectsOutputToContain('metric.teploshop.created=1')
            ->assertSuccessful();

        // Vaillant, Protherm and Kospel are bought elsewhere: matched for reference, never linked.
        $this->assertSame(0, $turbo->supplierProducts()->count());
        $this->assertSame(0, $panther->supplierProducts()->count());
        $this->assertSame(0, $ekco->supplierProducts()->count());
        $biotep = Product::query()->where('sku', 'BIOTEP-18')->sole();
        $this->assertSame('Пеллетный котел BIOTEP 18', $biotep->name);
        $this->assertSame(13700.0, (float) $biotep->price);
        $this->assertSame('11685.06', number_format((float) $biotep->supplierProducts()->sole()->price_byn, 2, '.', ''));
    }
}

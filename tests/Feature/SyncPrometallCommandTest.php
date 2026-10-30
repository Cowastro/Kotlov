<?php

namespace Tests\Feature;

use App\Console\Commands\SyncPrometallCommand;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncPrometallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_link_by_ean_and_name_are_on_request_and_set_the_retail_price(): void
    {
        $category = Category::query()->create(['name' => 'Печи для бани', 'slug' => 'prometall-test-stoves', 'is_active' => true]);
        $brand = Brand::query()->create(['name' => 'ProMETALL', 'slug' => 'prometall-test']);
        $byEan = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Ковш для бани', 'slug' => 'kovsh-pm', 'sku' => 'PS-011.900', 'price' => 100, 'is_active' => true]);
        $byName = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Стартовый дымоход 115-136', 'slug' => 'start-pm', 'sku' => 'PS-011.901', 'price' => 100, 'is_active' => true]);
        $suggested = Product::query()->create(['category_id' => $category->id, 'brand_id' => $brand->id, 'name' => 'Печь для бани Атмосфера L Жадеит', 'slug' => 'atm-l-pm', 'sku' => 'PS-011.902', 'price' => 100, 'is_active' => true]);
        $source = DB::table('integration_sources')->insertGetId(['code' => 'onec-test', 'name' => '1С', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('integration_products')->insert(['integration_source_id' => $source, 'product_id' => $byEan->id, 'external_id' => 'x1', 'barcode' => '4650097825114', 'created_at' => now(), 'updated_at' => now()]);

        $file = $this->priceList([
            ['p0068', '4650097825114', 'Ковш для бани', 148, 109],
            ['p0091', '4650097825367', 'Стартовый дымоход 115-136', 129.5, 95],
            ['p0300', '4670165500000', 'Печь банная "Атмосфера L" Жадеит', 5221, 3800],
            ['p0999', '', 'Портал неизвестный', 1000, 700],
        ]);

        $this->artisan('supplier:sync-prometall', ['--file' => $file])
            ->expectsOutputToContain('metric.prometall.ean=1')
            ->expectsOutputToContain('metric.prometall.exact_name=1')
            ->expectsOutputToContain('metric.prometall.suggested=1')
            ->expectsOutputToContain('metric.prometall.unlinked=1')
            ->expectsOutputToContain("audit.prometall.row=p0300:s{$suggested->id}:suggested:")
            ->assertSuccessful();

        $this->artisan('supplier:sync-prometall', ['--file' => $file, '--apply' => true])->assertSuccessful();

        $supplier = Supplier::query()->where('code', SyncPrometallCommand::SUPPLIER_CODE)->firstOrFail();
        $offer = SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'p0068')->firstOrFail();
        $this->assertSame($byEan->id, (int) $offer->product_id);
        $this->assertSame('preorder', $offer->stock_status);
        $this->assertSame(109.0, (float) $offer->price_byn);
        $this->assertSame(148.0, (float) $byEan->fresh()->price);
        $this->assertSame($byName->id, (int) SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'p0091')->value('product_id'));
        $this->assertNull(SupplierProduct::query()->where('supplier_id', $supplier->id)->where('supplier_article', 'p0300')->value('product_id'));
        @unlink($file);
    }

    /** @param list<array{0:string,1:string,2:string,3:float,4:float}> $items */
    private function priceList(array $items): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([[null, null, null, 'Фото', 'Описание']], null, 'A9');
        $sheet->setCellValue('B11', 'Аксессуары для бани');
        $row = 12;
        foreach ($items as [$code, $ean, $name, $rrc, $wholesale]) {
            $sheet->setCellValue('B'.$row, $code);
            $sheet->setCellValueExplicit('C'.$row, $ean, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue('F'.$row, $name);
            $sheet->setCellValue('G'.$row, $name);
            $sheet->setCellValue('O'.$row, $rrc);
            $sheet->setCellValue('Q'.$row, $wholesale);
            $row++;
        }
        $path = tempnam(sys_get_temp_dir(), 'pm').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}

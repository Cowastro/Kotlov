<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\OmenergoPriceList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchiveOmenergoReplacedTest extends TestCase
{
    use RefreshDatabase;

    private ?string $photo = null;

    protected function tearDown(): void
    {
        if ($this->photo && is_file($this->photo)) {
            unlink($this->photo);
        }
        parent::tearDown();
    }

    public function test_archive_is_previewed_and_only_exact_unlinked_replaced_card_is_changed(): void
    {
        Storage::fake('local');
        $f = fopen('php://temp', 'r+');
        fputcsv($f, ['Артикул', 'Категория', 'Наименование каталожное', 'Наименование для высталения счетов', 'Торговая марка', 'Страна', 'Гарантия, м', 'Вес, г', 'Краткие характеристики', 'Комплектация', 'Наличие', 'МРЦ', 'Д2'], escape: '');
        for ($i = 1; $i <= 150; $i++) {
            fputcsv($f, ['Е0101-'.$i, 'однофазные стабилизаторы напряжения', 'Энергия Voltron '.$i.' (HP)', 'Voltron '.$i, 'Энергия', 'Китай', 24, 1000, 'Релейный', 'Коробка', '**', 120, 100], escape: '');
        }
        rewind($f);
        Http::fake([OmenergoPriceList::CSV_URL => Http::response(stream_get_contents($f))]);
        fclose($f);
        $supplier = Supplier::create(['code' => 'omenergo', 'name' => 'ОмЭнерго', 'is_active' => true]);
        $other = Supplier::create(['code' => 'protected-other', 'name' => 'Другой']);
        $brand = Brand::firstOrCreate(['name' => 'Энергия'], ['slug' => 'energy-archive']);
        $category = Category::create(['name' => 'Архив тест', 'slug' => 'omenergo-archive-test']);
        $path = 'img/products/omenergo/omenergo-archive-unit.png';
        $this->photo = public_path($path);
        $this->assertFileDoesNotExist($this->photo);
        if (! is_dir(dirname($this->photo))) {
            mkdir(dirname($this->photo), 0775, true);
        }
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $raw = str_repeat("\0".str_repeat("\0\0\0", 220), 220);
        file_put_contents($this->photo, "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 220, 220, 8, 2, 0, 0, 0)).$chunk('IDAT', gzcompress($raw)).$chunk('IEND', ''));
        $base = ['category_id' => $category->id, 'brand_id' => $brand->id, 'is_active' => true, 'in_stock' => false, 'stock_qty' => null];
        $replacement = Product::create($base + ['name' => 'Энергия Voltron 500 (HP)', 'sku' => 'OMENERGO-ARCHIVE-TEST', 'slug' => 'archive-new', 'price' => 120, 'images' => [$path], 'specs' => array_fill(0, 8, ['key' => 'Параметр', 'value' => 'Значение'])]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $replacement->id, 'supplier_article' => 'ARCHIVE-NEW']);
        $old = Product::create($base + ['name' => 'Стабилизатор напряжения Энергия Voltron-500 (5%)', 'slug' => 'archive-old']);
        $protected = Product::create($base + ['name' => $old->name, 'slug' => 'archive-protected']);
        SupplierProduct::create(['supplier_id' => $other->id, 'product_id' => $protected->id, 'supplier_article' => 'PROTECTED']);
        $different = Product::create($base + ['name' => 'Энергия Voltron Neo 500', 'slug' => 'archive-different']);
        $stock = Product::create(array_merge($base, ['name' => $old->name, 'slug' => 'archive-stock', 'stock_qty' => 1]));
        $this->artisan('supplier:sync-omenergo', ['--archive-replaced' => true, '--dry-run' => true])->assertSuccessful();
        $this->assertFalse($old->fresh()->is_archived);
        $replacement->update(['images' => ['img/products/omenergo/missing-test-photo.png']]);
        $this->artisan('supplier:sync-omenergo', ['--archive-replaced' => true, '--apply' => true])->assertSuccessful();
        $this->assertFalse($old->fresh()->is_archived);
        $replacement->update(['images' => [$path]]);
        $twin = Product::create($base + ['name' => $replacement->name, 'sku' => 'OMENERGO-ARCHIVE-TWIN', 'slug' => 'archive-twin', 'price' => 120, 'images' => [$path], 'specs' => $replacement->specs]);
        SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $twin->id, 'supplier_article' => 'ARCHIVE-TWIN']);
        $this->artisan('supplier:sync-omenergo', ['--archive-replaced' => true, '--apply' => true])->assertSuccessful();
        $this->assertFalse($old->fresh()->is_archived);
        $twin->update(['is_active' => false]);
        $this->artisan('supplier:sync-omenergo', ['--archive-replaced' => true, '--apply' => true])->assertSuccessful();
        $this->assertTrue($old->fresh()->is_archived);
        foreach ([$replacement, $protected, $different, $stock] as $untouched) {
            $this->assertFalse($untouched->fresh()->is_archived);
        }
        $this->assertNotEmpty(Storage::disk('local')->allFiles('omenergo/archive-review'));
    }
}

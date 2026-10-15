<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPurchasePriceSource;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportConfirmedPurchasePricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_preserves_retail_and_stock_and_is_idempotent(): void
    {
        $product = $this->product('Гидравлический разделитель ОГС-Р-2-НР-i', 'hydro');
        $other = $this->product('Гидравлический разделитель ОГС-Р-2-НР (с креплением) Protect', 'protect');
        $options = $this->importOptions(['ОГС-P-2-НР-i:116.22']);
        $before = $product->fresh()->getAttributes();
        $this->artisan('products:import-confirmed-purchase-prices', $options)->assertSuccessful();
        $this->assertDatabaseCount('product_purchase_price_sources', 0);
        $reports = glob(storage_path('app/reports/test-purchase/confirmed_purchase_preview_*.csv'));
        sort($reports);
        $report = fopen(end($reports), 'rb');
        $headers = fgetcsv($report);
        $reportRow = array_combine($headers, fgetcsv($report));
        fclose($report);
        $this->assertSame((string) $product->id, $reportRow['product_id']);
        $this->assertSame('139.46', $reportRow['new_supplier_cost']);
        $this->assertSame('matched', $reportRow['status']);
        $this->artisan('products:import-confirmed-purchase-prices', [...$options, '--apply' => true])->assertSuccessful();
        $source = ProductPurchasePriceSource::query()->sole();
        $this->assertSame($product->id, $source->product_id);
        $this->assertSame(139.46, (float) $source->price_byn);
        $this->assertSame('2026-03-13', $source->document_date->toDateString());
        $this->assertSame('Документ поставщика №УТ-353 от 13.03.2026', $source->documentLabel());
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame(400.0, (float) $other->fresh()->price);
        $wholesalePrice = new \ReflectionMethod(ProductsTable::class, 'wholesalePrice');
        $this->assertSame(139.46, $wholesalePrice->invoke(null, $product->fresh()));
        $this->artisan('products:import-confirmed-purchase-prices', [...$options, '--apply' => true])->assertSuccessful();
        $this->assertDatabaseCount('product_purchase_price_sources', 1);
    }

    public function test_ambiguous_missing_and_variant_matches_block_the_entire_apply(): void
    {
        $this->product('Коллектор ОКС-К-3-5-НГ-i', 'collector');
        $this->product('Гидрострелка ОГС-Р-2-НР Protect', 'one');
        $this->product('Гидрострелка ОГС-Р-2-НР Protect', 'two');
        $options = $this->importOptions(['ОКС-К-3-5-НГ-i:238.81', 'ОГС-P-2-НР~Protect:83.52']);
        $this->artisan('products:import-confirmed-purchase-prices', [...$options, '--apply' => true])->assertFailed();
        $this->assertDatabaseCount('product_purchase_price_sources', 0);
        $this->artisan('products:import-confirmed-purchase-prices', [
            ...$options, '--row' => ['ОГС-P-2-НР-unknown:10'], '--apply' => true,
        ])->assertFailed();
        $this->assertDatabaseCount('product_purchase_price_sources', 0);
    }

    private function importOptions(array $rows): array
    {
        Supplier::query()->firstOrCreate(['code' => 'test-purchase'], ['name' => 'Test supplier', 'is_active' => true]);

        return [
            'supplier' => 'test-purchase', '--document' => 'УТ-353', '--date' => '2026-03-13',
            '--sha256' => str_repeat('a', 64), '--tax' => 'exclusive', '--vat' => '20', '--row' => $rows,
        ];
    }

    private function product(string $name, string $slug): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'hydraulic'], ['name' => 'Гидрострелки']);

        return Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'sku' => $slug,
            'price' => 400, 'currency' => 'BYN', 'is_active' => true, 'is_archived' => false,
            'stock_qty' => 8, 'in_stock' => true,
        ]);
    }
}

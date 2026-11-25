<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Integrations\BaniaOfferFreshness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BaniaOfferFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private SupplierProduct $offer;

    private Supplier $supplier;

    private string $stockFile;

    private string $retailFile;

    private string $title = 'Дверь для сауны DoorWood "Теплая ночь" 190х70 (бронза матовая, коробка листва)';

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::query()->firstOrCreate(['code' => 'bania'], ['name' => 'BANIA', 'is_active' => true]);
        $category = Category::query()->firstOrCreate(['slug' => 'dveri-dlya-ban-i-saun'], ['name' => 'Двери']);
        $this->product = Product::query()->create(['category_id' => $category->id, 'name' => 'Дверь DoorWood ТЕПЛАЯ НОЧЬ бронза матоваря 190 х 70',
            'slug' => 'bania-test-door', 'sku' => 'PS-TEST', 'price' => 296, 'is_active' => true, 'in_stock' => true, 'stock_qty' => 1, 'availability_status' => 'in_stock']);
        $this->offer = SupplierProduct::query()->create(['supplier_id' => $this->supplier->id, 'product_id' => $this->product->id,
            'supplier_article' => 'DW01125', 'supplier_article_normalized' => 'DW01125', 'supplier_name' => $this->title,
            'price' => 246.93, 'price_byn' => 246.93, 'in_stock' => true, 'stock_quantity' => 1, 'stock_status' => 'in_stock',
            'last_synced_at' => now()->subMonths(4), 'last_stock_synced_at' => now()->subMonths(4),
            'raw' => ['google_price_list' => ['name' => $this->title, 'article' => 'DW01125']]]);
        $this->stockFile = storage_path('app/bania-test-stock.xlsx');
        $this->retailFile = storage_path('app/bania-test-retail.xlsx');
        $this->workbooks();
    }

    private function workbooks(string $stock = 'В наличии', float $retail = 296, ?string $title = null): void
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Номенклатура'], [], [], [], [], [$title ?? $this->title, '', 'DW01125', '', '', 246.93, $stock]]);
        (new Xlsx($book))->save($this->stockFile);
        $book->disconnectWorksheets();
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([['Номенклатура'], [], [], [$title ?? $this->title, 'DW01125', 'шт', $retail]]);
        (new Xlsx($book))->save($this->retailFile);
        $book->disconnectWorksheets();
    }

    private function sync(): void
    {
        $code = Artisan::call('supplier:sync-bania-pricelist', ['--apply' => true, '--sync-retail-prices' => true,
            '--price-file' => $this->stockFile, '--retail-price-file' => $this->retailFile]);
        $this->assertSame(0, $code, Artisan::output());
    }

    public function test_actual_legacy_door_updates_and_never_invents_one_piece(): void
    {
        $offersBefore = SupplierProduct::query()->count();
        $productsBefore = Product::query()->count();
        $this->sync();
        $this->assertTrue($this->offer->fresh()->in_stock);
        $this->assertNull($this->offer->fresh()->stock_quantity);
        $this->assertSame('available_without_quantity', $this->offer->fresh()->stock_status);
        $this->assertTrue($this->offer->fresh()->last_stock_synced_at->isToday());
        $this->assertFalse($this->product->fresh()->requires_price_confirmation);
        $this->assertNull($this->product->fresh()->stock_qty);
        $this->assertNull($this->product->fresh()->confirmedStockQuantity());
        $this->assertSame(Product::AVAILABILITY_IN_STOCK, $this->product->fresh()->effectiveAvailabilityStatus());
        $this->assertDatabaseCount('supplier_products', $offersBefore);
        $this->assertDatabaseCount('products', $productsBefore);
    }

    public function test_unchanged_price_and_presence_are_checked_again_the_next_day(): void
    {
        $this->sync();
        $first = $this->offer->fresh()->last_stock_synced_at;
        $this->travel(1)->days();
        $this->sync();
        $this->assertTrue($this->offer->fresh()->last_stock_synced_at->gt($first));
        $this->assertEquals(296, $this->product->fresh()->price);
    }

    public function test_retail_comes_from_retail_file_not_wholesale(): void
    {
        $this->workbooks(retail: 350);
        $this->sync();
        $this->assertEquals(350, $this->product->fresh()->price);
        $this->assertEquals(246.93, $this->offer->fresh()->price_byn);
    }

    public function test_preorder_is_not_present_stock(): void
    {
        $this->workbooks(stock: 'Под заказ');
        $this->sync();
        $this->assertFalse($this->offer->fresh()->in_stock);
        $this->assertEquals(0, $this->offer->fresh()->stock_quantity);
        $this->assertSame(Product::AVAILABILITY_CHECK, $this->product->fresh()->effectiveAvailabilityStatus());
    }

    public function test_stale_only_supplier_is_held_without_erasing_history_or_faking_a_fresh_check(): void
    {
        $date = $this->offer->last_stock_synced_at;
        app(BaniaOfferFreshness::class)->quarantine($this->supplier->id);
        $this->assertFalse($this->offer->fresh()->in_stock);
        $this->assertTrue($this->offer->fresh()->last_stock_synced_at->equalTo($date));
        $this->assertEquals(296, $this->product->fresh()->price);
        $this->assertTrue($this->product->fresh()->requires_price_confirmation);
        $this->assertFalse($this->product->fresh()->hasVerifiedPublicPrice());
        $this->assertFalse($this->product->fresh()->canBeAddedToCart());
    }

    public function test_unmatched_model_stays_on_hold_after_successful_full_sync(): void
    {
        $this->workbooks(title: 'Дверь DoorWood Другая модель 180х60 сатин');
        $this->sync();
        $this->assertTrue($this->product->fresh()->requires_price_confirmation);
        $this->assertFalse($this->offer->fresh()->in_stock);
        $this->assertEquals(296, $this->product->fresh()->price);
    }

    public function test_manual_price_hold_is_not_released_by_the_import(): void
    {
        $this->product->update(['requires_price_confirmation' => true]);
        $this->sync();
        $this->assertTrue($this->product->fresh()->requires_price_confirmation);
    }

    public function test_onec_link_and_other_suppliers_keep_their_price_and_stock(): void
    {
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], ['name' => '1C', 'is_active' => true]);
        IntegrationProduct::query()->create(['integration_source_id' => $source->id, 'external_id' => 'test-key', 'product_id' => $this->product->id,
            'name' => $this->product->name, 'match_status' => 'matched']);
        app(BaniaOfferFreshness::class)->quarantine($this->supplier->id);
        $this->assertFalse($this->product->fresh()->requires_price_confirmation);
        $this->assertTrue($this->product->fresh()->in_stock);
        $this->assertFalse($this->offer->fresh()->in_stock);
    }

    public function test_missing_retail_file_fails_and_does_not_validate_old_stock(): void
    {
        $this->artisan('supplier:sync-bania-pricelist', ['--apply' => true, '--sync-retail-prices' => true,
            '--price-file' => $this->stockFile, '--retail-price-file' => $this->retailFile.'.missing'])->assertFailed();
        $this->assertTrue($this->product->fresh()->requires_price_confirmation);
        $this->assertFalse($this->offer->fresh()->in_stock);
        $this->assertFalse($this->offer->fresh()->last_stock_synced_at->isToday());
    }
}

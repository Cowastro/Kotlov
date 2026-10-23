<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierPriceImport;
use App\Models\SupplierPriceItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProductMapping;
use App\Services\PriceList\ApplyReviewedKarina;
use App\Services\PriceList\KarinaPriceAudit;
use App\Services\Pricing\KarinaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ApplyReviewedKarinaTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $category = Category::create(['name' => 'Audit ovens', 'slug' => 'karina-test-ovens', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::create(['name' => 'KARINA', 'slug' => 'karina-reviewed-test']);
        $product = Product::create(['category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => 'KARINA 6', 'sku' => 'K-6', 'slug' => 'karina-review-6', 'price' => 1600,
            'price_old' => null, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'stock_qty' => 5, 'content' => 'Keep this text', 'images' => ['keep.jpg']]);
        $raw = [
            ['article' => 'K-6', 'name' => 'KARINA 6', 'retail' => 40200],
            ['article' => 'K-9', 'name' => 'KARINA 9', 'retail' => 50000],
            ['article' => 'DUP', 'name' => 'KARINA Stone A', 'retail' => 60000],
            ['article' => 'DUP', 'name' => 'KARINA Stone B', 'retail' => 70000],
        ];
        $rows = [];
        foreach ($raw as $i => $row) {
            $rows[] = $row + KarinaPricing::quote($row['retail'], 0.03646) + ['row' => $i + 17, 'missing_article' => false];
        }
        $rows = (new KarinaPriceAudit)->match($rows, [$product->toArray()]);
        $rows[0]['price_plan'] = ['eligible_for_reviewed_apply' => true, 'product_id' => $product->id];
        return [$product, [
            'schema' => 'karina-audit-v1', 'source_currency' => 'RUB', 'currency_confirmed' => true, 'applied' => false,
            'workbook' => ['sha256' => str_repeat('a', 64)],
            'rate' => ['date' => now()->format('Y-m-d'), 'official_rate' => 3.646, 'scale' => 100, 'byn_per_rub' => 0.03646],
            'products' => [$product->toArray()], 'items' => $rows,
        ]];
    }

    public function test_dry_run_then_apply_and_replay_preserve_identity_stock_and_content(): void
    {
        [$product, $report] = $this->fixture();
        $count = Product::count();
        $service = new ApplyReviewedKarina;
        $preview = $service->run($report);
        $this->assertSame(2, $preview['offers']);
        $this->assertSame(1, $preview['matched']);
        $this->assertSame(2, $preview['quarantined']);
        $this->assertNull(Supplier::where('code', 'karina')->first());
        $this->assertSame(1600.0, (float) $product->fresh()->price);

        $applied = $service->run($report, true);
        $supplier = Supplier::where('code', 'karina')->sole();
        $this->assertSame('RUB', $supplier->currency);
        $this->assertFalse((bool) $supplier->automatic_order_transfer_enabled);
        $this->assertSame($count, Product::count());
        $this->assertSame(1465.69, (float) $product->fresh()->price);
        $this->assertTrue($product->fresh()->in_stock);
        $this->assertSame(5, (int) $product->fresh()->stock_qty);
        $this->assertSame('Keep this text', $product->fresh()->content);
        $this->assertSame(['keep.jpg'], $product->fresh()->images);
        $link = SupplierProduct::where('supplier_id', $supplier->id)->sole();
        $this->assertSame(30150.0, (float) $link->price);
        $this->assertSame(1099.27, (float) $link->price_byn);
        $this->assertFalse($link->in_stock);
        $this->assertSame('unknown', $link->stock_status);
        $this->assertSame(2, SupplierPriceItem::where('import_id', $applied['import_id'])->count());
        $this->assertSame(1, SupplierProductMapping::where('supplier_code', 'karina')->count());

        $replay = $service->run($report, true);
        $this->assertTrue($replay['already_applied']);
        $this->assertSame($applied['import_id'], $replay['import_id']);
        $this->assertSame(1, SupplierPriceImport::where('supplier_id', $supplier->id)->count());
        $this->assertSame(1, SupplierProduct::where('supplier_id', $supplier->id)->count());
    }

    public function test_changed_catalog_refuses_the_batch_without_creating_a_supplier(): void
    {
        [$product, $report] = $this->fixture();
        $product->update(['price' => 1700]);
        try {
            (new ApplyReviewedKarina)->run($report, true);
            $this->fail('Stale catalog should be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Catalog changed', $e->getMessage());
        }
        $this->assertNull(Supplier::where('code', 'karina')->first());
        $this->assertSame(1700.0, (float) $product->fresh()->price);
    }

    public function test_failure_after_batch_creation_rolls_back_every_write(): void
    {
        [$product, $report] = $this->fixture();
        SupplierPriceItem::creating(function (): void {
            throw new RuntimeException('Injected write failure');
        });
        try {
            (new ApplyReviewedKarina)->run($report, true);
            $this->fail('Write failure should roll back.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected write failure', $e->getMessage());
        } finally {
            SupplierPriceItem::flushEventListeners();
        }
        $this->assertNull(Supplier::where('code', 'karina')->first());
        $this->assertSame(1600.0, (float) $product->fresh()->price);
        $this->assertSame(0, SupplierProductMapping::where('supplier_code', 'karina')->count());
    }
}

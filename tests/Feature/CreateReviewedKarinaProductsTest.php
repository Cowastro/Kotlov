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
use App\Services\PriceList\CreateReviewedKarinaProducts;
use App\Services\Pricing\KarinaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CreateReviewedKarinaProductsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $stoves = Category::create(['name' => 'Electric sauna stoves', 'slug' => 'karina-create-stoves', 'parent_id' => 0, 'is_active' => true]);
        $accessories = Category::create(['name' => 'Sauna accessories', 'slug' => 'karina-create-accessories', 'parent_id' => 0, 'is_active' => true]);
        $brand = Brand::create(['name' => 'KARINA', 'slug' => 'karina-create-test']);
        $stoveTemplate = Product::create([
            'category_id' => $stoves->id, 'brand_id' => $brand->id,
            'name' => 'Электрическая печь KARINA Existing', 'sku' => 'EXISTING-STOVE',
            'slug' => 'karina-existing-stove', 'price' => 1000,
            'is_active' => true, 'is_archived' => false, 'in_stock' => false,
        ]);
        $accessoryTemplate = Product::create([
            'category_id' => $accessories->id, 'brand_id' => $brand->id,
            'name' => 'Пульт управления KARINA Existing', 'sku' => 'EXISTING-CONTROL',
            'slug' => 'karina-existing-control', 'price' => 500,
            'is_active' => true, 'is_archived' => false, 'in_stock' => false,
        ]);
        $supplier = Supplier::create([
            'code' => 'karina', 'name' => 'KARINA / ООО «Тепломаркет»',
            'currency' => 'RUB', 'currency_rate' => 0.03646, 'is_active' => true,
        ]);
        $import = SupplierPriceImport::create([
            'supplier_id' => $supplier->id, 'filename' => 'karina-reviewed-'.str_repeat('a', 64).'.xlsx',
            'total_rows' => 2, 'matched' => 0, 'unmatched' => 2, 'updated' => 0, 'skipped' => 2,
            'status' => 'done', 'imported_at' => now(),
        ]);
        $sourceRows = [
            ['row' => 17, 'article' => 'NEW-6', 'name' => 'Электрическая печь KARINA New 6', 'retail_rub' => 40000],
            ['row' => 18, 'article' => 'CTRL-15', 'name' => 'Пульт управления Karina New 15', 'retail_rub' => 20000],
        ];
        $items = [];
        $candidates = [];
        foreach ($sourceRows as $row) {
            $quote = KarinaPricing::quote($row['retail_rub'], 0.03646);
            $item = $row + $quote;
            $items[] = $item;
            $candidates[] = [
                'row' => $row['row'], 'article' => $row['article'], 'name' => $row['name'],
                'retail_rub' => $quote['retail_rub'], 'purchase_rub' => $quote['purchase_rub'],
                'retail_byn' => $quote['retail_byn'], 'purchase_byn' => $quote['purchase_byn'],
                'existing_product_ids' => [], 'blocked_reasons' => [],
                'eligible_for_hidden_creation_review' => true,
            ];
            SupplierPriceItem::create([
                'supplier_id' => $supplier->id, 'import_id' => $import->id,
                'article' => $row['article'], 'name' => $row['name'],
                'price' => $quote['purchase_rub'], 'currency' => 'RUB',
                'currency_rate' => 0.03646, 'price_byn' => $quote['purchase_byn'],
                'raw' => ['karina' => ['retail_rub' => $quote['retail_rub']]],
                'in_stock' => false, 'product_id' => null,
                'match_status' => 'unmatched', 'match_method' => null,
            ]);
        }

        return [$supplier, $import, $stoves, $accessories, [
            'schema' => 'karina-audit-v1', 'source_currency' => 'RUB',
            'currency_confirmed' => true, 'applied' => false,
            'workbook' => ['sha256' => str_repeat('a', 64)],
            'rate' => ['date' => now()->format('Y-m-d'), 'official_rate' => 3.646, 'scale' => 100, 'byn_per_rub' => 0.03646],
            'products' => [$stoveTemplate->toArray(), $accessoryTemplate->toArray()],
            'items' => $items, 'creation_candidates' => $candidates,
        ]];
    }

    public function test_dry_run_creates_nothing_then_apply_creates_hidden_linked_cards_once(): void
    {
        [$supplier, $import, $stoves, $accessories, $report] = $this->fixture();
        $service = new CreateReviewedKarinaProducts;

        $preview = $service->run($report);
        $this->assertSame(2, $preview['planned']);
        $this->assertSame(1, $preview['stoves']);
        $this->assertSame(1, $preview['accessories']);
        $this->assertSame(0, Product::where('created_via', CreateReviewedKarinaProducts::CREATED_VIA)->count());

        $result = $service->run($report, true);
        $this->assertSame(2, $result['created']);
        $created = Product::where('created_via', CreateReviewedKarinaProducts::CREATED_VIA)->orderBy('id')->get();
        $this->assertCount(2, $created);
        $this->assertTrue($created->every(fn (Product $product): bool => ! $product->is_active && ! $product->in_stock));
        $this->assertTrue($created->every(fn (Product $product): bool => $product->requires_price_confirmation));
        $this->assertSame([$stoves->id, $accessories->id], $created->pluck('category_id')->all());
        $this->assertSame([1458.4, 729.2], $created->map(fn (Product $product): float => (float) $product->price)->all());
        $this->assertSame([1093.8, 546.9], $created->map(fn (Product $product): float => (float) $product->manual_purchase_price_byn)->all());
        $this->assertTrue($created->every(fn (Product $product): bool => str_starts_with((string) $product->sku, 'KOTLOV-')));
        $this->assertSame(2, SupplierProduct::where('supplier_id', $supplier->id)->count());
        $this->assertSame(2, SupplierProductMapping::where('supplier_code', 'karina')->count());
        $this->assertSame(2, SupplierPriceItem::where('import_id', $import->id)->where('match_status', 'matched')->count());
        $this->assertSame(2, $import->fresh()->matched);
        $this->assertSame(0, $import->fresh()->unmatched);

        $replay = $service->run($report, true);
        $this->assertTrue($replay['already_applied']);
        $this->assertSame(2, $replay['existing_batch_products']);
        $this->assertSame(2, Product::where('created_via', CreateReviewedKarinaProducts::CREATED_VIA)->count());
    }

    public function test_second_product_failure_rolls_back_products_links_and_import_updates(): void
    {
        [$supplier, $import, , , $report] = $this->fixture();
        $writes = 0;
        Product::creating(function () use (&$writes): void {
            if (++$writes === 2) {
                throw new RuntimeException('Injected product creation failure');
            }
        });

        try {
            (new CreateReviewedKarinaProducts)->run($report, true);
            $this->fail('Write failure should roll back.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected product creation failure', $e->getMessage());
        } finally {
            Product::flushEventListeners();
        }

        $this->assertSame(0, Product::where('created_via', CreateReviewedKarinaProducts::CREATED_VIA)->count());
        $this->assertSame(0, SupplierProductMapping::where('supplier_code', 'karina')->count());
        $this->assertSame(0, SupplierProduct::where('supplier_id', $supplier->id)->count());
        $this->assertSame(0, $import->fresh()->matched);
        $this->assertSame(2, $import->fresh()->unmatched);
        $this->assertSame(2, SupplierPriceItem::where('import_id', $import->id)->where('match_status', 'unmatched')->count());
    }
}

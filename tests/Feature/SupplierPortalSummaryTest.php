<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierPortalSummary;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierPortalSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_only_assigned_supplier_catalogs(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC');
        $supplier = Supplier::query()->create(['code' => 'mine', 'name' => 'Мой поставщик']);
        $other = Supplier::query()->create(['code' => 'other', 'name' => 'Чужой поставщик']);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Тестовая категория',
            'slug' => 'supplier-summary-category',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Связанная карточка',
            'slug' => 'supplier-summary-product',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'M-1',
            'supplier_name' => 'В наличии',
            'product_id' => $productId,
            'price_byn' => 12.50,
            'stock_quantity' => 3,
            'last_synced_at' => $now->subHour(),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'M-2',
            'supplier_name' => 'Требует внимания',
            'price_byn' => 0,
            'stock_quantity' => 0,
            'last_synced_at' => $now->subHours(25),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $other->id,
            'supplier_article' => 'O-1',
            'supplier_name' => 'Чужой товар',
            'price_byn' => 0,
            'stock_quantity' => 99,
            'last_synced_at' => null,
        ]);

        $summary = app(SupplierPortalSummary::class)->forSupplierIds([$supplier->id], $now);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['in_stock']);
        $this->assertSame(1, $summary['unlinked']);
        $this->assertSame(1, $summary['missing_price']);
        $this->assertSame(1, $summary['stale']);
        $this->assertSame('stale', $summary['health']);
        $this->assertTrue($summary['last_synced_at']?->equalTo($now->subHour()));
    }

    public function test_it_distinguishes_empty_never_synced_and_healthy_catalogs(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC');
        $supplier = Supplier::query()->create(['code' => 'mine', 'name' => 'Мой поставщик']);
        $summaryService = app(SupplierPortalSummary::class);

        $this->assertSame('empty', $summaryService->forSupplierIds([$supplier->id], $now)['health']);

        $product = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'M-1',
            'supplier_name' => 'Без времени',
            'price_byn' => 10,
            'stock_quantity' => 1,
        ]);
        $this->assertSame('never', $summaryService->forSupplierIds([$supplier->id], $now)['health']);

        $product->update(['last_synced_at' => $now->subMinutes(10)]);
        $healthy = $summaryService->forSupplierIds([$supplier->id], $now);

        $this->assertSame('healthy', $healthy['health']);
        $this->assertSame(0, $healthy['stale']);
    }
}

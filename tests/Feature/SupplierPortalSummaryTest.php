<?php

namespace Tests\Feature;

use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\SupplierIntegrationSummary;
use App\Services\SupplierPortalSummary;
use App\Services\SupplierProductHealth;
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

    public function test_product_health_and_attention_scopes_explain_supplier_actions(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC');
        $supplier = Supplier::query()->create(['code' => 'mine', 'name' => 'Мой поставщик']);
        $other = Supplier::query()->create(['code' => 'other', 'name' => 'Другой поставщик']);
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Категория проверки состояния',
            'slug' => 'supplier-health-category',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Карточка проверки состояния',
            'slug' => 'supplier-health-product',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $missingPrice = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
            'supplier_article' => 'NO-PRICE',
            'price_byn' => 0,
            'stock_quantity' => 2,
            'last_synced_at' => $now->subHour(),
        ]);
        $unlinked = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'UNLINKED',
            'price_byn' => 20,
            'stock_quantity' => 2,
            'last_synced_at' => $now->subHour(),
        ]);
        $stale = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
            'supplier_article' => 'STALE',
            'price_byn' => 30,
            'stock_quantity' => 2,
            'last_synced_at' => $now->subHours(25),
        ]);
        $outOfStock = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
            'supplier_article' => 'OUT',
            'price_byn' => 40,
            'stock_quantity' => 0,
            'last_synced_at' => $now->subHour(),
        ]);
        $healthy = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
            'supplier_article' => 'READY',
            'price_byn' => 50,
            'stock_quantity' => 4,
            'last_synced_at' => $now->subHour(),
        ]);
        $flagOnly = SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $productId,
            'supplier_article' => 'FLAG-ONLY',
            'price_byn' => 60,
            'in_stock' => true,
            'stock_quantity' => null,
            'last_synced_at' => $now->subHour(),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $other->id,
            'supplier_article' => 'FOREIGN',
            'price_byn' => 0,
            'last_synced_at' => null,
        ]);

        $attentionIds = SupplierProduct::query()
            ->forSupplierIds([$supplier->id])
            ->needsAttention($now)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->assertSame([$missingPrice->id, $unlinked->id, $stale->id], $attentionIds);
        $this->assertSame([$stale->id], SupplierProduct::query()
            ->forSupplierIds([$supplier->id])
            ->stale($now)
            ->pluck('id')
            ->all());

        $health = app(SupplierProductHealth::class);
        $this->assertSame('missing_price', $health->describe($missingPrice, $now)['key']);
        $this->assertSame('unlinked', $health->describe($unlinked, $now)['key']);
        $this->assertSame('stale', $health->describe($stale, $now)['key']);
        $this->assertSame('out_of_stock', $health->describe($outOfStock, $now)['key']);
        $this->assertSame('healthy', $health->describe($healthy, $now)['key']);
        $this->assertSame('healthy', $health->describe($flagOnly, $now)['key']);
        $this->assertTrue(SupplierProduct::query()->available()->whereKey($flagOnly->id)->exists());
    }

    public function test_integration_summary_is_strictly_scoped_and_reports_exchange_health(): void
    {
        $now = CarbonImmutable::parse('2026-10-10 12:00:00', 'UTC');
        $supplier = Supplier::query()->create(['code' => 'portal-source', 'name' => 'Мой источник']);
        $other = Supplier::query()->create(['code' => 'foreign-source', 'name' => 'Чужой источник']);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'portal-commerce-ml',
            'name' => 'Моя 1С',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $foreignSource = IntegrationSource::query()->create([
            'supplier_id' => $other->id,
            'code' => 'foreign-commerce-ml',
            'name' => 'Чужая 1С',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);

        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => $now->subMinutes(2),
            'finished_at' => $now->subMinute(),
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $foreignSource->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'failed',
            'started_at' => $now,
            'finished_at' => $now,
        ]);
        $ownProduct = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'mine-ready',
            'name' => 'Моя позиция',
            'price' => 25,
            'stock_quantity' => 4,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'mine-missing-price',
            'name' => 'Моя позиция без цены',
            'price' => 0,
            'stock_quantity' => 1,
        ]);
        $foreignProduct = IntegrationProduct::query()->create([
            'integration_source_id' => $foreignSource->id,
            'external_id' => 'foreign',
            'name' => 'Чужая позиция',
            'price' => 0,
            'stock_quantity' => 99,
        ]);
        IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'mine-issue',
            'type' => 'product_missing_price',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Нет цены',
            'first_detected_at' => $now,
            'last_detected_at' => $now,
        ]);
        IntegrationIssue::query()->create([
            'integration_source_id' => $foreignSource->id,
            'fingerprint' => 'foreign-issue',
            'type' => 'integration_catalog_stale',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Чужая ошибка',
            'first_detected_at' => $now,
            'last_detected_at' => $now,
        ]);

        $ownOrder = Order::query()->create([
            'number' => 'ORD-SUMMARY-OWN',
            'customer_name' => 'Свой покупатель',
            'customer_phone' => '+375290000001',
            'status' => 'new',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 25,
            'total' => 25,
        ]);
        OrderItem::query()->create([
            'order_id' => $ownOrder->id,
            'integration_product_id' => $ownProduct->id,
            'product_name' => 'Моя позиция',
            'price' => 25,
            'quantity' => 1,
            'total' => 25,
        ]);
        OrderIntegrationDelivery::query()->create([
            'order_id' => $ownOrder->id,
            'integration_source_id' => $source->id,
            'status' => OrderIntegrationDelivery::STATUS_PENDING,
        ]);

        $foreignOrder = Order::query()->create([
            'number' => 'ORD-SUMMARY-FOREIGN',
            'customer_name' => 'Чужой покупатель',
            'customer_phone' => '+375290000002',
            'status' => 'new',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 50,
            'total' => 50,
        ]);
        OrderItem::query()->create([
            'order_id' => $foreignOrder->id,
            'integration_product_id' => $foreignProduct->id,
            'product_name' => 'Чужая позиция',
            'price' => 50,
            'quantity' => 1,
            'total' => 50,
        ]);
        OrderIntegrationDelivery::query()->create([
            'order_id' => $foreignOrder->id,
            'integration_source_id' => $foreignSource->id,
            'status' => OrderIntegrationDelivery::STATUS_FAILED,
        ]);

        $summary = app(SupplierIntegrationSummary::class)->forSupplierIds([$supplier->id], $now);

        $this->assertSame(1, $summary['source_count']);
        $this->assertSame(1, $summary['active_source_count']);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(2, $summary['in_stock']);
        $this->assertSame(2, $summary['unlinked']);
        $this->assertSame(1, $summary['missing_price']);
        $this->assertSame(1, $summary['open_issues']);
        $this->assertSame(1, $summary['active_orders']);
        $this->assertSame(1, $summary['pending_order_deliveries']);
        $this->assertSame(0, $summary['awaiting_order_responses']);
        $this->assertSame(0, $summary['failed_order_deliveries']);
        $this->assertSame('healthy', $summary['health']);
        $this->assertTrue($summary['last_success_at']?->equalTo($now->subMinute()));
        $this->assertSame([$source->id], $summary['sources']->pluck('id')->all());
    }
}

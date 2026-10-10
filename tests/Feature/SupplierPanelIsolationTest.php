<?php

namespace Tests\Feature;

use App\Filament\Supplier\Resources\IntegrationExchangeRuns\IntegrationExchangeRunResource;
use App\Filament\Supplier\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Filament\Supplier\Resources\IntegrationProducts\IntegrationProductResource;
use App\Filament\Supplier\Resources\Orders\OrderResource as SupplierOrderResource;
use App\Filament\Supplier\Resources\SupplierProducts\SupplierProductResource;
use App\Filament\Supplier\Resources\SupplierSyncChanges\SupplierSyncChangeResource;
use App\Filament\Supplier\Widgets\SupplierIntegrationOverview;
use App\Models\IntegrationExchangeRun;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierSyncChange;
use App\Models\SupplierSyncRun;
use App\Models\User;
use App\Services\Integrations\SupplierIntegrationIssueAdvisor;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierPanelIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_assigned_supplier_user_can_access_supplier_panel(): void
    {
        $supplier = Supplier::query()->create([
            'code' => 'supplier-a',
            'name' => 'Поставщик А',
        ]);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $panel = Panel::make()->id('supplier');

        $this->assertFalse($user->canAccessPanel($panel));

        $user->suppliers()->attach($supplier);
        $this->assertTrue($user->canAccessPanel($panel));

        $supplier->update(['is_active' => false]);
        $this->assertFalse($user->canAccessPanel($panel));
    }

    public function test_supplier_product_resource_is_strictly_scoped_to_assigned_suppliers(): void
    {
        $supplierA = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $supplierB = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplierA);

        $visible = SupplierProduct::query()->create([
            'supplier_id' => $supplierA->id,
            'supplier_article' => 'A-1',
            'supplier_name' => 'Доступный товар',
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplierB->id,
            'supplier_article' => 'B-1',
            'supplier_name' => 'Чужой товар',
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], SupplierProductResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_supplier_orders_expose_only_assigned_supplier_lines_and_subtotal(): void
    {
        $supplierA = Supplier::query()->create(['code' => 'orders-a', 'name' => 'Поставщик А']);
        $supplierB = Supplier::query()->create(['code' => 'orders-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplierA);

        $sourceA = IntegrationSource::query()->create([
            'supplier_id' => $supplierA->id,
            'code' => 'orders-source-a',
            'name' => '1С поставщика А',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $sourceB = IntegrationSource::query()->create([
            'supplier_id' => $supplierB->id,
            'code' => 'orders-source-b',
            'name' => '1С поставщика Б',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $productA = IntegrationProduct::query()->create([
            'integration_source_id' => $sourceA->id,
            'external_id' => 'order-product-a',
            'name' => 'Позиция поставщика А',
        ]);
        $productB = IntegrationProduct::query()->create([
            'integration_source_id' => $sourceB->id,
            'external_id' => 'order-product-b',
            'name' => 'Позиция поставщика Б',
        ]);

        $mixedOrder = Order::query()->create([
            'number' => 'ORD-SUPPLIER-MIXED',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375290000000',
            'status' => 'new',
            'delivery_type' => 'pickup',
            'delivery_city' => 'Минск',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 124,
            'total' => 124,
        ]);
        OrderItem::query()->create([
            'order_id' => $mixedOrder->id,
            'product_name' => 'Свой товар в смешанном заказе',
            'product_sku' => 'OWN-ORDER-LINE',
            'price' => 12,
            'quantity' => 2,
            'total' => 24,
            'pricing_type' => 'b2b',
            'price_tax_mode' => 'inclusive',
            'integration_product_id' => $productA->id,
        ]);
        OrderItem::query()->create([
            'order_id' => $mixedOrder->id,
            'product_name' => 'Чужой товар в смешанном заказе',
            'product_sku' => 'FOREIGN-ORDER-LINE',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
            'pricing_type' => 'b2b',
            'price_tax_mode' => 'inclusive',
            'integration_product_id' => $productB->id,
        ]);
        OrderIntegrationDelivery::query()->create([
            'order_id' => $mixedOrder->id,
            'integration_source_id' => $sourceA->id,
            'status' => OrderIntegrationDelivery::STATUS_ACKNOWLEDGED,
            'external_id' => 'supplier-a-order',
            'remote_status' => 'Принят поставщиком А',
            'exported_at' => now()->subMinute(),
            'status_received_at' => now(),
        ]);
        OrderIntegrationDelivery::query()->create([
            'order_id' => $mixedOrder->id,
            'integration_source_id' => $sourceB->id,
            'status' => OrderIntegrationDelivery::STATUS_SENT,
            'external_id' => 'supplier-b-order',
            'remote_status' => 'Собирает поставщик Б',
            'exported_at' => now(),
        ]);

        $foreignOrder = Order::query()->create([
            'number' => 'ORD-SUPPLIER-FOREIGN',
            'customer_name' => 'Другой покупатель',
            'customer_phone' => '+375291111111',
            'status' => 'new',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
        OrderItem::query()->create([
            'order_id' => $foreignOrder->id,
            'product_name' => 'Товар только чужого поставщика',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
            'pricing_type' => 'b2b',
            'price_tax_mode' => 'inclusive',
            'integration_product_id' => $productB->id,
        ]);

        $this->actingAs($user);

        $visible = SupplierOrderResource::getEloquentQuery()->get();

        $this->assertSame([$mixedOrder->id], $visible->pluck('id')->all());
        $this->assertSame(1, $visible->first()->supplier_items_count);
        $this->assertSame(24.0, (float) $visible->first()->supplier_subtotal);
        $this->assertSame(['Свой товар в смешанном заказе'], $visible->first()->items->pluck('product_name')->all());
        $this->assertSame([$sourceA->id], $visible->first()->integrationDeliveries->pluck('integration_source_id')->all());

        $this->get(SupplierOrderResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('ORD-SUPPLIER-MIXED')
            ->assertDontSeeText('ORD-SUPPLIER-FOREIGN');

        $this->get(SupplierOrderResource::getUrl('view', ['record' => $mixedOrder], panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('Свой товар в смешанном заказе')
            ->assertSeeText('24,00 BYN')
            ->assertSeeText('Принят поставщиком А')
            ->assertDontSeeText('Чужой товар в смешанном заказе')
            ->assertDontSeeText('FOREIGN-ORDER-LINE')
            ->assertDontSeeText('Собирает поставщик Б');

        $this->get(SupplierOrderResource::getUrl('view', ['record' => $foreignOrder], panel: 'supplier'))
            ->assertNotFound();
    }

    public function test_supplier_panel_routes_are_registered(): void
    {
        $this->get('/supplier/login')->assertOk();
    }

    public function test_supplier_product_page_shows_actionable_tabs_and_only_own_products(): void
    {
        $supplier = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $otherSupplier = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);

        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'supplier_article' => 'A-ACTION',
            'supplier_name' => 'Моя позиция без цены',
            'price_byn' => 0,
            'stock_quantity' => 2,
            'last_synced_at' => now(),
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $otherSupplier->id,
            'supplier_article' => 'B-HIDDEN',
            'supplier_name' => 'Чужая позиция без цены',
            'price_byn' => 0,
            'stock_quantity' => 2,
            'last_synced_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(SupplierProductResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSee('Требуют внимания')
            ->assertSee('Не привязаны')
            ->assertSee('Устарели')
            ->assertSee('Моя позиция без цены')
            ->assertSee('Без цены')
            ->assertDontSee('Чужая позиция без цены');
    }

    public function test_supplier_change_history_is_strictly_scoped_to_assigned_suppliers(): void
    {
        $supplierA = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $supplierB = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplierA);
        $run = SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-example',
            'status' => 'success',
            'started_at' => now(),
        ]);

        $visible = SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplierA->id,
            'supplier_name' => $supplierA->name,
            'supplier_article' => 'A-1',
            'product_name' => 'Доступное изменение',
            'change_flags' => ['supplier_price'],
            'supplier_price_before' => 10,
            'supplier_price_after' => 12,
            'created_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplierB->id,
            'supplier_name' => $supplierB->name,
            'supplier_article' => 'B-1',
            'product_name' => 'Чужое изменение',
            'change_flags' => ['stock_quantity'],
            'stock_quantity_before' => 2,
            'stock_quantity_after' => 9,
            'created_at' => now(),
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], SupplierSyncChangeResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_supplier_can_open_its_change_history_page(): void
    {
        $supplier = Supplier::query()->create(['code' => 'supplier-a', 'name' => 'Поставщик А']);
        $otherSupplier = Supplier::query()->create(['code' => 'supplier-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);
        $run = SupplierSyncRun::query()->create([
            'command' => 'supplier:sync-example',
            'status' => 'success',
            'started_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'supplier_article' => 'A-1',
            'product_name' => 'Мой изменённый товар',
            'change_flags' => ['supplier_price', 'stock_quantity'],
            'supplier_price_before' => 10,
            'supplier_price_after' => 12,
            'stock_quantity_before' => 2,
            'stock_quantity_after' => 3,
            'created_at' => now(),
        ]);
        SupplierSyncChange::query()->create([
            'supplier_sync_run_id' => $run->id,
            'supplier_id' => $otherSupplier->id,
            'supplier_name' => $otherSupplier->name,
            'supplier_article' => 'B-1',
            'product_name' => 'Чужой изменённый товар',
            'change_flags' => ['supplier_price'],
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(SupplierSyncChangeResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSee('История изменений')
            ->assertSee('Мой изменённый товар')
            ->assertDontSee('Чужой изменённый товар');
    }

    public function test_supplier_integration_catalog_is_scoped_to_explicitly_assigned_sources(): void
    {
        $supplier = Supplier::query()->create(['code' => 'supplier-integration-a', 'name' => 'Поставщик А']);
        $otherSupplier = Supplier::query()->create(['code' => 'supplier-integration-b', 'name' => 'Поставщик Б']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'supplier-a-onec',
            'name' => '1С поставщика А',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $otherSource = IntegrationSource::query()->create([
            'supplier_id' => $otherSupplier->id,
            'code' => 'supplier-b-onec',
            'name' => '1С поставщика Б',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $visible = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'visible-integration-item',
            'name' => 'Мой товар из 1С',
            'price' => 10,
            'stock_quantity' => 3,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $otherSource->id,
            'external_id' => 'hidden-integration-item',
            'name' => 'Чужой товар из 1С',
            'price' => 10,
            'stock_quantity' => 3,
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], IntegrationProductResource::getEloquentQuery()->pluck('id')->all());

        $this->get(IntegrationProductResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('Товары из 1С / API')
            ->assertSeeText('Мой товар из 1С')
            ->assertSeeText('Не найден')
            ->assertDontSeeText('Чужой товар из 1С');
    }

    public function test_supplier_dashboard_shows_only_its_integration_operational_summary(): void
    {
        $supplier = Supplier::query()->create(['code' => 'dashboard-supplier', 'name' => 'Поставщик кабинета']);
        $otherSupplier = Supplier::query()->create(['code' => 'dashboard-foreign', 'name' => 'Чужой поставщик']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'dashboard-onec',
            'name' => 'Моя интеграция',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $foreignSource = IntegrationSource::query()->create([
            'supplier_id' => $otherSupplier->id,
            'code' => 'dashboard-foreign-onec',
            'name' => 'Чужая интеграция',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'dashboard-own-product',
            'price' => 15,
            'stock_quantity' => 2,
        ]);
        IntegrationProduct::query()->create([
            'integration_source_id' => $foreignSource->id,
            'external_id' => 'dashboard-foreign-product',
            'price' => 0,
            'stock_quantity' => 100,
        ]);

        $this->actingAs($user)
            ->get('/supplier')
            ->assertOk()
            ->assertSeeText('Сводка поставщика')
            ->assertSeeText('Товары из 1С / API');

        Livewire::actingAs($user)
            ->test(SupplierIntegrationOverview::class)
            ->assertSeeText('Заказы')
            ->assertSeeText('К передаче: 0 · ждут ответа: 0 · ошибок: 0')
            ->assertSeeText('Товары из интеграций')
            ->assertSeeText('Нет успешного цикла')
            ->assertSeeText('В наличии: 1')
            ->assertSeeText('Без цены: 0');
    }

    public function test_supplier_exchange_journal_is_strictly_scoped_to_assigned_sources(): void
    {
        [$user, $source, $foreignSource] = $this->integrationAccessFixture();
        $visible = IntegrationExchangeRun::query()->create([
            'integration_source_id' => $source->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
        IntegrationExchangeRun::query()->create([
            'integration_source_id' => $foreignSource->id,
            'direction' => 'inbound',
            'operation' => 'catalog',
            'status' => 'failed',
            'started_at' => now(),
            'error_message' => 'Чужая ошибка обмена',
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], IntegrationExchangeRunResource::getEloquentQuery()->pluck('id')->all());

        $this->get(IntegrationExchangeRunResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('История получения каталога')
            ->assertSeeText('Каталог, цены и остатки')
            ->assertDontSeeText('Чужая ошибка обмена');
    }

    public function test_supplier_issue_queue_is_read_only_and_strictly_scoped_to_assigned_sources(): void
    {
        [$user, $source, $foreignSource] = $this->integrationAccessFixture();
        $visible = IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'supplier-visible-issue',
            'type' => 'integration_catalog_stale',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Мой обмен остановился',
            'message' => 'Каталог давно не обновлялся.',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
        IntegrationIssue::query()->create([
            'integration_source_id' => $foreignSource->id,
            'fingerprint' => 'supplier-hidden-issue',
            'type' => 'integration_catalog_stale',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Чужая проблема',
            'message' => 'Поставщик не должен это увидеть.',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($user);

        $this->assertSame([$visible->id], IntegrationIssueResource::getEloquentQuery()->pluck('id')->all());
        $this->assertFalse(IntegrationIssueResource::canEdit($visible));
        $this->assertFalse(IntegrationIssueResource::canDelete($visible));
        $this->assertSame('Поставщик', app(SupplierIntegrationIssueAdvisor::class)->advise($visible)['owner']);

        IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'supplier-kotlov-owned-issue',
            'type' => 'product_missing_category',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Нужно назначить категорию',
            'context' => ['missing_category' => true],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
        IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'supplier-shared-issue',
            'type' => 'product_attention',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Нет цены и привязки',
            'context' => [
                'missing_price' => true,
                'unmatched' => true,
                'missing_category' => true,
            ],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->get(IntegrationIssueResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('Мой обмен остановился')
            ->assertSeeText('Следующий шаг')
            ->assertSeeText('Кто исправляет')
            ->assertSeeText('Поставщик')
            ->assertSeeText('KOTLOV')
            ->assertSeeText('Совместно')
            ->assertSeeText('Запустить обмен из 1С / API')
            ->assertSeeText('Назначить категорию сайта')
            ->assertSeeText('Передать цену и подготовить привязку')
            ->assertDontSeeText('Чужая проблема');
    }

    public function test_supplier_issue_advice_assigns_the_next_step_to_the_correct_party_without_mutation(): void
    {
        $advisor = app(SupplierIntegrationIssueAdvisor::class);
        $cases = [
            ['integration_catalog_stale', [], 'Поставщик'],
            ['catalog_all_stock_positive', ['warehouse_label' => 'Основной'], 'Поставщик'],
            ['product_missing_price', ['missing_price' => true], 'Поставщик'],
            ['product_unmatched', ['unmatched' => true], 'KOTLOV'],
            ['product_attention', ['missing_price' => true, 'unmatched' => true], 'Совместно'],
            ['order_status_conflict', [], 'Совместно'],
        ];

        foreach ($cases as [$type, $context, $owner]) {
            $issue = new IntegrationIssue([
                'type' => $type,
                'context' => $context,
            ]);
            $advice = $advisor->advise($issue);

            $this->assertSame($owner, $advice['owner']);
            $this->assertNotSame('', $advice['title']);
            $this->assertNotEmpty($advice['steps']);
            $this->assertFalse($issue->exists);
        }
    }

    /** @return array{User, IntegrationSource, IntegrationSource} */
    private function integrationAccessFixture(): array
    {
        $supplier = Supplier::query()->create(['code' => 'fixture-supplier', 'name' => 'Мой поставщик']);
        $otherSupplier = Supplier::query()->create(['code' => 'fixture-foreign', 'name' => 'Чужой поставщик']);
        $user = User::factory()->create(['role' => 'supplier', 'is_active' => true]);
        $user->suppliers()->attach($supplier);
        $source = IntegrationSource::query()->create([
            'supplier_id' => $supplier->id,
            'code' => 'fixture-own-source',
            'name' => 'Мой источник',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);
        $foreignSource = IntegrationSource::query()->create([
            'supplier_id' => $otherSupplier->id,
            'code' => 'fixture-foreign-source',
            'name' => 'Чужой источник',
            'driver' => 'commerceml',
            'is_active' => true,
        ]);

        return [$user, $source, $foreignSource];
    }
}

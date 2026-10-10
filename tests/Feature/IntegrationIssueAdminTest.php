<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Filament\Resources\IntegrationIssues\Pages\ListIntegrationIssues;
use App\Models\Category;
use App\Models\IntegrationIssue;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Integrations\IntegrationOrderStatusMapper;
use App\Services\Integrations\IntegrationProductIssueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationIssueAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_queue_shows_contextual_next_action_without_opening_a_modal(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'issue-ui-source',
            'name' => 'Тестовая 1С',
        ]);

        $issue = IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'fingerprint' => 'issue-ui-stale',
            'type' => 'integration_stale',
            'severity' => 'danger',
            'status' => 'open',
            'title' => 'Нет свежего обмена с Тестовая 1С',
            'message' => 'Последний успешный обмен был слишком давно.',
            'context' => ['stale_after_minutes' => 15],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(IntegrationIssueResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Следующий шаг')
            ->assertSeeText('Восстановить автоматический обмен')
            ->assertSeeText('Откройте регламентное задание обмена на стороне 1С.')
            ->assertSeeText('Что делать')
            ->assertSeeText('ИИ-разбор')
            ->assertSeeText('Открыть');
    }

    public function test_unknown_order_status_offers_a_direct_safe_mapping_action(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
        $source = IntegrationSource::query()->create([
            'code' => 'unknown-status-ui-source',
            'name' => '1С поставщика',
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-UNKNOWN-UI',
            'status' => 'processing',
            'customer_name' => 'Покупатель',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ]);
        $issue = IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'order_id' => $order->id,
            'fingerprint' => 'unknown-status-ui',
            'type' => 'order_status_unknown',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Не распознан статус из 1С',
            'message' => 'Статус «Передан логисту».',
            'context' => [
                'unknown_status' => 'Передан логисту',
                'unknown_payment_status' => null,
            ],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ListIntegrationIssues::class)
            ->assertTableActionExists('mapUnknownStatus', record: $issue)
            ->callTableAction('mapUnknownStatus', $issue, ['order_target' => 'shipped'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(
            'shipped',
            app(IntegrationOrderStatusMapper::class)->orderStatus('Передан логисту', $source->fresh()),
        );
        $this->assertSame('processing', $order->fresh()->status);

        $this
            ->get(IntegrationIssueResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Добавить правило');
    }

    public function test_admin_can_link_an_unmatched_product_directly_from_issue_queue(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $source = IntegrationSource::query()->create([
            'code' => 'product-issue-source',
            'name' => '1С поставщика',
        ]);
        $category = Category::query()->create([
            'name' => 'Дымоходы',
            'slug' => 'issue-link-chimneys',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Труба дымохода D150',
            'slug' => 'issue-link-pipe-d150',
            'sku' => 'KOTLOV-D150',
            'price' => 150,
            'stock_qty' => 8,
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'onec-pipe-d150',
            'external_sku' => 'SUP-D150',
            'name' => 'Труба нерж. D150',
            'price' => 100,
            'stock_quantity' => 3,
            'match_status' => 'unmatched',
        ]);
        $issue = IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'integration_product_id' => $item->id,
            'fingerprint' => 'product-issue-link-directly',
            'type' => 'product_attention',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Товар требует привязки',
            'message' => 'Карточка сайта не найдена.',
            'context' => ['unmatched' => true, 'missing_price' => false],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(ListIntegrationIssues::class)
            ->set('activeTab', 'ready-to-link')
            ->assertTableActionExists('linkExistingProduct', record: $issue)
            ->callTableAction('linkExistingProduct', $issue, ['product_id' => $product->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('integration_products', [
            'id' => $item->id,
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => 'manual_search',
        ]);
        $this->assertDatabaseHas('integration_issues', [
            'id' => $issue->id,
            'status' => 'resolved',
        ]);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Труба дымохода D150',
            'price' => 150,
            'stock_qty' => 8,
        ]);
        $this->assertDatabaseHas('supplier_product_mappings', [
            'product_id' => $product->id,
            'supplier_article' => 'SUP-D150',
            'confidence' => 'manual',
        ]);
    }

    public function test_stale_product_issue_cannot_replace_an_existing_link(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'stale-product-issue-source',
            'name' => '1С поставщика',
        ]);
        $category = Category::query()->create([
            'name' => 'Категория защиты привязки',
            'slug' => 'stale-link-protection',
            'parent_id' => 0,
        ]);
        $original = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Уже привязанная карточка',
            'slug' => 'already-linked-product',
            'sku' => 'ALREADY-LINKED',
        ]);
        $replacement = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Другая карточка',
            'slug' => 'replacement-product',
            'sku' => 'REPLACEMENT',
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'product_id' => $original->id,
            'external_id' => 'already-linked-external',
            'name' => 'Уже обработанный товар',
            'stock_quantity' => 1,
            'match_status' => 'matched',
        ]);
        $issue = IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'integration_product_id' => $item->id,
            'fingerprint' => 'stale-product-issue',
            'type' => 'product_attention',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Устаревшая задача',
            'message' => 'Товар уже был обработан другим оператором.',
            'context' => ['unmatched' => true, 'missing_price' => false],
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $result = app(IntegrationProductIssueResolver::class)
            ->linkExistingProduct($issue, $replacement->id);

        $this->assertNull($result);
        $this->assertSame($original->id, $item->fresh()->product_id);
        $this->assertSame('open', $issue->fresh()->status);
    }
}

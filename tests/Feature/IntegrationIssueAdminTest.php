<?php

namespace Tests\Feature;

use App\Filament\Resources\IntegrationIssues\IntegrationIssueResource;
use App\Filament\Resources\IntegrationIssues\Pages\ListIntegrationIssues;
use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\User;
use App\Services\Integrations\IntegrationOrderStatusMapper;
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
}

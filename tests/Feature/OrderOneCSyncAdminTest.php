<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\IntegrationIssue;
use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOneCSyncAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_list_distinguishes_delivery_progress_from_actionable_onec_problems(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $waiting = $this->createOrder('ORD-1C-WAITING');
        $delayed = $this->createOrder('ORD-1C-DELAYED');
        $sent = $this->createOrder('ORD-1C-SENT', ['onec_exported_at' => now()->subMinutes(2)]);
        $noResponse = $this->createOrder('ORD-1C-NO-RESPONSE', ['onec_exported_at' => now()->subMinutes(20)]);
        $confirmed = $this->createOrder('ORD-1C-CONFIRMED', [
            'onec_exported_at' => now()->subMinutes(10),
            'onec_status' => 'Подтверждён',
            'onec_status_received_at' => now()->subMinute(),
        ]);
        $conflict = $this->createOrder('ORD-1C-CONFLICT', [
            'onec_exported_at' => now()->subMinutes(10),
            'onec_status' => 'Отправлен',
            'onec_status_received_at' => now()->subMinute(),
        ]);
        $unknown = $this->createOrder('ORD-1C-UNKNOWN', [
            'onec_exported_at' => now()->subMinutes(10),
        ]);

        $this->createIssue($delayed, 'order_not_exported', 'Заказ не передан в 1С', 'Регламентный обмен задержан.');
        $this->createIssue($noResponse, 'order_no_1c_response', 'Нет ответа 1С', 'Статус не вернулся вовремя.');
        $this->createIssue($conflict, 'order_status_conflict', 'Конфликт статусов', '1С пытается вернуть более ранний статус.');
        $this->createIssue($unknown, 'order_status_unknown', 'Неизвестный статус', 'Получено значение «Комплектуется».');

        $this->assertSame('waiting', $waiting->onecSyncState());
        $this->assertSame('delayed', $delayed->onecSyncState());
        $this->assertSame('sent', $sent->onecSyncState());
        $this->assertSame('no_response', $noResponse->onecSyncState());
        $this->assertSame('confirmed', $confirmed->onecSyncState());
        $this->assertSame('conflict', $conflict->onecSyncState());
        $this->assertSame('unknown', $unknown->onecSyncState());

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Ожидает передачи')
            ->assertSeeText('Передача задержана')
            ->assertSeeText('Передан')
            ->assertSeeText('Нет ответа 1С')
            ->assertSeeText('Ответ получен')
            ->assertSeeText('Конфликт статусов')
            ->assertSeeText('Неизвестный статус')
            ->assertSeeText('Регламентный обмен задержан.')
            ->assertSeeText('1С пытается вернуть более ранний статус.')
            ->assertSeeText('Есть открытая проблема');
    }

    public function test_obsolete_delay_issue_does_not_override_newer_exchange_evidence(): void
    {
        $exported = $this->createOrder('ORD-1C-STALE-EXPORT-ISSUE', [
            'onec_exported_at' => now(),
        ]);
        $confirmed = $this->createOrder('ORD-1C-STALE-RESPONSE-ISSUE', [
            'onec_exported_at' => now()->subMinute(),
            'onec_status' => 'Подтверждён',
            'onec_status_received_at' => now(),
        ]);
        $this->createIssue($exported, 'order_not_exported', 'Старое предупреждение', 'Уже не актуально.');
        $this->createIssue($confirmed, 'order_no_1c_response', 'Старое предупреждение', 'Уже не актуально.');

        $this->assertSame('sent', $exported->onecSyncState());
        $this->assertSame('confirmed', $confirmed->onecSyncState());
    }

    public function test_order_state_uses_the_specific_source_delivery_and_hides_recovered_issue(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'supplier-order-state',
            'name' => '1С поставщика',
        ]);
        $order = $this->createOrder('ORD-SOURCE-STATE');
        $delivery = OrderIntegrationDelivery::query()->create([
            'order_id' => $order->id,
            'integration_source_id' => $source->id,
            'status' => OrderIntegrationDelivery::STATUS_SENT,
            'external_id' => 'supplier-order-state-id',
            'exported_at' => now()->subMinutes(20),
        ]);
        IntegrationIssue::query()->create([
            'integration_source_id' => $source->id,
            'order_id' => $order->id,
            'fingerprint' => 'source-order-state-no-response',
            'type' => 'order_no_1c_response',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Нет статуса заказа: 1С поставщика',
            'message' => 'Источник ещё не вернул статус.',
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $order->load(['integrationIssues.source', 'integrationDeliveries']);
        $this->assertSame('no_response', $order->onecSyncState());
        $this->assertStringContainsString('1С поставщика', $order->onecSyncDescription());

        $delivery->update([
            'status' => OrderIntegrationDelivery::STATUS_ACKNOWLEDGED,
            'remote_status' => 'Принят',
            'status_received_at' => now(),
        ]);
        $order->unsetRelation('integrationDeliveries');

        $this->assertSame('confirmed', $order->onecSyncState());
        $this->assertStringNotContainsString('Источник ещё не вернул статус.', $order->onecSyncDescription());
    }

    /** @param array<string, mixed> $overrides */
    private function createOrder(string $number, array $overrides = []): Order
    {
        return Order::query()->create(array_merge([
            'number' => $number,
            'status' => 'new',
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 10,
            'total' => 10,
        ], $overrides));
    }

    private function createIssue(Order $order, string $type, string $title, string $message): void
    {
        IntegrationIssue::query()->create([
            'order_id' => $order->id,
            'fingerprint' => 'order-ui-'.$order->id.'-'.$type,
            'type' => $type,
            'severity' => $type === 'order_status_conflict' ? 'danger' : 'warning',
            'status' => 'open',
            'title' => $title,
            'message' => $message,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\Order;
use App\Services\Integrations\OneCBridge\OneCAgentTasks;
use App\Services\Integrations\OneCBridge\OrderSupplyTaskHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OneCOrderSupplyTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $extra = []): Order
    {
        Mail::fake();
        $order = Order::create($extra + ['number' => 'SUPPLY-'.Order::count(), 'customer_name' => 'Test',
            'customer_phone' => '+375291234567', 'delivery_type' => 'courier', 'status' => 'processing',
            'payment_status' => 'pending', 'payment_type' => 'cash', 'subtotal' => 100, 'total' => 100,
            'onec_order_guid' => 'aaaaaaaa-0000-0000-0000-000000000001', 'onec_order_number' => 'TEST-1C',
            'onec_posted' => true, 'onec_deleted' => false, 'onec_exported_at' => now(), 'onec_doc_status' => 'КВыполнению']);
        $order->forceFill(['onec_posted' => $extra['onec_posted'] ?? true,
            'onec_deleted' => $extra['onec_deleted'] ?? false, 'onec_doc_status' => $extra['onec_doc_status'] ?? 'КВыполнению'])->save();

        return $order;
    }

    private function agent(string $version): string
    {
        config(['onec.supply_sync_enabled' => true]);
        [$token, $plain] = OneCAgentToken::issue('supply-test', null);
        $token->forceFill(['agent_version' => $version, 'last_seen_at' => now()])->save();

        return $plain;
    }

    public function test_old_agent_keeps_legacy_work_but_cannot_create_new_supply_orders(): void
    {
        $plain = $this->agent('10');
        $order = $this->order();
        $new = app(OneCAgentTasks::class)->enqueue('onec_order', $order, 'New', null, ['live_supply' => true]);
        $legacy = app(OneCAgentTasks::class)->enqueue('odata_read', null, 'Read', null, ['queries' => []]);
        $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'X-Kotlov-Agent' => '10'])
            ->get('/1c/agent/tasks/next')->assertOk()->assertSee('task|'.$legacy->id.'|odata_read');
        $this->assertSame('queued', $new->fresh()->status);
        $this->withHeaders(['Authorization' => 'Bearer '.$plain, 'X-Kotlov-Agent' => '11'])
            ->get('/1c/agent/tasks/next')->assertOk()->assertSee('supply|live');
    }

    public function test_only_open_posted_orders_are_queued_once_and_failures_stop_automatic_retry(): void
    {
        $this->agent('11');
        $open = $this->order();
        $this->order(['status' => 'delivered']);
        $this->order(['onec_doc_status' => 'Закрыт']);
        $this->order(['onec_state' => 'Закрыт']);
        $this->order(['onec_posted' => false]);
        $handler = app(OrderSupplyTaskHandler::class);
        $this->assertSame(1, $handler->enqueueOpen());
        $task = OneCAgentTask::where('type', $handler::TYPE)->firstOrFail();
        $this->assertSame($open->id, $task->subject_id);
        $this->assertSame(0, $handler->enqueueOpen());
        $task->update(['status' => 'failed', 'created_at' => now()->subHour()]);
        $this->assertSame(0, $handler->enqueueOpen());
        Mail::assertNothingQueued();
    }

    public function test_preview_does_not_change_customer_status_or_send_notifications(): void
    {
        $this->agent('11');
        $order = $this->order();
        $handler = app(OrderSupplyTaskHandler::class);
        $this->assertSame(1, $handler->enqueueOpen(20, true));
        $task = OneCAgentTask::where('type', $handler::TYPE)->firstOrFail();
        $this->assertContains('preview|1', $handler->lines($task));
        $summary = $handler->finished($task, ['ok' => true, 'data' => ['changed' => '2', 'document_key' => $order->onec_order_guid]]);
        $this->assertSame('Без записи в 1С: строк для изменения 2; к отгрузке 0, к обеспечению 0', $summary);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(0, $order->managerComments()->count());
        Mail::assertNothingQueued();
    }

    public function test_wrong_order_result_is_saved_as_failure_and_not_retried(): void
    {
        $this->agent('11');
        $this->order();
        app(OrderSupplyTaskHandler::class)->enqueueOpen();
        $task = OneCAgentTask::where('type', OrderSupplyTaskHandler::TYPE)->firstOrFail();
        $task->update(['status' => 'sending']);
        $result = app(OneCAgentTasks::class)->finish($task, ['ok' => true, 'data' => ['changed' => '1', 'document_key' => 'different']]);
        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('другой заказ', $result->error);
        $this->assertSame(0, app(OrderSupplyTaskHandler::class)->enqueueOpen());
        Mail::assertNothingQueued();
    }
}

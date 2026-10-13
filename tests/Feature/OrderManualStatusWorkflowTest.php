<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManualStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_stage_change_records_manager_and_auditable_comment(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'number' => 'ORD-MANUAL-1',
            'status' => 'confirmed',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'courier',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->actingAs($manager);

        $order->transitionTo('processing', 'Ручная фиксация: заказ создан в 1С.');

        $order->refresh();
        $history = OrderStatusHistory::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame('processing', $order->status);
        $this->assertSame($manager->id, $order->manager_id);
        $this->assertSame($manager->id, $history->user_id);
        $this->assertSame('confirmed', $history->status_from);
        $this->assertSame('processing', $history->status_to);
        $this->assertSame('Ручная фиксация: заказ создан в 1С.', $history->comment);
    }
}

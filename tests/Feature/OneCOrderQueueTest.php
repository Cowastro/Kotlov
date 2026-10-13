<?php

namespace Tests\Feature;

use App\Models\IntegrationSource;
use App\Models\Order;
use App\Models\OrderIntegrationDelivery;
use App\Models\User;
use App\Services\Integrations\OneCOrderQueue;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OneCOrderQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_order_is_moved_to_processing_and_explicitly_queued(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'Центральная 1С',
            'is_active' => true,
        ]);
        $order = $this->order(['status' => 'confirmed']);

        $delivery = app(OneCOrderQueue::class)->queue($order, $manager, [
            'manager_id' => $manager->id,
            'assigned_to' => '@'.$manager->name,
        ]);

        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame($manager->id, $order->fresh()->manager_id);
        $this->assertSame($source->id, $delivery->integration_source_id);
        $this->assertSame(OrderIntegrationDelivery::STATUS_PENDING, $delivery->status);
        $this->assertSame('kotlov-order-'.$order->id, $delivery->external_id);
        $this->assertSame($manager->id, $delivery->queued_by);
        $this->assertNotNull($delivery->queued_at);
    }

    public function test_unconfirmed_order_cannot_be_queued(): void
    {
        IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'Центральная 1С',
            'is_active' => true,
        ]);

        $this->expectException(DomainException::class);
        app(OneCOrderQueue::class)->queue($this->order(['status' => 'new']), null);
    }

    /** @param array<string, mixed> $attributes */
    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => 'ORD-QUEUE-'.uniqid(),
            'customer_name' => 'Тестовый покупатель',
            'customer_phone' => '+375291112233',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
            ...$attributes,
        ]);
    }
}

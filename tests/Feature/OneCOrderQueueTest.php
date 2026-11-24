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

    public function test_confirmed_order_cannot_bypass_delivery_preparation_via_legacy_queue(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $source = IntegrationSource::query()->updateOrCreate(['code' => 'onec'], [
            'name' => 'Центральная 1С',
            'is_active' => true,
        ]);
        $order = $this->order(['status' => 'confirmed']);

        $this->expectException(DomainException::class);
        app(OneCOrderQueue::class)->queue($order, $manager, [
            'manager_id' => $manager->id,
            'assigned_to' => '@'.$manager->name,
        ]);

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

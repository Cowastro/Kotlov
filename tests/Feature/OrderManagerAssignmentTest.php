<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderManagerAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'number' => 'ORD-MGR-'.random_int(1000, 9999), 'status' => 'new', 'customer_name' => 'Клиент', 'customer_phone' => '+375291111114',
            'delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 100, 'total' => 100,
            ...$attributes,
        ]);
    }

    public function test_an_order_taken_in_telegram_stays_with_that_manager_after_a_colleague_acts(): void
    {
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Анатолий Петров']);
        $order = $this->order(['status' => 'checking', 'assigned_to' => '@natali_tg']);

        $this->actingAs($other);
        $order->transitionTo('confirmed', 'Наличие подтверждено.', [
            'manager_id' => $order->manager_id ?: $other->id,
            'assigned_to' => $order->assigned_to ?: '@'.$other->name,
        ]);

        $order->refresh();
        $this->assertNull($order->manager_id);
        $this->assertSame('@natali_tg', $order->assigned_to);
        $this->assertSame('confirmed', $order->status);
    }

    public function test_a_telegram_claim_resolves_to_the_crm_user_with_that_telegram_name(): void
    {
        $natalia = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Наталья', 'telegram_username' => 'natali_tg']);
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = $this->order(['status' => 'checking', 'assigned_to' => '@natali_tg']);

        $this->actingAs($other);
        $order->update(['manager_id' => $other->id]);

        $this->assertSame($natalia->id, $order->refresh()->manager_id);
    }

    public function test_an_unclaimed_order_is_still_taken_by_the_manager_who_moves_it(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true, 'telegram_username' => 'anat']);
        $order = $this->order();

        $this->actingAs($manager);
        $order->transitionTo('checking');

        $order->refresh();
        $this->assertSame($manager->id, $order->manager_id);
        $this->assertSame('@anat', $order->assigned_to);
    }

    public function test_manager_can_reassign_the_order(): void
    {
        $anatoly = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Анатолий']);
        $natalia = User::factory()->create(['role' => 'manager', 'is_active' => true, 'name' => 'Наталья']);
        $order = $this->order(['status' => 'checking', 'manager_id' => $anatoly->id, 'assigned_to' => '@Анатолий']);

        $this->actingAs($natalia);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('assignManager', ['manager_id' => $natalia->id])
            ->assertHasNoActionErrors();

        $order->refresh();
        $this->assertSame($natalia->id, $order->manager_id);
        $this->assertSame('@Наталья', $order->assigned_to);
        $this->assertStringContainsString('Анатолий → Наталья', $order->managerComments()->first()->body);
    }
}

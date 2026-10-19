<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_link_opens_the_complete_live_order_without_email_or_device_confirmation(): void
    {
        [$order, $manager] = $this->order();
        $portal = app(OrderPortal::class);
        $link = $portal->publish($order, $manager);

        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Расширительный бак V 300')
            ->assertSee('Комплект автоматики')
            ->assertSee('ТМ Менеджмент')
            ->assertSee('913,00 BYN');
        $this->assertNull($link->fresh()->access_email);
        $this->assertDatabaseCount('order_portal_sessions', 0);
    }

    public function test_the_same_link_can_be_forwarded_and_opened_in_another_session(): void
    {
        [$order, $manager] = $this->order();
        $portal = app(OrderPortal::class);
        $link = $portal->publish($order, $manager);

        $this->get($portal->url($link))->assertOk();

        $this->flushSession();
        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee('Комплект автоматики');

        $this->assertSame(2, $link->fresh()->open_count);
        $this->assertDatabaseCount('order_portal_challenges', 0);
    }

    private function order(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-PORTAL-001',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375291111111',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'manager_id' => $manager->id,
            'subtotal' => 1857,
            'total' => 1857,
        ]);
        $order->items()->create([
            'product_name' => 'Расширительный бак V 300',
            'product_sku' => 'KOTLOV-007689',
            'price' => 1185,
            'quantity' => 1,
            'total' => 1185,
            'supply_supplier_name' => 'ТМ Менеджмент',
            'supply_supplier_contact' => '+375 44 592-09-20',
            'supply_purchase_price' => 913,
            'supply_stock_quantity' => 2,
            'supply_is_available' => true,
            'supply_captured_at' => now(),
        ]);
        $order->items()->create([
            'product_name' => 'Комплект автоматики',
            'product_sku' => 'THERMOKRAFT-CTRL',
            'price' => 672,
            'quantity' => 1,
            'total' => 672,
            'supply_is_available' => false,
            'supply_captured_at' => now(),
        ]);

        return [$order->fresh('items'), $manager];
    }
}

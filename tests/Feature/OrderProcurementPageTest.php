<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderProcurementPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_open_printable_procurement_sheet(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-PROC-001',
            'status' => 'confirmed',
            'customer_name' => 'Клиент',
            'customer_phone' => '+375291111111',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'manager_id' => $manager->id,
            'subtotal' => 1185,
            'total' => 1185,
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

        $this->actingAs($manager)
            ->get(route('admin.orders.procurement', $order))
            ->assertOk()
            ->assertSee('Закупочный лист ORD-PROC-001')
            ->assertSee('Расширительный бак V 300')
            ->assertSee('913,00 BYN')
            ->assertSee('ТМ Менеджмент')
            ->assertSee('+375 44 592-09-20');
    }
}

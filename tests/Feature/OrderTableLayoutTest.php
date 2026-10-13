<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTableLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_table_keeps_long_product_lists_compact_and_inside_the_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $order = Order::query()->create([
            'number' => 'ORD-WIDE-TABLE',
            'status' => 'new',
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 400,
            'total' => 400,
        ]);

        foreach (range(1, 4) as $position) {
            OrderItem::query()->create([
                'order_id' => $order->id,
                'product_name' => "Длинное наименование товара № {$position}",
                'product_sku' => "WIDE-{$position}",
                'price' => 100,
                'quantity' => 1,
                'total' => 100,
            ]);
        }

        $this->actingAs($admin)
            ->get(OrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Показать ещё 2 товара')
            ->assertSee('overscroll-behavior-inline: contain;', false)
            ->assertSee('width:250px;max-width:250px;overflow-wrap:anywhere', false);
    }
}

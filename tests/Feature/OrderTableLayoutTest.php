<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
            ->assertSee('class="order-cell-details order-item-preview"', false)
            ->assertSeeText('+3 · 4 шт.')
            ->assertSeeText('Длинное наименование товара № 4 [WIDE-4]')
            ->assertSeeText('Другие очереди и статусы')
            ->assertSeeText('Дополнительные фильтры')
            ->assertSee('overscroll-behavior-inline: contain;', false)
            ->assertDontSee('width:220px;max-width:220px;overflow-wrap:anywhere', false);
    }

    public function test_order_search_respects_status_queue_and_responsible_filters(): void
    {
        $manager = User::factory()->create(['name' => 'Анна Менеджер', 'role' => 'manager', 'is_active' => true]);
        $mine = $this->order('ORD-FILTER-MINE', ['manager_id' => $manager->id]);
        $unassigned = $this->order('ORD-FILTER-EMPTY', ['assigned_to' => ' ']);
        $legacy = $this->order('ORD-FILTER-LEGACY', ['assigned_to' => '@operator']);
        $closed = $this->order('ORD-FILTER-CLOSED', ['status' => 'completed']);

        $unassigned->items()->create([
            'product_name' => 'Насос циркуляционный', 'product_sku' => 'SKU-FILTER-19', 'price' => 100, 'quantity' => 1, 'total' => 100,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($manager)->test(ListOrders::class)
            ->assertCanSeeTableRecords([$mine, $legacy, $unassigned])
            ->assertCanNotSeeTableRecords([$closed])
            ->filterTable('manager_id', 'unassigned')
            ->assertCanSeeTableRecords([$unassigned])
            ->assertCanNotSeeTableRecords([$mine, $legacy, $closed])
            ->searchTable('SKU-FILTER-19')
            ->assertCanSeeTableRecords([$unassigned])
            ->searchTable('+375 (29) 111-00-00')
            ->assertCanSeeTableRecords([$unassigned])
            ->filterTable('manager_id', 'mine')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$unassigned, $legacy, $closed])
            ->call('resetTableFiltersForm')
            ->searchTable('ORD-FILTER-CLOSED')
            ->assertCanNotSeeTableRecords([$closed])
            ->assertSee('Искать во всех заказах')
            ->call('selectOrderTab', 'all')
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords([$closed])
            ->assertCanNotSeeTableRecords([$mine, $legacy, $unassigned]);
    }

    private function order(string $number, array $attributes = []): Order
    {
        return Order::create($attributes + [
            'number' => $number, 'status' => 'new', 'customer_name' => 'Клиент', 'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 100, 'total' => 100,
        ]);
    }
}

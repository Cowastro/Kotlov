<?php

namespace Tests\Feature;

use App\Filament\Resources\DealByOrders\DealByOrderResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\DealByOrderInbox;
use App\Models\User;
use App\Services\Marketplaces\DealByOrderProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealByOrderAdminResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_gets_the_regular_order_workspace_for_deal_by_orders(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $inbox = DealByOrderInbox::query()->create([
            'remote_order_id' => '987654',
            'remote_status' => 'pending',
            'remote_status_name' => 'Новый',
            'remote_created_at' => '2026-10-19 08:00:00',
            'remote_modified_at' => '2026-10-19 08:05:00',
            'first_seen_at' => '2026-10-19 08:06:00',
            'last_seen_at' => '2026-10-19 08:06:00',
            'payload_hash' => hash('sha256', 'deal-by-order-987654'),
            'payload' => [
                'client_first_name' => 'Иван',
                'client_last_name' => 'Покупатель',
                'phone' => '+375290000000',
                'email' => 'buyer@example.test',
                'client_notes' => 'Позвонить перед доставкой',
                'delivery_option' => ['name' => 'Курьер'],
                'delivery_address' => 'Минск, тестовый адрес',
                'payment_option' => ['name' => 'Наличными'],
                'full_price' => 150.50,
                'products' => [[
                    'name' => 'Тестовый котёл',
                    'sku' => 'TEST-001',
                    'price' => 150.50,
                    'quantity' => 1,
                    'total_price' => 150.50,
                ]],
            ],
            'processing_status' => DealByOrderInbox::STATUS_PENDING,
        ]);

        $order = app(DealByOrderProjector::class)->project($inbox);

        $this->actingAs($manager)
            ->get(DealByOrderResource::getUrl('index', panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Заказы Deal.by')
            ->assertSeeText('DBY-987654')
            ->assertSeeText('Покупатель Иван')
            ->assertSeeText('Мои заказы')
            ->assertSeeText('Без ответственного');

        $this->actingAs($manager)
            ->get(DealByOrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Заказ Deal.by №987654')
            ->assertSeeText('+375290000000')
            ->assertSeeText('buyer@example.test')
            ->assertSeeText('Позвонить перед доставкой')
            ->assertSeeText('Тестовый котёл')
            ->assertSeeText('Принять заказ');

        $this->actingAs($manager)
            ->get(DealByOrderResource::getUrl('edit', ['record' => $order], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Управление заказом');

        $this->actingAs($manager)
            ->get(OrderResource::getUrl('view', ['record' => $order], panel: 'admin'))
            ->assertNotFound();

        $this->assertFalse(DealByOrderResource::canCreate());
        $this->assertTrue(DealByOrderResource::canEdit($order));
        $this->assertFalse(OrderResource::getEloquentQuery()->whereKey($order)->exists());
        $this->assertTrue(DealByOrderResource::getEloquentQuery()->whereKey($order)->exists());
    }
}

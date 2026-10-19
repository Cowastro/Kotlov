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

        $this->assertTrue($link->expires_at->between(now()->addDays(14), now()->addDays(15)->addMinute()));

        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Расширительный бак V 300')
            ->assertSee('Комплект автоматики')
            ->assertSee('ТМ Менеджмент')
            ->assertSee('913,00 BYN');
        $this->assertNull($link->fresh()->access_email);
        $this->assertDatabaseCount('order_portal_sessions', 0);

        $this->get($portal->printUrl($link))
            ->assertOk()
            ->assertSee('Заказ '.$order->number)
            ->assertSee('Вернуться к заказу')
            ->assertSee('Открыть заказ с телефона')
            ->assertSee('QR-код онлайн-заказа')
            ->assertSee('Комплект автоматики');
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

    public function test_link_stops_working_when_order_is_completed(): void
    {
        [$order, $manager] = $this->order();
        $portal = app(OrderPortal::class);
        $link = $portal->publish($order, $manager);

        $order->update(['status' => 'delivered', 'payment_status' => 'paid']);

        $this->get($portal->url($link))->assertGone()->assertSee('недоступен');
        $this->get($portal->printUrl($link))->assertGone()->assertSee('недоступен');
    }

    public function test_office_can_accept_delivery_then_finish_delivery_and_payment_with_actor_audit(): void
    {
        [$order, $manager] = $this->order();
        $portal = app(OrderPortal::class);
        $link = $portal->publish($order, $manager);

        $plannedDate = today()->addDays(3)->toDateString();
        $this->post(route('order-portal.action', $link->token_encrypted), [
            'actor' => 'Анна, офис',
            'workflow_action' => 'add_comment',
            'comment_category' => 'no_stock',
            'note' => 'Одной позиции нет в наличии, ожидаем поступление.',
            'planned_delivery_date' => $plannedDate,
        ])->assertRedirect($portal->url($link));

        $this->assertSame($plannedDate, $order->fresh()->planned_delivery_date->toDateString());
        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee('Нет в наличии')
            ->assertSee('Одной позиции нет в наличии, ожидаем поступление.')
            ->assertSee(today()->addDays(3)->format('d.m.Y'));

        $this->post(route('order-portal.action', $link->token_encrypted), [
            'actor' => 'Анна, офис',
            'workflow_action' => 'accept_delivery',
        ])->assertRedirect($portal->url($link));

        $this->assertSame('shipped', $order->fresh()->status);
        $this->get($portal->url($link))
            ->assertOk()
            ->assertSee('Принят в доставку')
            ->assertSee('Анна, офис');

        $this->post(route('order-portal.action', $link->token_encrypted), [
            'actor' => 'Анна, офис',
            'workflow_action' => 'delivered_paid',
        ])->assertOk()
            ->assertSee('Доставка и оплата подтверждены')
            ->assertSee('Анна, офис');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status_from' => 'shipped',
            'status_to' => 'delivered',
            'comment' => 'Онлайн-заказ: Анна, офис — Доставлен и оплачен.',
        ]);
        $this->assertDatabaseHas('order_portal_events', [
            'order_id' => $order->id,
            'type' => 'order_action',
        ]);
        $this->get($portal->url($link))->assertGone();
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

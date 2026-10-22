<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class OrderWorkflowSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_exposes_exactly_one_next_step_for_each_active_status(): void
    {
        $workflow = app(OrderWorkflow::class);

        $this->assertSame('Проверить наличие', $workflow->step($this->order('new'))['label']);
        $this->assertSame('Подтвердить наличие', $workflow->step($this->order('checking'))['label']);
        $this->assertSame('Передать в исполнение', $workflow->step($this->order('confirmed'))['label']);
        $this->assertSame('Передать в доставку', $workflow->step($this->order('processing'))['label']);
        $this->assertSame('Отметить доставленным', $workflow->step($this->order('shipped'))['label']);
        $this->assertNull($workflow->step($this->order('delivered')));
        $this->assertNull($workflow->step($this->order('cancelled')));
    }

    public function test_manager_can_only_advance_in_sequence_and_availability_is_a_required_gate(): void
    {
        config()->set('onec.order_queue_enabled', false);
        Mail::fake();

        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $customer = User::factory()->create();
        $order = $this->order('new');
        $order->update([
            'user_id' => $customer->id,
            'customer_email' => $customer->email,
        ]);
        $workflow = app(OrderWorkflow::class);

        $workflow->advance($order, $manager);
        $this->assertSame('checking', $order->fresh()->status);

        try {
            $workflow->advance($order->fresh(), $manager);
            $this->fail('Availability confirmation must be blocked without confirmed items.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Сначала подтвердите наличие', $exception->getMessage());
        }

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Последовательный товар',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
            'pricing_type' => 'retail',
            'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'available',
            'fulfillment_confirmed_at' => now(),
        ]);

        $workflow->advance($order->fresh(), $manager);
        $this->assertSame('confirmed', $order->fresh()->status);

        $workflow->advance($order->fresh(), $manager);
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertDatabaseCount('order_integration_deliveries', 0);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn (OrderStatusUpdatedMail $mail): bool => $mail->order->status === 'processing');

        $workflow->advance($order->fresh(), $manager);
        $this->assertSame('shipped', $order->fresh()->status);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn (OrderStatusUpdatedMail $mail): bool => $mail->order->status === 'shipped');

        $workflow->advance($order->fresh(), $manager);
        $this->assertSame('delivered', $order->fresh()->status);
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn (OrderStatusUpdatedMail $mail): bool => $mail->order->status === 'delivered');
        Mail::assertQueuedCount(4);

        $this->expectException(DomainException::class);
        $workflow->advance($order->fresh(), $manager);
    }

    public function test_order_table_shows_one_contextual_next_step_button(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $new = $this->order('new');
        $checking = $this->order('checking');
        $confirmedPaid = $this->order('confirmed');
        $confirmedPaid->update([
            'payment_type' => 'webpay',
            'payment_status' => 'paid',
        ]);
        $processing = $this->order('processing');
        OrderItem::query()->create([
            'order_id' => $new->id,
            'product_name' => 'Товар без поставщика',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->assertTableActionHasLabel('advanceWorkflow', 'Проверить', $new)
            ->assertTableActionHasLabel('correctOrder', 'Корректировать заказ', $new)
            ->assertTableActionVisible('correctOrder', $new)
            ->assertTableActionVisible('findAnalog', $new)
            ->assertTableActionHasLabel('advanceWorkflow', 'Подтвердить', $checking)
            ->assertTableActionVisible('correctOrder', $checking)
            ->assertTableActionHasLabel('advanceWorkflow', 'В работу', $confirmedPaid)
            ->assertTableActionHasLabel('advanceWorkflow', 'В доставку', $processing)
            ->assertTableActionHidden('correctOrder', $processing)
            ->assertTableActionHidden('findAnalog', $processing)
            ->assertTableActionDoesNotExist('confirmed', record: $new)
            ->assertTableActionDoesNotExist('confirmAvailability', record: $new)
            ->assertTableActionDoesNotExist('processing', record: $new)
            ->assertTableActionDoesNotExist('shipped', record: $new)
            ->assertTableActionDoesNotExist('delivered', record: $new)
            ->assertSeeText('✓ Оплачен');
    }

    public function test_table_confirmation_collects_fulfillment_and_advances_checking_order(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'workflow-supplier',
            'name' => 'Тестовый поставщик',
            'is_active' => true,
        ]);
        $order = $this->order('checking');
        $item = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Товар для быстрого подтверждения',
            'product_sku' => 'QUICK-1',
            'price' => 100,
            'quantity' => 1,
            'total' => 100,
            'supply_supplier_id' => $supplier->id,
            'supply_supplier_name' => $supplier->name,
            'supply_is_available' => true,
            'supply_purchase_price' => 70,
        ]);

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListOrders::class)
            ->callTableAction('advanceWorkflow', $order, [
                'order_item_id' => $item->id,
                'fulfillment_route' => 'supplier_purchase',
                'supplier_id' => $supplier->id,
                'availability_status' => 'available',
                'purchase_price' => 70,
                'note' => 'Наличие подтверждено поставщиком',
            ])
            ->assertHasNoTableActionErrors();

        $order->refresh();
        $item->refresh();

        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->availability_confirmed_at);
        $this->assertSame('supplier_purchase', $item->fulfillment_route);
        $this->assertSame($supplier->id, $item->fulfillment_supplier_id);
        $this->assertSame('available', $item->fulfillment_availability_status);
        $this->assertNotNull($item->fulfillment_confirmed_at);
    }

    private function order(string $status): Order
    {
        return Order::query()->create([
            'number' => 'ORD-SEQUENCE-'.strtoupper($status).'-'.uniqid(),
            'status' => $status,
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375290000000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 100,
        ]);
    }
}

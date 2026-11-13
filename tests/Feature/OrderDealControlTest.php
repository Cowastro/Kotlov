<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderDealControl;
use App\Services\Orders\OrderDealControlNotifier;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrderDealControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_orders_derive_their_stage_from_fulfillment(): void
    {
        foreach (['new' => 'received', 'processing' => 'processing', 'shipped' => 'handover', 'delivered' => 'completed'] as $status => $stage) {
            $order = $this->order($status);
            $this->assertSame($stage, $order->effectiveDealStage());
        }
        $this->assertFalse($this->order('cancelled')->dealIsActive());
    }

    public function test_manager_can_change_stage_and_schedule_control_from_the_order_list(): void
    {
        $manager = $this->manager();
        $order = $this->order();
        $localDeadline = now('Europe/Minsk')->addDay()->startOfMinute()->format('Y-m-d H:i:s');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($manager)->test(ListOrders::class)
            ->assertSet('activeTab', 'work_queue')
            ->call('mountAction', 'manageDeal', ['stage' => 'contacted'], ['table' => true, 'recordKey' => (string) $order->id])
            ->assertSchemaStateSet(['stage' => 'contacted'])
            ->fillForm([
                'stage' => 'contacted',
                'next_action' => 'Уточнить адрес доставки',
                'control_at' => $localDeadline,
                'manager_id' => $manager->id,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Уточнить адрес доставки');

        $order->refresh();
        $this->assertSame('contacted', $order->effectiveDealStage());
        $this->assertSame($manager->id, $order->manager_id);
        $this->assertSame(1, $order->deal_version);
        $this->assertSame(
            \Illuminate\Support\Carbon::parse($localDeadline, 'Europe/Minsk')->utc()->format('Y-m-d H:i:s'),
            $order->deal_control_at->utc()->format('Y-m-d H:i:s'),
        );
        $event = $order->statusHistory()->latest('id')->first();
        $this->assertSame($manager->id, $event->user_id);
        $this->assertStringContainsString('Уточнить адрес доставки', $event->comment);
        $this->assertStringContainsString('Связались', $event->comment);

        Livewire::actingAs($manager)->test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Уточнить адрес доставки')
            ->assertSee('Этапы сделки')
            ->mountAction('manageDeal', ['stage' => 'agreed'])
            ->assertSchemaStateSet(['stage' => 'agreed']);
    }

    public function test_stale_changes_cannot_overwrite_another_managers_control(): void
    {
        $manager = $this->manager();
        $order = $this->order();
        $this->save($order, $manager);
        try {
            $this->save($order, $manager, ['next_action' => 'Устаревшее изменение']);
            $this->fail('A stale version must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stage', $exception->errors());
        }
        $this->assertSame('Позвонить клиенту', $order->fresh()->deal_next_action);
        $this->assertSame(1, $order->fresh()->deal_version);
        $this->assertSame(1, $order->statusHistory()->count());
    }

    public function test_future_fulfillment_cannot_be_claimed_and_closed_orders_cannot_be_reopened(): void
    {
        $manager = $this->manager();
        foreach (['processing', 'handover', 'completed'] as $stage) {
            $order = $this->order();
            try {
                $this->save($order, $manager, ['stage' => $stage]);
                $this->fail('Unconfirmed fulfillment must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('stage', $exception->errors());
            }
            $this->assertSame(0, $order->fresh()->deal_version);
        }
        $closed = $this->order('cancelled');
        $this->expectException(ValidationException::class);
        $this->save($closed, $manager);
    }

    public function test_reassignment_while_the_form_is_open_cannot_be_overwritten(): void
    {
        $manager = $this->manager();
        $colleague = $this->manager();
        $order = $this->order();
        $order->forceFill(['manager_id' => $colleague->id, 'assigned_to' => '@'.$colleague->name])->save();
        $stale = $order->fresh();
        $order->forceFill(['manager_id' => $manager->id, 'assigned_to' => '@'.$manager->name])->save();
        $this->expectException(ValidationException::class);
        $this->save($stale, $colleague);
    }

    public function test_customer_cannot_change_order_deal_control(): void
    {
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $this->save($this->order(), $customer);
    }

    public function test_returning_to_an_earlier_stage_requires_a_recorded_reason(): void
    {
        $manager = $this->manager();
        $order = $this->order();
        $this->save($order, $manager, ['stage' => 'agreed']);
        try {
            $this->save($order->fresh(), $manager, ['stage' => 'contacted']);
            $this->fail('Returning to a previous stage requires a reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('comment', $exception->errors());
        }
        $this->save($order->fresh(), $manager, ['stage' => 'contacted', 'comment' => 'Клиент изменил комплектацию']);
        $this->assertSame('contacted', $order->fresh()->effectiveDealStage());
        $this->assertStringContainsString('Клиент изменил комплектацию', $order->statusHistory()->latest('id')->first()->comment);
    }

    public function test_tooltip_keeps_the_stage_author_and_time_when_control_is_rescheduled(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $manager = $this->manager();
        $colleague = $this->manager();
        $order = $this->save($this->order(), $manager);
        $enteredAt = now()->copy();
        $this->travel(2)->hours();
        $order = $this->save($order, $colleague, ['next_action' => 'Повторно позвонить', 'manager_id' => $colleague->id]);
        $order->load('statusHistory.user');
        $event = $order->dealStageHistory()['contacted'];
        $this->assertSame($manager->id, $event->user_id);
        $this->assertTrue($event->created_at->equalTo($enteredAt));
        $html = view('filament.resources.orders.deal-progress', ['getRecord' => fn () => $order, 'isTable' => true])->render();
        $hint = "Связались\n".$manager->name."\n".$enteredAt->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)';
        $this->assertStringContainsString('title="'.e($hint).'"', $html);
        $this->assertStringContainsString('>Условия</span>', $html);
        $this->assertStringContainsString('>Передача</span>', $html);
    }

    public function test_legacy_stage_history_ignores_reminder_edits_and_requested_transitions(): void
    {
        $manager = $this->manager();
        $order = $this->order();
        $entered = $order->statusHistory()->create([
            'user_id' => $manager->id, 'status_from' => 'new', 'status_to' => 'new',
            'comment' => "Контроль сделки. Было: Получен\nСтало: Связались · Позвонить",
        ]);
        foreach ([
            "Контроль сделки. Было: Связались · Позвонить\nСтало: Связались · Перенести звонок",
            "Контроль сделки. Было: Связались\nЗапрошен переход: Исполнение · Позвонить",
        ] as $comment) {
            $order->statusHistory()->create([
                'user_id' => $manager->id, 'status_from' => 'new', 'status_to' => 'new', 'comment' => $comment,
            ]);
        }
        $history = $order->load('statusHistory.user')->dealStageHistory();
        $this->assertSame(['contacted'], array_keys($history));
        $this->assertSame($entered->id, $history['contacted']->id);
    }

    public function test_later_stages_use_the_existing_order_workflow_and_finish_control(): void
    {
        Mail::fake();
        config()->set('onec.order_queue_enabled', false);
        $manager = $this->manager();
        $this->actingAs($manager);
        $order = $this->order('confirmed');
        $order->items()->create([
            'product_name' => 'Товар', 'price' => 100, 'quantity' => 1, 'total' => 100,
            'fulfillment_confirmed_at' => now(), 'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'available',
        ]);

        $order = $this->save($order, $manager, ['stage' => 'processing']);
        $this->assertSame('processing', $order->status);
        $this->assertSame('processing', $order->effectiveDealStage());
        $order = $this->save($order, $manager, ['stage' => 'handover']);
        $this->assertSame('shipped', $order->status);
        $order = $this->save($order, $manager, ['stage' => 'completed', 'control_at' => null, 'next_action' => '']);
        $this->assertSame('delivered', $order->status);
        $this->assertSame('completed', $order->effectiveDealStage());
        $this->assertNull($order->deal_control_at);
        $this->assertFalse($order->dealIsActive());
        $history = $order->load('statusHistory.user')->dealStageHistory();
        foreach (['processing', 'handover', 'completed'] as $stage) {
            $this->assertSame($manager->id, $history[$stage]->user_id);
            $this->assertSame($stage, $history[$stage]->deal_stage_to);
        }
    }

    public function test_control_queues_use_minsk_dates_and_exclude_completed_orders(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(21, 30));
        $due = $this->order();
        $due->forceFill(['deal_next_action' => 'Позвонить', 'deal_control_at' => now()->subMinutes(10)])->save();
        $tomorrow = $this->order();
        $tomorrow->forceFill(['deal_next_action' => 'Согласовать', 'deal_control_at' => now()->addDay()])->save();
        $unscheduled = $this->order();
        $closed = $this->order('cancelled');
        $closed->forceFill(['deal_control_at' => now()->subDay()])->save();

        $this->assertSame([$due->id], Order::query()->dealControl('today')->pluck('id')->all());
        $this->assertSame([$due->id], Order::query()->dealControl('overdue')->pluck('id')->all());
        $this->assertSame([$unscheduled->id], Order::query()->dealControl('unscheduled')->pluck('id')->all());
    }

    public function test_fulfillment_conflict_keeps_the_follow_up_without_claiming_a_false_stage(): void
    {
        $manager = $this->manager();
        $order = $this->order('processing');
        $workflow = $this->mock(\App\Services\Orders\OrderWorkflow::class);
        $workflow->shouldReceive('blocker')->once()->andReturnNull();
        $workflow->shouldReceive('advance')->once()->andThrow(new \DomainException('Состояние заказа изменилось.'));
        try {
            $this->save($order, $manager, ['stage' => 'handover']);
            $this->fail('A conflicting fulfillment transition must be reported.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Следующий шаг сохранён', $exception->errors()['stage'][0]);
        }
        $this->assertSame('processing', $order->fresh()->effectiveDealStage());
        $this->assertSame('Позвонить клиенту', $order->fresh()->deal_next_action);
    }

    public function test_due_control_notifies_the_assignee_once_per_day_and_respects_postponement(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $manager = $this->manager();
        $otherManager = $this->manager();
        $order = $this->order();
        $this->save($order, $manager, ['on_hold' => true]);
        $notifier = app(OrderDealControlNotifier::class);
        $this->assertSame(0, $notifier->notify());
        $this->travel(25)->hours();
        $this->assertSame(1, $notifier->notify());
        $this->assertSame(0, $notifier->notify());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $otherManager->notifications()->count());
        $this->assertSame('new', $order->fresh()->status);
        $this->travel(1)->days();
        $this->assertSame(1, $notifier->notify());
        $order->forceFill(['status' => 'delivered'])->save();
        $this->travel(1)->days();
        $this->assertSame(0, $notifier->notify());
    }

    public function test_completed_queue_includes_all_finished_deals_without_sms_or_review_conditions(): void
    {
        $manager = $this->manager();
        $delivered = $this->order('delivered');
        $finished = $this->order('completed');
        $stageFinished = $this->order('processing');
        $stageFinished->forceFill(['deal_stage' => 'completed', 'review_sms_sent_at' => now()])->save();
        $cancelled = $this->order('cancelled');
        $cancelled->forceFill(['deal_stage' => 'completed'])->save();
        $active = $this->order('processing');
        Livewire::actingAs($manager)->test(ListOrders::class)
            ->call('selectOrderTab', 'finished')
            ->assertSet('activeTab', 'finished')
            ->assertCanSeeTableRecords([$delivered, $finished, $stageFinished])
            ->assertCanNotSeeTableRecords([$cancelled, $active])
            ->assertSee('Завершённые');
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'is_active' => true]);
    }

    private function order(string $status = 'new'): Order
    {
        return Order::create([
            'number' => 'ORD-DEAL-'.Order::count(), 'status' => $status,
            'customer_name' => 'Клиент', 'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending',
            'subtotal' => 100, 'total' => 100,
        ]);
    }

    private function save(Order $order, User $manager, array $attributes = []): Order
    {
        return app(OrderDealControl::class)->save($order, $manager, $attributes + [
            'stage' => 'contacted', 'next_action' => 'Позвонить клиенту',
            'control_at' => now()->addDay(), 'on_hold' => false, 'manager_id' => $manager->id,
            'version' => (int) $order->deal_version, 'status' => $order->status, 'comment' => null,
            'expected_manager_id' => $order->manager_id, 'expected_assigned_to' => $order->assigned_to,
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCAgentTasks\OneCAgentTaskResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\OneCAgentTask;
use App\Models\User;
use App\Services\Orders\OrderDealControl;
use App\Services\Orders\OrderDealControlNotifier;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Shipping\Evropochta;
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
        foreach (['new' => 'received', 'checking' => 'contacted', 'confirmed' => 'agreed', 'processing' => 'processing', 'shipped' => 'handover', 'delivered' => 'completed'] as $status => $stage) {
            $order = $this->order($status);
            $this->assertSame($stage, $order->effectiveDealStage());
        }
        $this->assertFalse($this->order('cancelled')->dealIsActive());
    }

    public function test_confirmed_order_uses_third_step_despite_stale_manual_marks(): void
    {
        $order = $this->order('confirmed');
        $order->forceFill(['deal_stage' => 'received'])->save();
        $html = view('filament.resources.orders.deal-progress', ['getRecord' => fn () => $order])->render();
        $this->assertStringContainsString('В обработке</strong>', $html);
        $this->assertStringContainsString('3 / 6', $html);
        $order->forceFill(['deal_stage' => 'completed'])->save();
        $this->assertSame('agreed', $order->effectiveDealStage());
        $this->assertFalse($order->canRequestCustomerReview());
    }

    public function test_order_card_offers_the_current_step_and_the_existing_europochta_label(): void
    {
        config(['services.evropochta.token' => 'test-token']);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = $this->manager();
        $order = $this->order();
        $order->update(['delivery_type' => 'evropochta']);
        $item = $order->items()->create([
            'product_name' => 'Товар', 'price' => 100, 'quantity' => 1, 'total' => 100,
        ]);
        $card = fn () => Livewire::actingAs($manager)->test(ViewOrder::class, ['record' => $order->getRouteKey()]);

        $confirmation = $card()->assertSeeHtml('data-order-next-action="confirmFulfillment"')
            ->assertDontSee('Трек-номер Европочты')
            ->assertActionVisible('confirmInternalOrder');
        $this->assertNotContains(
            'confirmFulfillment',
            collect($confirmation->instance()->getCachedHeaderActions())->filter(fn ($action) => $action instanceof \Filament\Actions\Action)->map->getName(),
        );
        $confirmation->mountAction('confirmFulfillment')->assertActionMounted('confirmFulfillment');
        $this->assertNull($item->fresh()->fulfillment_confirmed_at);

        $item->update([
            'fulfillment_confirmed_at' => now(), 'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'available',
        ]);
        $order->update(['status' => 'confirmed']);
        $card()->assertSeeHtml('data-order-next-action="advanceWorkflow"');

        $order->update(['status' => 'processing']);
        $card()->assertSeeHtml('data-order-next-action="prepareEvropochtaShipment"')
            ->assertDontSee('Трек-номер Европочты');

        $order->update(['evropochta_quote' => [
            'store_id_finish' => 2,
            'parcel' => ['store_id_start' => 1, 'weight' => 1, 'length' => 10, 'width' => 10, 'height' => 10],
        ]]);
        $card()->assertSeeHtml('data-order-next-action="createEvropochtaShipment"');

        $order->forceFill(['evropochta_tracking_number' => '9999900000001'])->save();
        $card()->assertSeeInOrder(['Следующий шаг', 'Трек-номер Европочты', '9999900000001', 'Проверяем готовность этикетки'])
            ->assertDontSeeHtml('data-order-next-action="createEvropochtaShipment"');
        $this->assertSame('processing', $order->fresh()->status);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_order_card_opens_supplier_assignment_and_preselects_the_sent_request(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = $this->manager();
        $order = $this->order('processing');
        $item = $order->items()->create([
            'product_name' => 'Позиция для закупки', 'product_sku' => 'PURCHASE-1',
            'price' => 100, 'quantity' => 1, 'total' => 100, 'fulfillment_route' => 'supplier_purchase',
        ]);
        $card = fn () => Livewire::actingAs($manager)->test(ViewOrder::class, ['record' => $order->getRouteKey()]);
        $assignment = $card()->assertSee('Назначить поставщика')
            ->assertSeeHtml('data-order-next-action="openNextFulfillmentStep"')
            ->assertDontSeeHtml('data-order-next-action="advanceWorkflow"')
            ->call('openNextFulfillmentStep')
            ->assertSet('mountedActions.0.data.request_reserve_now', false);
        $action = $assignment->instance()->getMountedAction();
        $this->assertSame('assignItemSupplier', $action?->getName());
        $this->assertTrue($action->getRecord()->is($item));
        $assignment->call('unmountAction');
        $this->assertNull($item->fresh()->fulfillment_supplier_id);

        $supplier = \App\Models\Supplier::create(['name' => 'Поставщик для проверки', 'code' => 'deal-test-supplier']);
        $item->update(['fulfillment_supplier_id' => $supplier->id, 'fulfillment_confirmed_at' => now()]);
        $request = $order->supplierOrderRequests()->create([
            'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'number' => 'REQ-DEAL-1',
            'route' => 'supplier_purchase', 'status' => 'sent', 'transfer_mode' => 'email',
        ]);
        $request->items()->create([
            'order_item_id' => $item->id, 'product_name' => $item->product_name,
            'product_sku' => $item->product_sku, 'quantity' => 1,
        ]);

        $card()->assertSee('Ждём ответ от Поставщик для проверки по заявке REQ-DEAL-1')
            ->assertSee('Позиция для закупки · PURCHASE-1')
            ->assertSee('Зафиксировать ответ поставщика')
            ->mountAction('recordSupplierEmailResponse', ['supplierOrderRequestId' => $request->id])
            ->assertActionMounted('recordSupplierEmailResponse')
            ->assertSchemaStateSet(['supplier_order_request_id' => $request->id]);
        $this->assertSame('sent', $request->fresh()->status);
    }

    public function test_pending_agent_transfer_does_not_offer_duplicate_execution(): void
    {
        $order = $this->order('confirmed');
        \App\Models\OneCAgentTask::create([
            'type' => \App\Services\Integrations\OneCBridge\OrderTaskHandler::TYPE,
            'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id,
            'title' => 'Передача заказа', 'status' => 'queued',
        ]);
        $this->assertNull(app(\App\Services\Orders\OrderWorkflow::class)->step($order));
        $this->assertStringContainsString('повторно отправлять заказ не нужно', $order->workflowNextStep());
        $this->assertSame('agreed', $order->effectiveDealStage());
    }

    public function test_attention_queue_opens_the_first_supplier_assignment_without_saving(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = $this->manager();
        $order = $this->order('processing');
        $items = $order->items()->createMany([
            ['product_name' => 'Первая позиция', 'price' => 50, 'quantity' => 1, 'total' => 50, 'fulfillment_route' => 'supplier_purchase'],
            ['product_name' => 'Вторая позиция', 'price' => 50, 'quantity' => 1, 'total' => 50, 'fulfillment_route' => 'supplier_purchase'],
        ]);
        Livewire::actingAs($manager)->test(ListOrders::class)
            ->assertDontSeeHtml('data-order-queue-next-step')
            ->call('selectOrderTab', 'attention')
            ->assertCanSeeTableRecords([$order])
            ->assertSee('Назначить поставщика')
            ->assertSee('Позиций: 2')
            ->assertSeeHtml('next-step=fulfillment');

        $card = Livewire::withQueryParams(['next-step' => 'fulfillment'])->test(ViewOrder::class, ['record' => $order->id])
            ->assertSeeHtml('wire:init="openQueueNextStep"')->call('openQueueNextStep')
            ->assertSet('queueNextStep', null);
        $action = $card->instance()->getMountedAction();
        $this->assertSame('assignItemSupplier', $action?->getName());
        $this->assertTrue($action->getRecord()->is($items->first()));
        $card->call('unmountAction');
        $this->assertNull($items->first()->fresh()->fulfillment_supplier_id);
    }

    public function test_attention_queue_opens_pickup_preparation_and_rechecks_stale_links(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = $this->manager();
        $order = $this->order('processing');
        $item = $order->items()->create([
            'product_name' => 'Товар', 'price' => 100, 'quantity' => 1, 'total' => 100,
            'fulfillment_confirmed_at' => now(), 'fulfillment_route' => 'own_stock', 'fulfillment_availability_status' => 'available',
        ]);

        Livewire::actingAs($manager)->test(ListOrders::class)->call('selectOrderTab', 'attention')
            ->assertCanSeeTableRecords([$order])->assertSee('Подготовить самовывоз')->assertSeeHtml('next-step=delivery');

        $card = fn () => Livewire::withQueryParams(['next-step' => 'delivery'])->test(ViewOrder::class, ['record' => $order->id])
            ->call('openQueueNextStep');
        $card()->assertActionMounted('prepareOrderDelivery')->assertSet('mountedActions.0.data.method', 'pickup')
            ->call('unmountAction');
        $this->assertNull($order->fresh()->delivery_preparation);

        $item->update(['fulfillment_confirmed_at' => null]);
        $this->assertNull($card()->instance()->getMountedAction());
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_attention_queue_links_the_current_failed_order_task_and_shows_supplier_and_label_waits(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = $this->manager();
        $failedOrder = $this->order('processing');
        $supplierOrder = $this->order('confirmed');
        $labelOrder = $this->order('shipped');
        $failedOrder->update(['manager_id' => $manager->id]);
        $supplierOrder->update(['manager_id' => $manager->id]);
        $labelOrder->forceFill(['delivery_type' => 'evropochta', 'evropochta_tracking_number' => 'BY080027046773'])->save();

        $supplier = \App\Models\Supplier::create(['name' => 'Тестовый поставщик', 'code' => 'queue-wait-supplier']);
        $request = $supplierOrder->supplierOrderRequests()->create([
            'supplier_id' => $supplier->id, 'supplier_name' => $supplier->name, 'number' => 'REQ-WAIT-1',
            'route' => 'supplier_purchase', 'status' => 'sent', 'transfer_mode' => 'email',
        ]);
        $taskData = [
            'type' => OrderTaskHandler::TYPE, 'subject_type' => $failedOrder->getMorphClass(),
            'subject_id' => $failedOrder->id, 'title' => 'Передача заказа', 'status' => OneCAgentTask::FAILED,
        ];
        $oldTask = OneCAgentTask::create($taskData);
        $failedTask = OneCAgentTask::create($taskData);
        $cacheKey = 'evropochta.label-status.thermal.'.$labelOrder->evropochta_tracking_number;
        \Illuminate\Support\Facades\Cache::put($cacheKey, Evropochta::LABEL_PENDING, 60);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake();

        $queue = Livewire::actingAs($manager)->test(ListOrders::class)
            ->assertDontSeeHtml('data-order-queue-wait')
            ->call('selectOrderTab', 'attention')
            ->assertCanSeeTableRecords([$failedOrder, $supplierOrder, $labelOrder])
            ->assertSee('Ошибка 1С')
            ->assertSee('Задача №'.$failedTask->id)
            ->assertSeeHtml(OneCAgentTaskResource::getUrl('view', ['record' => $failedTask]))
            ->assertDontSeeHtml(OneCAgentTaskResource::getUrl('view', ['record' => $oldTask]))
            ->assertSee('Ждём ответ поставщика · Тестовый поставщик · REQ-WAIT-1')
            ->assertSee('Этикетка Европочты ещё готовится');

        OneCAgentTask::create(array_replace($taskData, ['status' => OneCAgentTask::QUEUED]));
        $request->update(['status' => 'acknowledged']);
        \Illuminate\Support\Facades\Cache::put($cacheKey, Evropochta::LABEL_READY, 60);
        $queue->call('$refresh')->assertCanNotSeeTableRecords([$failedOrder, $supplierOrder])
            ->assertDontSee('Ошибка 1С')->assertDontSee('Ждём ответ поставщика · Тестовый поставщик')
            ->assertDontSee('Этикетка Европочты ещё готовится');
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_label_is_not_dispatch_and_dispatch_is_not_receipt(): void
    {
        $order = $this->order('processing');
        $order->forceFill(['carrier_stage' => 'created', 'deal_stage' => 'completed'])->save();
        $this->assertSame('processing', $order->effectiveDealStage());
        $order->forceFill(['status' => 'shipped', 'carrier_stage' => 'transit'])->save();
        $this->assertSame('handover', $order->effectiveDealStage());
        $this->assertStringContainsString('В пути', $order->workflowNextStep());
        $order->forceFill(['status' => 'delivered', 'carrier_stage' => 'delivered'])->save();
        $html = view('filament.resources.orders.deal-progress', ['getRecord' => fn () => $order])->render();
        $this->assertSame('completed', $order->effectiveDealStage());
        $this->assertStringNotContainsString('is-current', $html);
        $this->assertSame(6, substr_count($html, 'is-done'));
    }

    public function test_checking_to_confirmation_uses_availability_gate(): void
    {
        $manager = $this->manager();
        $order = $this->order('checking');
        $this->expectException(ValidationException::class);
        $this->save($order, $manager, ['stage' => 'agreed']);
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
        $event = $order->statusHistory()->where('comment', 'like', 'Контроль сделки.%')->latest('id')->first();
        $this->assertSame($manager->id, $event->user_id);
        $this->assertStringContainsString('Уточнить адрес доставки', $event->comment);
        $this->assertStringContainsString('Проверка', $event->comment);

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

    public function test_manual_marks_cannot_rewind_actual_fulfillment(): void
    {
        $manager = $this->manager();
        $order = $this->order('confirmed');
        try {
            $this->save($order->fresh(), $manager, ['stage' => 'contacted', 'comment' => 'Клиент изменил комплектацию']);
            $this->fail('A control note must not rewind actual fulfillment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('stage', $exception->errors());
        }
        $this->assertSame('agreed', $order->fresh()->effectiveDealStage());
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_tooltip_keeps_the_stage_author_and_time_when_control_is_rescheduled(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $manager = $this->manager();
        $colleague = $this->manager();
        $this->actingAs($manager);
        $order = $this->save($this->order(), $manager, ['stage' => 'contacted']);
        $enteredAt = now()->copy();
        $this->travel(2)->hours();
        $order = $this->save($order, $colleague, ['next_action' => 'Повторно позвонить', 'manager_id' => $colleague->id]);
        $order->load('statusHistory.user');
        $event = $order->dealStageHistory()['contacted'];
        $this->assertSame($manager->id, $event->user_id);
        $this->assertTrue($event->created_at->equalTo($enteredAt));
        $html = view('filament.resources.orders.deal-progress', ['getRecord' => fn () => $order, 'isTable' => true])->render();
        $hint = "Проверка\n".$manager->name."\n".$enteredAt->timezone('Europe/Minsk')->format('d.m.Y H:i').' (Минск)';
        $this->assertStringContainsString('title="'.e($hint).'"', $html);
        $this->assertStringContainsString('>Обработка</span>', $html);
        $this->assertStringContainsString('>Доставка</span>', $html);
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
        $this->assertSame([], $history);
    }

    public function test_later_stages_use_the_existing_order_workflow_and_finish_control(): void
    {
        Mail::fake();
        config()->set('onec.order_queue_enabled', false);
        // This scenario exercises the standalone workflow, without export to 1C.
        \App\Models\IntegrationSource::query()->where('code', 'onec')->update(['is_active' => false]);

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

    public function test_completed_queue_uses_receipt_and_ignores_old_manual_completion_marks(): void
    {
        $manager = $this->manager();
        $delivered = $this->order('delivered');
        $stageFinished = $this->order('processing');
        $stageFinished->forceFill(['deal_stage' => 'completed', 'review_sms_sent_at' => now()])->save();
        $cancelled = $this->order('cancelled');
        $cancelled->forceFill(['deal_stage' => 'completed'])->save();
        $active = $this->order('processing');
        Livewire::actingAs($manager)->test(ListOrders::class)
            ->call('selectOrderTab', 'finished')
            ->assertSet('activeTab', 'finished')
            ->assertCanSeeTableRecords([$delivered])
            ->assertCanNotSeeTableRecords([$cancelled, $active, $stageFinished])
            ->assertSee('Завершённые');
    }

    public function test_legacy_completed_status_is_recognized_without_inserting_an_unsupported_mysql_enum(): void
    {
        // MySQL persists delivered, while imported/legacy model values may contain completed.
        $order = new Order(['status' => 'completed']);
        $this->assertSame('completed', $order->effectiveDealStage());
        $this->assertFalse($order->dealIsActive());
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
            'stage' => $order->effectiveDealStage(), 'next_action' => 'Позвонить клиенту',
            'control_at' => now()->addDay(), 'on_hold' => false, 'manager_id' => $manager->id,
            'version' => (int) $order->deal_version, 'status' => $order->status, 'comment' => null,
            'expected_manager_id' => $order->manager_id, 'expected_assigned_to' => $order->assigned_to,
        ]);
    }
}

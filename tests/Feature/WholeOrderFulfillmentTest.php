<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Mail\OrderAvailabilityConfirmedMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCItem;
use App\Models\OneCOrderProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderExport;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use App\Services\Orders\OrderWorkflow;
use App\Services\Orders\WholeOrderFulfillment;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WholeOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Mail::fake();
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $client = User::factory()->create(['email' => 'whole-order@example.test']);
        $this->actingAs($actor);
        $supplier = Supplier::updateOrCreate(['code' => 'sanbusinessgroup'], ['name' => 'Секретный поставщик', 'is_active' => true]);
        $source = IntegrationSource::updateOrCreate(['code' => 'onec'], ['name' => '1С', 'supplier_id' => $supplier->id, 'is_active' => true, 'update_stock' => true]);
        $category = Category::create(['name' => 'Тест', 'slug' => 'whole-order-test', 'is_active' => true]);
        $order = Order::create(['user_id' => $client->id, 'number' => 'ORD-WHOLE-TEST', 'status' => 'new',
            'customer_name' => 'Клиент', 'customer_phone' => '+375291234567', 'customer_email' => $client->email,
            'delivery_type' => 'pickup', 'payment_type' => 'cash', 'payment_status' => 'pending', 'subtotal' => 90, 'total' => 90]);
        foreach ([[1, 10], [5, 2], [2, 0], [1, null]] as $index => [$quantity, $stock]) {
            $product = Product::create(['category_id' => $category->id, 'name' => 'Позиция '.($index + 1), 'slug' => 'whole-item-'.$index,
                'price' => 10, 'is_active' => true, 'is_archived' => false]);
            $key = sprintf('aaaaaaaa-0000-0000-0000-%012d', $index + 1);
            $row = IntegrationProduct::create(['integration_source_id' => $source->id, 'product_id' => $product->id,
                'external_id' => $key, 'external_code' => 'УТ-WHOLE-'.$index, 'external_sku' => 'FACTORY-'.$index,
                'name' => $product->name, 'price_byn' => 7.5, 'stock_quantity' => $stock,
                'stock_confirmed_at' => $stock !== null ? now() : null, 'match_status' => 'matched', 'match_method' => 'manual_search']);
            if ($stock !== null) {
                OneCItem::create(['ref_key' => $key, 'description' => $product->name,
                    'stock_on_hand' => $stock, 'stock_reserved' => 0, 'stock_synced_at' => now()]);
            }
            $order->items()->create(['product_id' => $product->id, 'integration_product_id' => $row->id,
                'product_name' => $product->name, 'product_sku' => 'WHOLE-'.$index, 'price' => 10, 'quantity' => $quantity]);
        }
        $key = 'bbbbbbbb-0000-0000-0000-000000000001';
        OneCOrderProfile::create(['name' => 'Тестовая схема', 'document' => 'customer', 'customer_mode' => 'retail', 'is_active' => true, 'is_default' => true,
            'header' => array_fill_keys(['Организация_Key', 'Склад_Key', 'Партнер_Key', 'Контрагент_Key', 'Соглашение_Key'], $key),
            'row_defaults' => [], 'meta' => ['keys' => ['НомерПоДаннымКлиента'], 'row_keys' => []]]);

        return [$actor, $client, $order];
    }

    public function test_whole_order_keeps_shortages_queues_every_line_for_reserve_and_notifies_once(): void
    {
        [$actor, $client, $order] = $this->fixture();
        $before = $order->items()->get(['id', 'product_id', 'quantity', 'price', 'total'])->toArray();
        $service = app(WholeOrderFulfillment::class);
        $fingerprint = $service->fingerprint($order);
        $result = $service->confirm($order, $actor, $fingerprint, '2027-01-10', 'Согласуем удобную отгрузку.');
        $this->assertNull($result['reservation_warning']);
        $this->assertTrue($result['notified']);
        $this->assertSame($before, $order->items()->get(['id', 'product_id', 'quantity', 'price', 'total'])->toArray());
        $this->assertSame(['available', 'partial', 'awaiting_supply', 'clarifying'], $order->items()->orderBy('id')->pluck('fulfillment_availability_status')->all());
        $this->assertSame(4, $order->items()->whereNotNull('fulfillment_reservation_requested_at')->count());
        $this->assertSame(0, OneCAgentTask::where('type', 'onec_order')->count());
        $export = app(OneCOrderExport::class);
        $body = $export->body($order->fresh(), OneCOrderProfile::firstOrFail(), $export->defaults($order->fresh()));
        $rows = $body['Товары'];
        $this->assertCount(4, $rows);
        $this->assertSame([1.0, 5.0, 2.0, 1.0], array_map(fn ($r) => (float) $r['Количество'], $rows));
        foreach ($rows as $row) {
            $this->assertSame(OneCOrderExport::RESERVE, $row['ВариантОбеспечения']);
        }
        foreach (OneCOrderExport::simplify($body, 4)['Товары'] as $row) {
            $this->assertSame(OneCOrderExport::RESERVE, $row['ВариантОбеспечения']);
        }
        $second = $service->confirm($order->fresh(), $actor, $fingerprint);
        $this->assertFalse($second['notified']);
        $this->assertSame(0, OneCAgentTask::where('type', 'onec_order')->count());
        $this->assertSame(4, $order->fulfillmentHistory()->count());
        Mail::assertQueuedCount(1);
        $html = (new OrderAvailabilityConfirmedMail($order->fresh(['items', 'user'])))->render();
        foreach (range(1, 4) as $number) {
            $this->assertStringContainsString('Позиция '.$number, $html);
        }
        $this->assertStringContainsString('Ожидаем: 3 шт.', $html);
        $this->assertStringContainsString('10.01.2027', $html);
        $this->assertStringNotContainsString('возможность исполнения всех позиций', $html);
        $this->assertStringNotContainsString('Секретный поставщик', $html);
        $this->assertStringNotContainsString('7,50', $html);
        $this->actingAs($client)->get(route('account.orders.show', $order))->assertOk()
            ->assertSeeText('Принят в обработку')->assertSeeText('Ожидаем: 3 шт.')
            ->assertDontSeeText('Секретный поставщик')->assertDontSeeText('7,50');
        $order->refresh()->update(['status' => 'processing']);
        $this->assertNotNull(app(OrderWorkflow::class)->blocker($order->fresh()));
    }

    public function test_single_header_action_previews_and_accepts_all_lines(): void
    {
        [$actor, , $order] = $this->fixture();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($actor)->test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHasLabel('confirmFulfillment', 'Принять весь заказ')
            ->mountAction('confirmFulfillment')->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame(4, $order->items()->whereNotNull('fulfillment_confirmed_at')->count());
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_entire_order_waits_for_supply_then_ships_from_one_c_without_extra_manager_steps(): void
    {
        [$actor, $client, $order] = $this->fixture();
        $order->update(['delivery_type' => 'courier']);
        Storage::fake('local');
        $service = app(WholeOrderFulfillment::class);
        $service->confirm($order, $actor, $service->fingerprint($order));
        app(OrderWorkflow::class)->advance($order->fresh(), $actor);
        $order->refresh();
        app(\App\Services\Orders\OrderDeliveryPreparation::class)->confirm($order, $actor, [
            'method' => 'courier', 'from' => 'Склад', 'to' => 'Адрес клиента', 'date' => now()->addDay()->toDateString(),
        ]);
        $order->refresh();
        $writer = app(OneCOrderExport::class);
        $writer->enqueue($order, $writer->defaults($order), $actor);
        $export = OneCAgentTask::where('type', 'onec_order')->sole();
        foreach ($export->payload['body']['Товары'] as $row) {
            $this->assertSame(OneCOrderExport::RESERVE, $row['ВариантОбеспечения']);
        }
        $guid = 'bbbbbbbb-0000-0000-0000-000000000099';
        app(OrderTaskHandler::class)->finished($export, ['ok' => true, 'data' => ['document_key' => $guid, 'document_number' => 'TEST-SUPPLY']]);
        foreach ([['КОбеспечению', 'ОжидаетсяОбеспечение', 'Комплектация'], ['КВыполнению', 'ГотовКОтгрузке', 'Готов к отгрузке'], ['Закрыт', 'Закрыт', 'Отгружен']] as [$documentStatus, $state, $label]) {
            $this->travel(1)->minutes();
            $task = OneCAgentTask::create(['type' => 'doc_status', 'title' => 'Test', 'status' => 'sending', 'picked_at' => now(),
                'payload' => ['queries' => ['orders_1' => 'orders', 'states_1' => 'states'], 'parts' => ['orders_1' => ['code' => 200], 'states_1' => ['code' => 200]]]]);
            foreach (['orders_1' => [['Ref_Key' => $guid, 'Posted' => true, 'Статус' => $documentStatus, 'Товары' => [['Отменено' => false]]]],
                'states_1' => [['Заказ' => $guid, 'Состояние' => $state]]] as $name => $rows) {
                Storage::disk('local')->put(ODataReadTaskHandler::file($task, $name), json_encode(['value' => $rows]));
            }
            $handler = app(DocumentStatusTaskHandler::class);
            $handler->finished($task, ['ok' => true]);
            $handler->finished($task, ['ok' => true]);
            $this->assertSame($label, $order->fresh()->customerStatusLabel());
            $this->assertSame(4, $order->items()->count());
            $this->assertSame(9, (int) $order->items()->sum('quantity'));
        }
        $this->assertSame('shipped', $order->fresh()->status);
        $this->actingAs($client)->get(route('account.orders.show', $order))->assertOk()->assertSeeText('Отгружен')->assertDontSeeText('Заказ получен клиентом.');
        $html = (new OrderStatusUpdatedMail($order->fresh()))->render();
        $this->assertStringContainsString('Ваш заказ укомплектован и отгружен.', $html);
        $this->assertStringNotContainsString('Ожидаем поступление', $html);
        Mail::assertQueuedCount(3); // acceptance, processing, shipment; repeated answers never send duplicates
        Mail::assertQueued(OrderStatusUpdatedMail::class, fn ($mail) => $mail->statusAtQueue === 'shipped' && $mail->statusDetails['title'] === 'Отгружен');
        $this->assertDatabaseMissing('order_stage_email_claims', ['order_id' => $order->id, 'status' => 'delivered']);
    }

    public function test_one_stock_pool_is_not_promised_twice_for_repeated_product_lines(): void
    {
        [$actor, , $order] = $this->fixture();
        $first = $order->items()->orderBy('id')->first();
        $order->items()->create(['product_id' => $first->product_id, 'product_name' => $first->product_name,
            'integration_product_id' => $first->integration_product_id, 'quantity' => 12, 'price' => 10]);
        $service = app(WholeOrderFulfillment::class);
        $preview = $service->preview($order);
        $this->assertEquals(1, $preview[0]['available']);
        $this->assertEquals(9, $preview[4]['available']);
        $this->assertEquals(3, $preview[4]['missing']);
        $service->confirm($order, $actor, $service->fingerprint($order));
        $last = $order->items()->latest('id')->first();
        $this->assertSame('partial', $last->fulfillment_availability_status);
        $this->assertSame('9.000', $last->fulfillment_available_quantity);
    }

    public function test_confirmed_reserve_and_price_are_not_overwritten_and_guest_gets_the_full_email(): void
    {
        [$actor, , $order] = $this->fixture();
        $order->update(['user_id' => null, 'customer_email' => 'guest@example.test']);
        $first = $order->items()->first();
        $first->update(['fulfillment_availability_status' => 'reserved', 'fulfillment_confirmed_at' => now()->subDay(), 'fulfillment_purchase_price' => 6.25]);
        $service = app(WholeOrderFulfillment::class);
        $service->confirm($order, $actor, $service->fingerprint($order));
        $this->assertSame('reserved', $first->fresh()->fulfillment_availability_status);
        $this->assertSame('6.25', $first->fresh()->fulfillment_purchase_price);
        Mail::assertQueued(OrderAvailabilityConfirmedMail::class, fn ($mail) => $mail->hasTo('guest@example.test'));
        $this->assertStringContainsString('Зарезервировано: 1 шт.', $first->fresh()->customerFulfillmentLabel());
    }

    public function test_failed_email_can_be_retried_without_duplicate_orders_or_confirmation_history(): void
    {
        [$actor, , $order] = $this->fixture();
        Mail::shouldReceive('mailer')->with('transactional')->andThrow(new \RuntimeException('Test mail failure'));
        $service = app(WholeOrderFulfillment::class);
        $hash = $service->fingerprint($order);
        $result = $service->confirm($order, $actor, $hash);
        $this->assertNotNull($result['reservation_warning']);
        $this->assertNull($order->fresh()->customer_notified_at);
        $this->assertSame(4, $order->items()->whereNotNull('fulfillment_confirmed_at')->count());
        Mail::swap(new MailFake(new MailManager(app())));
        $this->assertTrue($service->confirm($order->fresh(), $actor, $hash)['notified']);
        $this->assertSame(0, OneCAgentTask::where('type', 'onec_order')->count());
        $this->assertSame(4, $order->fulfillmentHistory()->count());
        Mail::assertQueuedCount(1);
    }

    public function test_only_fresh_authoritative_one_c_readiness_overrides_waiting_customer_status(): void
    {
        [, , $order] = $this->fixture();
        $item = $order->items()->first();
        $item->update(['fulfillment_availability_status' => 'awaiting_supply']);
        $order->forceFill(['onec_order_guid' => 'bbbbbbbb-0000-0000-0000-000000000001', 'onec_posted' => true,
            'onec_state' => 'ГотовКОтгрузке', 'onec_status_received_at' => now()])->save();
        $this->assertStringContainsString('Подготовлено к отгрузке', $order->customerItemFulfillment($item));
        $order->update(['onec_status_received_at' => now()->subWeeks(4)]);
        $this->assertStringContainsString('Ожидаем поступление', $order->customerItemFulfillment($item));
    }

    public function test_stale_commercial_data_rejects_entire_confirmation_without_partial_writes(): void
    {
        [$actor, , $order] = $this->fixture();
        $service = app(WholeOrderFulfillment::class);
        $fingerprint = $service->fingerprint($order);
        $order->items()->first()->update(['quantity' => 3]);
        try {
            $service->confirm($order, $actor, $fingerprint);
            $this->fail('Stale confirmation accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fingerprint', $exception->errors());
        }
        $this->assertSame(0, $order->items()->whereNotNull('fulfillment_reservation_requested_at')->count());
        $this->assertSame(0, OneCAgentTask::where('type', 'onec_order')->count());
        Mail::assertNothingQueued();
    }

    public function test_account_tracks_each_real_stage_and_outdated_email_is_skipped(): void
    {
        [, $client, $order] = $this->fixture();
        $this->actingAs($client);
        foreach ([['checking', 'pickup', 'Принят в обработку'], ['processing', 'pickup', 'Комплектация'],
            ['shipped', 'pickup', 'Готов к самовывозу'], ['shipped', 'courier', 'Передан в доставку'],
            ['delivered', 'courier', 'Получен']] as [$status, $delivery, $label]) {
            $order->update(['status' => $status, 'delivery_type' => $delivery]);
            $this->get(route('account.orders.show', $order))->assertOk()->assertSeeText($label)
                ->assertDontSeeText('передано в 1С')->assertDontSeeText('Секретный поставщик');
        }
        $order->update(['status' => 'processing']);
        $mail = new OrderStatusUpdatedMail($order->fresh(['items', 'user']));
        $html = $mail->render();
        foreach (range(1, 4) as $number) {
            $this->assertStringContainsString('Позиция '.$number, $html);
        }
        $this->assertStringNotContainsString('Секретный поставщик', $html);
        $sent = 0;
        $next = function () use (&$sent) {
            $sent++;
        };
        $mail->middleware()[0]->handle(new \stdClass, $next);
        $this->assertSame(1, $sent);
        $order->update(['status' => 'shipped']);
        $mail->middleware()[0]->handle(new \stdClass, $next);
        (new OrderAvailabilityConfirmedMail($order))->middleware()[0]->handle(new \stdClass, $next);
        $this->assertSame(1, $sent);
    }

    public function test_unmapped_line_is_preserved_and_customer_gets_clarification_instead_of_a_false_reserve(): void
    {
        [$actor, , $order] = $this->fixture();
        $order->items()->create(['product_name' => 'Без привязки к 1С', 'quantity' => 2, 'price' => 5]);
        $service = app(WholeOrderFulfillment::class);
        $result = $service->confirm($order, $actor, $service->fingerprint($order));
        $this->assertNull($result['reservation_warning']);
        $this->assertSame(5, $order->items()->count());
        $item = $order->items()->latest('id')->first();
        $this->assertSame('clarifying', $item->fulfillment_availability_status);
        $this->assertStringContainsString('Уточняем наличие и срок', $item->customerFulfillmentLabel());
        $this->assertSame(0, OneCAgentTask::where('type', 'onec_order')->count());
        Mail::assertQueuedCount(1);
    }
}

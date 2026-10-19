<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder as AdminViewOrder;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Supplier\Resources\SupplierOrderRequests\SupplierOrderRequestResource;
use App\Models\Category;
use App\Models\CrmContactPoint;
use App\Models\CrmCounterparty;
use App\Models\CrmMailbox;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierOrderRequestItem;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CrmMail\SupplierRequestMailDraftService;
use App\Services\Orders\SupplierAutoTransferManager;
use App\Services\Orders\SupplierAutoTransferReadiness;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use App\Services\Orders\SupplierReservationMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierOrderRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_email_added_to_crm_card_is_pulled_into_existing_draft(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $counterparty = CrmCounterparty::query()->create(['name' => 'Поставщик без email']);
        $supplier = Supplier::query()->create([
            'crm_counterparty_id' => $counterparty->id,
            'code' => 'supplier-with-late-email',
            'name' => 'Поставщик без email',
            'contact' => '+375291112233',
            'is_active' => true,
        ]);
        [, , , $request] = $this->requestFixture('LATE-EMAIL', $manager, $supplier);
        CrmMailbox::query()->firstOrCreate(
            ['email' => 'order@kotlov.by'],
            [
                'name' => 'Заказы KOTLOV',
                'username' => 'order@kotlov.by',
                'is_enabled' => true,
            ],
        );

        $service = app(SupplierRequestMailDraftService::class);
        $thread = $service->sync($request, $manager);

        $this->assertFalse($thread->hasValidRecipient());
        $this->assertSame($counterparty->id, $thread->crm_counterparty_id);

        CrmContactPoint::query()->create([
            'counterparty_id' => $counterparty->id,
            'type' => 'email',
            'value' => 'orders@supplier.test',
            'is_primary' => true,
            'is_active' => true,
        ]);

        $thread = $service->refreshRecipientFromSupplier($thread);

        $this->assertTrue($thread->hasValidRecipient());
        $this->assertSame('orders@supplier.test', $thread->participant_email);
        $this->assertSame(
            ['orders@supplier.test'],
            collect($thread->messages()->where('direction', 'draft')->firstOrFail()->to)
                ->pluck('email')
                ->all(),
        );
    }

    public function test_order_request_draft_uses_order_mailbox_prices_stock_and_manager_note(): void
    {
        [$manager, , , $request] = $this->requestFixture('EMAIL-BODY');
        CrmMailbox::query()->create([
            'name' => 'Заказы KOTLOV',
            'email' => 'order@kotlov.by',
            'username' => 'order@kotlov.by',
            'is_enabled' => true,
        ]);
        $request->items()->firstOrFail()->orderItem()->update([
            'supply_is_available' => true,
            'supply_stock_quantity' => 7,
        ]);

        $thread = app(SupplierRequestMailDraftService::class)->sync(
            $request,
            $manager,
            'Нужна отгрузка до пятницы.',
        );
        $draft = $thread->messages()->where('direction', 'draft')->firstOrFail();

        $this->assertSame('order@kotlov.by', $thread->mailbox->email);
        $this->assertStringContainsString('80,00 BYN', $draft->text_body);
        $this->assertStringContainsString('остаток по прайсу: 7 шт.', $draft->text_body);
        $this->assertStringContainsString('Нужна отгрузка до пятницы.', $draft->text_body);
        $this->assertStringContainsString('ответным письмом подтвердите резерв', $draft->text_body);
    }

    public function test_email_transfer_does_not_require_supplier_portal_user_and_is_audited(): void
    {
        [$manager, $supplier, $supplierUser, $request] = $this->requestFixture('EMAIL-ONLY');
        $supplier->users()->detach($supplierUser);

        $result = app(SupplierOrderRequestWorkflow::class)->markEmailSent(
            $request,
            $manager,
            'Отправлено с order@kotlov.by.',
        );

        $this->assertSame('sent', $result->status);
        $this->assertSame('email', $result->transfer_mode);
        $this->assertSame($manager->id, $result->sent_by);
        $this->assertDatabaseHas('supplier_order_request_status_histories', [
            'supplier_order_request_id' => $request->id,
            'actor_scope' => 'manager_email',
            'status_from' => 'draft',
            'status_to' => 'sent',
        ]);
    }

    public function test_manager_records_supplier_email_reservation_response(): void
    {
        [$manager, , , $request] = $this->requestFixture('EMAIL-RESPONSE');
        $workflow = app(SupplierOrderRequestWorkflow::class);
        $request = $workflow->markEmailSent($request, $manager);

        $result = $workflow->recordEmailResponse(
            $request,
            'acknowledged',
            $manager,
            'Две штуки в резерве до 22.10.',
        );

        $this->assertSame('acknowledged', $result->status);
        $this->assertNotNull($result->acknowledged_at);
        $this->assertSame('Две штуки в резерве до 22.10.', $result->supplier_response_note);
        $this->assertDatabaseHas('supplier_order_request_status_histories', [
            'supplier_order_request_id' => $request->id,
            'actor_scope' => 'manager_email',
            'status_from' => 'sent',
            'status_to' => 'acknowledged',
        ]);
    }

    public function test_product_reserve_draft_is_linked_to_product_supplier_and_mail_thread(): void
    {
        $manager = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier = Supplier::query()->create([
            'code' => 'product-reserve-supplier',
            'name' => 'Поставщик товара',
            'contact' => 'reserve@supplier.test',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'name' => 'Категория резерва',
            'slug' => 'reserve-category',
            'parent_id' => 0,
        ]);
        $product = Product::query()->create([
            'category_id' => $category->id,
            'name' => 'Котёл для резерва',
            'slug' => 'reserve-product',
            'sku' => 'KOTEL-1',
            'price' => 120,
        ]);
        SupplierProduct::query()->create([
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'supplier_article' => 'SUP-001',
            'price' => 75,
            'price_byn' => 75,
            'currency' => 'BYN',
            'in_stock' => true,
            'stock_quantity' => 4,
            'match_status' => 'matched',
        ]);
        CrmMailbox::query()->create([
            'name' => 'Заказы KOTLOV',
            'email' => 'order@kotlov.by',
            'username' => 'order@kotlov.by',
            'is_enabled' => true,
        ]);

        $thread = app(SupplierReservationMailService::class)->prepareProductRequest(
            $product,
            $supplier->id,
            $manager,
            2,
            'reserve@supplier.test',
            'Поставка на наш склад.',
        );
        $draft = $thread->messages()->where('direction', 'draft')->firstOrFail();

        $this->assertSame($product->id, $thread->product_id);
        $this->assertSame($supplier->id, $thread->supplier_id);
        $this->assertSame('order@kotlov.by', $thread->mailbox->email);
        $this->assertStringContainsString('SUP-001', $draft->text_body);
        $this->assertStringContainsString('2 шт. × 75,00 BYN = 150,00 BYN', $draft->text_body);
        $this->assertStringContainsString('Остаток по нашим данным: 4 шт.', $draft->text_body);
        $this->assertSame($thread->id, $product->fresh()->latestSupplierMailThread?->id);
    }

    public function test_manager_explicitly_publishes_a_request_to_only_the_assigned_supplier_portal(): void
    {
        [$manager, $supplierA, $supplierUserA, $requestA] = $this->requestFixture('A');
        [, $supplierB, $supplierUserB, $requestB] = $this->requestFixture('B', $manager);
        [, , , $draftA] = $this->requestFixture('A-DRAFT', $manager, $supplierA, $supplierUserA);

        Livewire::actingAs($manager)
            ->test(AdminViewOrder::class, ['record' => $requestA->order_id])
            ->callAction('publishSupplierRequest', [
                'supplier_order_request_id' => $requestA->id,
                'note' => 'Подтвердите возможность поставки.',
            ])
            ->assertHasNoActionErrors();

        app(SupplierOrderRequestWorkflow::class)->publish($requestB, $manager);

        $requestA->refresh();
        $this->assertSame('sent', $requestA->status);
        $this->assertNotNull($requestA->sent_at);
        $this->assertSame($manager->id, $requestA->sent_by);
        $this->assertDatabaseHas('supplier_order_request_status_histories', [
            'supplier_order_request_id' => $requestA->id,
            'user_id' => $manager->id,
            'actor_scope' => 'manager',
            'status_from' => 'draft',
            'status_to' => 'sent',
        ]);

        $this->actingAs($supplierUserA)
            ->get(SupplierOrderRequestResource::getUrl('index', panel: 'supplier'))
            ->assertOk()
            ->assertSeeText($requestA->number)
            ->assertDontSeeText($requestB->number)
            ->assertDontSeeText($draftA->number);

        $this->actingAs($supplierUserA)
            ->get(SupplierOrderRequestResource::getUrl('view', ['record' => $requestA], panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('Принять в работу')
            ->assertSeeText('Подтвердите возможность поставки.')
            ->assertDontSeeText($requestA->order->customer_name)
            ->assertDontSeeText($requestA->order->customer_phone);

        $this->actingAs($supplierUserA)
            ->get(SupplierOrderRequestResource::getUrl('view', ['record' => $requestB], panel: 'supplier'))
            ->assertNotFound();

        $this->assertNotSame($supplierA->id, $supplierB->id);
        $this->assertNotSame($supplierUserA->id, $supplierUserB->id);
    }

    public function test_supplier_can_acknowledge_and_fulfill_own_request_with_an_audited_independent_status(): void
    {
        [$manager, , $supplierUser, $request] = $this->requestFixture('FLOW');
        [, , $foreignSupplierUser] = $this->requestFixture('FOREIGN', $manager);
        $workflow = app(SupplierOrderRequestWorkflow::class);
        $request = $workflow->publish($request, $manager, 'Нужен ответ поставщика.');

        try {
            $workflow->respond($request, 'acknowledged', $foreignSupplierUser);
            $this->fail('Чужой поставщик не должен менять заявку.');
        } catch (ValidationException) {
            $this->assertSame('sent', $request->fresh()->status);
        }

        $request = $workflow->respond($request, 'acknowledged', $supplierUser, 'Приняли, товар зарезервирован.');
        $this->assertSame('acknowledged', $request->status);
        $this->assertNotNull($request->acknowledged_at);

        $request = $workflow->respond($request, 'fulfilled', $supplierUser, 'Товар передан в доставку.');
        $this->assertSame('fulfilled', $request->status);
        $this->assertNotNull($request->fulfilled_at);
        $this->assertSame('Товар передан в доставку.', $request->supplier_response_note);
        $this->assertDatabaseCount('supplier_order_request_status_histories', 3);
        $this->assertDatabaseHas('supplier_order_request_status_histories', [
            'supplier_order_request_id' => $request->id,
            'user_id' => $supplierUser->id,
            'actor_scope' => 'supplier',
            'status_from' => 'acknowledged',
            'status_to' => 'fulfilled',
            'note' => 'Товар передан в доставку.',
        ]);

        $this->actingAs($supplierUser)
            ->get(SupplierOrderRequestResource::getUrl('view', ['record' => $request], panel: 'supplier'))
            ->assertOk()
            ->assertSeeText('Исполнена')
            ->assertSeeText('История статусов')
            ->assertSeeText('Товар передан в доставку.')
            ->assertDontSeeText('Принять в работу')
            ->assertDontSeeText('Отклонить');

        $this->actingAs($manager)
            ->get(route('filament.admin.resources.orders.view', ['record' => $request->order_id]))
            ->assertOk()
            ->assertSeeText('Ответ поставщика')
            ->assertSeeText('Товар передан в доставку.');
    }

    public function test_draft_cannot_be_published_without_an_active_supplier_portal_user(): void
    {
        [$manager, $supplier, $supplierUser, $request] = $this->requestFixture('NO-USER');
        $supplier->users()->detach($supplierUser);

        $this->expectException(ValidationException::class);
        app(SupplierOrderRequestWorkflow::class)->publish($request, $manager);
    }

    public function test_rejected_supplier_request_becomes_a_critical_manager_problem(): void
    {
        [$manager, , $supplierUser, $request] = $this->requestFixture('REJECTED');
        $workflow = app(SupplierOrderRequestWorkflow::class);
        $request = $workflow->publish($request, $manager);
        $workflow->respond($request, 'rejected', $supplierUser, 'Нет товара на складе.');

        $order = $request->order()->with('supplierOrderRequests')->firstOrFail();

        $this->assertTrue(Order::query()
            ->withOperationalProblem('supplier_request_rejected')
            ->whereKey($order->id)
            ->exists());
        $this->assertSame('critical', $order->managementSummary()['severity']);
        $this->assertTrue($order->managementSummary()['problems']->contains(
            fn (array $problem): bool => $problem['label'] === 'Поставщик отклонил заявок: 1',
        ));
        $this->assertStringContainsString('Отклонена: 1', $order->supplierRequestStatusSummary());
    }

    public function test_automatic_transfer_is_disabled_by_default_and_cannot_bypass_control_period(): void
    {
        [$admin, $supplier] = $this->requestFixture('AUTO-BLOCKED');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $readiness = app(SupplierAutoTransferReadiness::class)->snapshot($supplier);

        $this->assertFalse((bool) $supplier->automatic_order_transfer_enabled);
        $this->assertFalse($readiness['ready']);
        $this->assertContains('Успешных контрольных заявок: 0 из 3', $readiness['blockers']);

        try {
            app(SupplierAutoTransferManager::class)->setEnabled($supplier, true, $manager, 'Попытка менеджера');
            $this->fail('Менеджер не должен включать автоматическую передачу.');
        } catch (ValidationException) {
            $this->assertFalse($supplier->fresh()->automatic_order_transfer_enabled);
        }

        $this->expectException(ValidationException::class);
        app(SupplierAutoTransferManager::class)->setEnabled($supplier, true, $admin, 'Контроль ещё не пройден');
    }

    public function test_admin_enables_automatic_transfer_after_control_period_and_command_is_idempotent(): void
    {
        $this->travelTo(now()->startOfDay());
        [$admin, $supplier, $supplierUser, $first] = $this->requestFixture('AUTO-1');
        [, , , $second] = $this->requestFixture('AUTO-2', $admin, $supplier, $supplierUser);
        [, , , $third] = $this->requestFixture('AUTO-3', $admin, $supplier, $supplierUser);

        foreach ([$first, $second, $third] as $index => $request) {
            $request->forceFill([
                'status' => 'fulfilled',
                'transfer_mode' => 'manual',
                'sent_at' => now()->subDays(9 - $index),
                'acknowledged_at' => now()->subDays(8 - $index),
                'fulfilled_at' => now()->subDays(7 - $index),
            ])->save();
        }

        $readiness = app(SupplierAutoTransferReadiness::class)->snapshot($supplier);
        $this->assertTrue($readiness['ready']);
        $this->assertSame(3, $readiness['successful_count']);
        $this->assertSame(0, $readiness['rejected_count']);

        app(SupplierAutoTransferManager::class)->setEnabled(
            $supplier,
            true,
            $admin,
            'Три успешные ручные заявки за контрольный период.',
        );
        $this->assertTrue($supplier->fresh()->automatic_order_transfer_enabled);
        $this->assertDatabaseHas('supplier_auto_transfer_decisions', [
            'supplier_id' => $supplier->id,
            'user_id' => $admin->id,
            'enabled' => true,
        ]);

        [, , , $automaticDraft] = $this->requestFixture('AUTO-NEXT', $admin, $supplier, $supplierUser);
        [, $disabledSupplier, , $disabledDraft] = $this->requestFixture('AUTO-DISABLED', $admin);

        $this->artisan('orders:auto-publish-supplier-requests')->assertSuccessful();
        $this->assertSame('draft', $automaticDraft->fresh()->status);

        $this->artisan('orders:auto-publish-supplier-requests --apply')->assertSuccessful();
        $automaticDraft->refresh();
        $this->assertSame('sent', $automaticDraft->status);
        $this->assertSame('automatic', $automaticDraft->transfer_mode);
        $this->assertNotNull($automaticDraft->automatic_transfer_run_uuid);
        $this->assertNull($automaticDraft->sent_by);
        $this->assertSame('draft', $disabledDraft->fresh()->status);
        $this->assertFalse((bool) $disabledSupplier->automatic_order_transfer_enabled);
        $this->assertDatabaseHas('supplier_order_request_status_histories', [
            'supplier_order_request_id' => $automaticDraft->id,
            'actor_scope' => 'system',
            'status_from' => 'draft',
            'status_to' => 'sent',
            'run_uuid' => $automaticDraft->automatic_transfer_run_uuid,
        ]);

        $historyCount = $automaticDraft->statusHistories()->count();
        $this->artisan('orders:auto-publish-supplier-requests --apply')->assertSuccessful();
        $this->assertSame($historyCount, $automaticDraft->statusHistories()->count());

        $this->actingAs($admin)
            ->get(SupplierResource::getUrl('edit', ['record' => $supplier], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Автоматическая передача заказов')
            ->assertSeeText('Включена')
            ->assertSeeText('Отключить автопередачу');

        $this->travelBack();
    }

    /** @return array{User, Supplier, User, SupplierOrderRequest} */
    private function requestFixture(
        string $suffix,
        ?User $manager = null,
        ?Supplier $supplier = null,
        ?User $supplierUser = null,
    ): array {
        $manager ??= User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $supplier ??= Supplier::query()->create([
            'code' => 'request-supplier-'.strtolower($suffix),
            'name' => 'Поставщик '.$suffix,
            'contact' => 'supply-'.$suffix.'@example.test',
            'is_active' => true,
        ]);
        $supplierUser ??= User::factory()->create([
            'role' => 'supplier',
            'is_active' => true,
            'name' => 'Пользователь '.$suffix,
        ]);
        if (! $supplier->users()->whereKey($supplierUser->id)->exists()) {
            $supplier->users()->attach($supplierUser);
        }

        $order = Order::query()->create([
            'number' => 'ORD-REQUEST-'.$suffix,
            'status' => 'new',
            'customer_name' => 'Скрытый покупатель '.$suffix,
            'customer_phone' => '+37529000'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'delivery_type' => 'pickup',
            'delivery_price' => 0,
            'payment_type' => 'cash',
            'payment_status' => 'pending',
            'subtotal' => 120,
            'discount' => 0,
            'total' => 120,
        ]);
        $orderItem = OrderItem::query()->create([
            'order_id' => $order->id,
            'product_name' => 'Товар заявки '.$suffix,
            'product_sku' => 'REQUEST-'.$suffix,
            'price' => 120,
            'quantity' => 1,
            'total' => 120,
        ]);
        $request = SupplierOrderRequest::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $supplier->id,
            'created_by' => $manager->id,
            'number' => 'SR-REQUEST-'.$suffix,
            'route' => 'supplier_purchase',
            'status' => 'draft',
            'supplier_name' => $supplier->name,
            'supplier_contact' => $supplier->contact,
            'item_count' => 1,
            'purchase_total' => 80,
        ]);
        SupplierOrderRequestItem::query()->create([
            'supplier_order_request_id' => $request->id,
            'order_item_id' => $orderItem->id,
            'product_name' => $orderItem->product_name,
            'product_sku' => $orderItem->product_sku,
            'quantity' => 1,
            'purchase_price' => 80,
            'purchase_total' => 80,
        ]);

        return [$manager, $supplier, $supplierUser, $request];
    }
}

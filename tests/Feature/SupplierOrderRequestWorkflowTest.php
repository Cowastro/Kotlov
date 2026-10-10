<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder as AdminViewOrder;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Supplier\Resources\SupplierOrderRequests\SupplierOrderRequestResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierOrderRequestItem;
use App\Models\User;
use App\Services\Orders\SupplierAutoTransferManager;
use App\Services\Orders\SupplierAutoTransferReadiness;
use App\Services\Orders\SupplierOrderRequestWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierOrderRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

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

<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder as AdminViewOrder;
use App\Filament\Supplier\Resources\SupplierOrderRequests\SupplierOrderRequestResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\SupplierOrderRequest;
use App\Models\SupplierOrderRequestItem;
use App\Models\User;
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

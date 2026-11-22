<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Orders\InternalOrderManager;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InternalOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $actor = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($actor);
        $supplier = Supplier::create(['name' => 'Чистый берег', 'code' => 'bereg-test', 'is_active' => true]);
        $order = Order::create(['number' => 'ORD-INTERNAL-TEST', 'status' => 'new',
            'customer_name' => 'Монтажник', 'customer_phone' => '+375291234567', 'payment_type' => 'cash',
            'payment_status' => 'unpaid', 'delivery_type' => 'pickup', 'subtotal' => 340, 'total' => 340]);
        foreach (range(1, 17) as $index) {
            $order->items()->create(['product_name' => 'Фитинг '.$index, 'product_sku' => 'FIT-'.$index,
                'quantity' => 2, 'price' => 10, 'total' => 20, 'supply_supplier_id' => $supplier->id,
                'supply_purchase_price' => 7, 'supply_stock_quantity' => 130, 'supply_is_available' => null,
                'supply_captured_at' => now()]);
        }

        return [$actor, $order];
    }

    public function test_one_confirmation_and_one_completion_for_seventeen_lines_preserve_commercial_data(): void
    {
        Mail::fake();
        [$actor, $order] = $this->fixture();
        $first = $order->items()->first();
        $first->update(['fulfillment_confirmed_at' => now()->subDay(), 'fulfillment_confirmed_by' => $actor->id,
            'fulfillment_purchase_price' => 6, 'fulfillment_availability_status' => 'reserved']);
        $before = $order->items()->get(['id', 'quantity', 'price', 'total', 'supply_stock_quantity', 'supply_is_available'])->toArray();
        config(['services.sms_by.enabled' => true, 'services.sms_by.token' => 'test-token',
            'services.sms_by.alphaname_id' => '42']);
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('confirmInternalOrder')->assertHasNoErrors();
        $order->refresh();
        $this->assertTrue($order->is_internal);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(17, $order->items()->whereNotNull('fulfillment_confirmed_at')->count());
        $this->assertSame('reserved', $first->fresh()->fulfillment_availability_status);
        $this->assertSame('6.00', $first->fresh()->fulfillment_purchase_price);
        $this->assertSame(16, $order->fulfillmentHistory()->count());
        app(InternalOrderManager::class)->confirm($order, $actor);
        $this->assertSame(16, $order->fulfillmentHistory()->count());
        Livewire::test(ViewOrder::class, ['record' => $order->id])->callAction('completeInternalOrder')->assertHasNoErrors();
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('completed', $order->effectiveDealStage());
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('340.00', $order->total);
        $this->assertNull($order->onec_order_guid);
        $this->assertSame($before, $order->items()->get(['id', 'quantity', 'price', 'total', 'supply_stock_quantity', 'supply_is_available'])->toArray());
        Mail::assertNothingOutgoing();
        $this->assertSame(0, $order->statusSms()->count());
    }

    public function test_missing_supplier_rolls_back_the_entire_confirmation(): void
    {
        [$actor, $order] = $this->fixture();
        $order->items()->latest('id')->first()->update(['supply_supplier_id' => null]);
        try {
            app(InternalOrderManager::class)->confirm($order, $actor);
            $this->fail('Expected missing supplier validation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('поставщика', $exception->getMessage());
        }
        $this->assertFalse($order->fresh()->is_internal);
        $this->assertSame('new', $order->fresh()->status);
        $this->assertSame(0, $order->items()->whereNotNull('fulfillment_confirmed_at')->count());
        $this->assertSame(0, $order->fulfillmentHistory()->count());
    }

    public function test_regular_order_cannot_be_closed_using_the_internal_shortcut(): void
    {
        [$actor, $order] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(InternalOrderManager::class)->complete($order, $actor);
    }

    public function test_customer_cannot_confirm_internal_orders(): void
    {
        [, $order] = $this->fixture();
        $customer = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->expectException(HttpException::class);
        app(InternalOrderManager::class)->confirm($order, $customer);
    }

    public function test_crm_manager_can_confirm_but_inactive_manager_cannot(): void
    {
        [, $order] = $this->fixture();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $this->actingAs($manager);
        app(InternalOrderManager::class)->confirm($order, $manager);
        $this->assertSame(17, $order->items()->where('fulfillment_confirmed_by', $manager->id)->count());
        $manager->update(['is_active' => false]);
        $this->expectException(HttpException::class);
        app(InternalOrderManager::class)->complete($order->fresh(), $manager);
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\DealByOrders\Pages\ViewDealByOrder;
use App\Filament\Resources\DealByOrders\Pages\ListDealByOrders;
use App\Models\DealByOrderInbox;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class DealByOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        Http::preventStrayRequests();
        config(['onec.order_queue_enabled' => false, 'app.url' => 'https://kotlov.by']);
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_active' => true]));
    }

    public function test_deal_by_actions_move_through_the_workflow_and_keep_history(): void
    {
        $order = $this->order();
        foreach (['contacted', 'agreed'] as $stage) {
            $this->page($order)->mountAction('manageDeal')->setActionData([
                'stage' => $stage, 'manager_id' => auth()->id(), 'next_action' => 'Согласовать заказ',
                'control_at' => now()->addDay()->toDateTimeString(),
            ])->callMountedAction()->assertHasNoActionErrors();
            $this->assertSame($stage, $order->fresh()->effectiveDealStage());
        }
        $this->page($order)->callAction('advanceWorkflow')->assertHasNoActionErrors();
        $this->assertSame('checking', $order->fresh()->status);
        $this->page($order)->assertActionDisabled('advanceWorkflow');
        $order->items()->update(['fulfillment_confirmed_at' => now(), 'fulfillment_route' => 'own_stock',
            'fulfillment_availability_status' => 'available']);
        $this->page($order)->callAction('advanceWorkflow')->assertHasNoActionErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        foreach (['processing' => 'processing', 'handover' => 'shipped', 'completed' => 'delivered'] as $stage => $status) {
            $this->page($order)->mountAction('manageDeal')->setActionData([
                'stage' => $stage, 'manager_id' => auth()->id(),
                'next_action' => $stage === 'completed' ? '' : 'Контроль доставки',
                'control_at' => $stage === 'completed' ? null : now()->addDay()->toDateTimeString(),
            ])->callMountedAction()->assertHasNoActionErrors();
            $this->assertSame($status, $order->fresh()->status);
        }
        $this->assertSame('completed', $order->fresh()->effectiveDealStage());
        $this->assertNull($order->fresh()->deal_control_at);
        foreach (['contacted', 'agreed', 'processing', 'handover', 'completed'] as $stage) {
            $this->assertTrue($order->statusHistory()->where('deal_stage_to', $stage)->where('user_id', auth()->id())->exists());
        }
        $this->page($order)->assertActionEnabled('customer_review_sms');
        Http::assertNothingSent();
    }

    public function test_responsible_manager_can_be_changed_and_inactive_users_are_rejected(): void
    {
        $order = $this->order();
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        Livewire::test(ListDealByOrders::class)->set('activeTab', 'all')
            ->mountTableAction('assignManager', $order)->setTableActionData(['manager_id' => $manager->id])
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertSame($manager->id, $order->fresh()->manager_id);
        $this->assertTrue($order->managerComments()->where('user_id', auth()->id())
            ->where('body', 'like', '%'.$manager->name.'%')->exists());
        $other = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $page = $this->page($order)->mountAction('assignManager')->setActionData(['manager_id' => $other->id]);
        $other->update(['is_active' => false]);
        $page->callMountedAction()->assertHasActionErrors(['manager_id']);
        $this->assertSame($manager->id, $order->fresh()->manager_id);
        $this->assertSame(1, $order->managerComments()->count());
    }

    public function test_review_sms_success_retry_invalid_phone_and_provider_failure_are_handled_in_modal(): void
    {
        config(['services.sms_by.enabled' => true, 'services.sms_by.token' => 'test-token',
            'services.sms_by.alphaname_id' => '123', 'services.sms_by.base_url' => 'https://app.sms.by']);
        $order = $this->order();
        $this->page($order)->assertActionDisabled('customer_review_sms');
        $order->update(['status' => 'delivered']);
        $this->page($order)->mountAction('customer_review_sms')->setActionData(['phone' => '123'])
            ->callMountedAction()->assertHasActionErrors(['phone']);
        Http::assertNothingSent();
        $rejectSms = true;
        Http::fake(['app.sms.by/*' => function () use (&$rejectSms) {
            return Http::response($rejectSms ? ['status' => 'error', 'message' => 'No balance'] : ['sms_id' => 'deal-review-test']);
        }]);
        $this->page($order)->mountAction('customer_review_sms')->setActionData(['phone' => $order->customer_phone])
            ->callMountedAction()->assertHasActionErrors(['phone']);
        $this->assertNull($order->fresh()->review_sms_sent_at);
        $this->travel(2)->minutes();
        $rejectSms = false;
        Livewire::test(ListDealByOrders::class)->set('activeTab', 'all')
            ->mountTableAction('customer_review_sms', $order)->setTableActionData(['phone' => $order->customer_phone])
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertSame('deal-review-test', $order->fresh()->review_sms_id);
        $history = $order->statusHistory()->where('comment', 'like', '%deal-review-test%')->sole();
        $this->assertSame(auth()->id(), $history->user_id);
        $this->page($order)->mountAction('customer_review_sms')->setActionData(['phone' => $order->customer_phone])
            ->callMountedAction()->assertHasActionErrors(['phone']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'sendQuickSMS')
            && mb_strlen($request['message']) <= 70);
        $this->assertSame('delivered', $order->fresh()->status);
    }

    private function page(Order $order)
    {
        return Livewire::test(ViewDealByOrder::class, ['record' => $order->id]);
    }

    private function order(): Order
    {
        $order = Order::create(['number' => 'DBY-ACTIONS', 'status' => 'new', 'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291234567', 'delivery_type' => 'pickup', 'payment_type' => 'cash',
            'payment_status' => 'pending', 'subtotal' => 100, 'total' => 100]);
        $order->items()->create(['product_name' => 'Тестовый товар', 'quantity' => 1, 'price' => 100, 'total' => 100]);
        DealByOrderInbox::create(['remote_order_id' => 'test-actions', 'order_id' => $order->id,
            'first_seen_at' => now(), 'last_seen_at' => now(), 'payload_hash' => hash('sha256', 'test'), 'payload' => []]);

        return $order;
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\OneCAgentTask;
use App\Models\Order;
use App\Models\User;
use App\Services\Integrations\OneCBridge\DocumentStatusTaskHandler;
use App\Services\Integrations\OneCBridge\ODataReadTaskHandler;
use App\Services\Integrations\OneCBridge\OneCOrderJourney;
use App\Services\Integrations\OneCBridge\OneCPostingReminder;
use App\Services\Integrations\OneCBridge\OrderTaskHandler;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OneCPostingReminderTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        Mail::fake();

        return Order::create(['number' => 'POST-'.Order::count(), 'status' => 'processing',
            'customer_name' => 'Клиент', 'customer_phone' => '+375291110000',
            'delivery_type' => 'courier', 'payment_type' => 'cash', 'payment_status' => 'pending',
            'subtotal' => 100, 'total' => 100, 'onec_order_guid' => 'aaaaaaaa-0000-0000-0000-000000000001',
            'onec_order_number' => 'TEST-1C', 'onec_exported_at' => now()->subDays(90)]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'is_active' => true]);
    }

    public function test_export_creates_one_internal_reminder_and_read_reminders_do_not_repeat(): void
    {
        $manager = $this->manager();
        $order = $this->order();
        $order->forceFill(['manager_id' => $manager->id])->save();
        $task = OneCAgentTask::create(['type' => 'onec_order', 'title' => 'Create', 'status' => 'sending',
            'subject_type' => $order->getMorphClass(), 'subject_id' => $order->id, 'payload' => []]);
        app(OrderTaskHandler::class)->finished($task, ['ok' => true, 'data' => [
            'document_key' => $order->onec_order_guid, 'document_number' => $order->onec_order_number,
        ]]);
        $this->assertFalse($order->fresh()->onec_posted);
        $reminder = $manager->notifications()->sole();
        $this->assertSame($order->id, $reminder->data['onec_posting_order_id']);
        $this->assertSame('Провести в 1С: TEST-1C', $reminder->data['title']);
        $reminder->markAsRead();
        app(OneCPostingReminder::class)->sync($order->fresh());
        app(OneCPostingReminder::class)->syncOpen();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertNotNull($reminder->fresh()->read_at);
        Mail::assertNothingQueued();
        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_reassignment_moves_reminder_and_cancelled_orders_retire_it(): void
    {
        $order = $this->order();
        $first = $this->manager();
        $second = $this->manager();
        $order->forceFill(['manager_id' => $first->id])->save();
        $service = app(OneCPostingReminder::class);
        $service->sync($order);
        $order->forceFill(['manager_id' => $second->id])->save();
        $order->unsetRelation('manager');
        $service->sync($order);
        $this->assertSame(0, $first->unreadNotifications()->count());
        $this->assertSame(1, $second->unreadNotifications()->count());
        $order->forceFill(['status' => 'cancelled'])->save();
        $service->syncOpen();
        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
    }

    public function test_missing_responsible_falls_back_to_active_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $inactive = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $client = User::factory()->create(['is_active' => true]);
        $order = $this->order();
        $order->forceFill(['manager_id' => $client->id])->save();
        app(OneCPostingReminder::class)->sync($order);
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $client->notifications()->count());
    }

    public function test_posted_result_retires_reminder_and_late_unposted_result_cannot_restore_it(): void
    {
        Storage::fake('local');
        $order = $this->order();
        $order->forceFill(['manager_id' => $this->manager()->id])->save();
        app(OneCPostingReminder::class)->sync($order);
        $handler = app(DocumentStatusTaskHandler::class);
        $task = $handler->enqueueOrderCheck($order, null);
        $task->forceFill(['status' => 'sending', 'picked_at' => now(), 'payload' => array_merge($task->payload, ['parts' => ['orders_1' => ['code' => 200]]])])->save();
        Storage::disk('local')->put(ODataReadTaskHandler::file($task, 'orders_1'), json_encode(['value' => [[
            'Ref_Key' => $order->onec_order_guid, 'Posted' => true, 'DeletionMark' => false,
        ]]]));
        $handler->finished($task, ['ok' => true]);
        $this->assertTrue($order->fresh()->onec_posted);
        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
        $task->forceFill(['picked_at' => now()->subHour()])->save();
        Storage::disk('local')->put(ODataReadTaskHandler::file($task, 'orders_1'), json_encode(['value' => [[
            'Ref_Key' => $order->onec_order_guid, 'Posted' => false,
        ]]]));
        $handler->finished($task, ['ok' => true]);
        $this->assertTrue($order->fresh()->onec_posted);
        $this->assertSame(0, DatabaseNotification::whereNull('read_at')->count());
        Mail::assertNothingQueued();
    }

    public function test_employee_action_is_visible_and_only_queues_read_for_this_document_even_if_old(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager);
        $order = $this->order();
        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertActionHasLabel('onecOrder', 'Провести в 1С: TEST-1C')
            ->callAction([TestAction::make('onecOrder'), TestAction::make('onecCheckPosting')])
            ->assertHasNoActionErrors()->assertNotified('Проверка проведения поставлена в очередь');
        $task = OneCAgentTask::sole();
        $this->assertSame('doc_status', $task->type);
        $this->assertSame(['orders_1'], array_keys($task->payload['queries']));
        $query = urldecode($task->payload['queries']['orders_1']);
        $this->assertStringContainsString($order->onec_order_guid, $query);
        $this->assertStringNotContainsString('Post()', $query);
        $this->assertNull(app(DocumentStatusTaskHandler::class)->enqueueOrderCheck($order, $manager));
        $this->assertStringContainsString('проведите его', app(OneCOrderJourney::class)->next($order));
        $this->assertFalse((bool) config('onec.supply_sync_enabled'));
        Mail::assertNothingQueued();
    }
}

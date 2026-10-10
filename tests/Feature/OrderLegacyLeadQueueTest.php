<?php

namespace Tests\Feature;

use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class OrderLegacyLeadQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_historical_queue_contains_old_and_pre_launch_new_unpaid_orders(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        config()->set('shop.order_management.operations_started_at', '2026-10-10 00:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $oldLead = $this->order('ORD-OLD-LEAD', 'new', 'pending', now()->subDays(45));
        $preLaunchLead = $this->order('ORD-PRE-LAUNCH', 'new', 'pending', now()->subDay());
        $recentLead = $this->order('ORD-RECENT-LEAD', 'new', 'pending', now()->subHours(2));
        $paidOrder = $this->order('ORD-PAID-OLD', 'new', 'paid', now()->subDays(60));
        $confirmedOrder = $this->order('ORD-CONFIRMED-OLD', 'confirmed', 'pending', now()->subDays(60));

        $this->assertSame(
            [$oldLead->id, $preLaunchLead->id],
            Order::query()->historicalUnprocessed()->orderBy('id')->pluck('id')->all(),
        );
        $this->assertTrue($oldLead->isStaleUnprocessed());
        $this->assertTrue($preLaunchLead->isHistoricalUnprocessed());
        $this->assertFalse($recentLead->isStaleUnprocessed());

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'historical_leads')
            ->assertCanSeeTableRecords([$oldLead, $preLaunchLead])
            ->assertCanNotSeeTableRecords([$recentLead, $paidOrder, $confirmedOrder]);
    }

    public function test_historical_leads_do_not_pollute_current_work_and_attention_queues(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        config()->set('shop.order_management.operations_started_at', '2026-10-10 00:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $historical = $this->order('ORD-HISTORICAL', 'new', 'pending', now()->subDay());
        $current = $this->order('ORD-CURRENT', 'new', 'pending', now()->subHour());

        $this->assertSame('historical', $historical->managementSummary()['severity']);
        $this->assertSame('Историческая заявка', $historical->managementSummary()['attention_label']);
        $this->assertSame(0, $historical->managementSummary()['problem_count']);
        $this->assertSame('historical', $historical->onecSyncState());
        $this->assertSame('Вне рабочего обмена', $historical->onecSyncLabel());
        $this->assertStringContainsString('не требует передачи', $historical->onecSyncDescription());
        $this->assertFalse(Order::query()->operationallyActive()->whereKey($historical)->exists());
        $this->assertTrue(Order::query()->operationallyActive()->whereKey($current)->exists());
        $this->assertFalse(Order::query()->withOperationalProblem('needs_attention')->whereKey($historical)->exists());
        $this->assertTrue(Order::query()->withOperationalProblem('needs_attention')->whereKey($current)->exists());

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'work_queue')
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$historical]);
    }

    public function test_manager_can_mark_a_lead_irrelevant_without_deleting_it(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $lead = $this->order('ORD-IRRELEVANT', 'new', 'pending', now()->subDays(45));

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'historical_leads')
            ->callTableAction('markIrrelevant', $lead, ['reason' => 'Клиент подтвердил, что заявка больше не актуальна'])
            ->assertHasNoTableActionErrors();

        $lead->refresh();
        $this->assertSame('cancelled', $lead->status);
        $this->assertNotNull($lead->archived_at);
        $this->assertSame($manager->id, $lead->archived_by);
        $this->assertSame('Клиент подтвердил, что заявка больше не актуальна', $lead->archive_reason);
        $this->assertStringContainsString('Неактуальная заявка:', (string) $lead->admin_comment);
        $this->assertDatabaseHas('orders', ['id' => $lead->id]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $lead->id,
            'user_id' => $manager->id,
            'status_from' => 'new',
            'status_to' => 'cancelled',
            'comment' => 'Клиент подтвердил, что заявка больше не актуальна',
        ]);
    }

    public function test_manager_can_close_the_entire_historical_queue_without_deleting_orders(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        config()->set('shop.order_management.operations_started_at', '2026-10-10 00:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $historicalOne = $this->order('ORD-HISTORY-ONE', 'new', 'pending', now()->subDays(10));
        $historicalTwo = $this->order('ORD-HISTORY-TWO', 'new', 'pending', now()->subDay());
        $current = $this->order('ORD-CURRENT-KEEP', 'new', 'pending', now()->subHour());

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'historical_leads')
            ->callAction('archiveHistorical', [
                'reason' => 'Архивная заявка до запуска нового рабочего процесса',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('cancelled', $historicalOne->fresh()->status);
        $this->assertSame('cancelled', $historicalTwo->fresh()->status);
        $this->assertNotNull($historicalOne->fresh()->archived_at);
        $this->assertNotNull($historicalTwo->fresh()->archived_at);
        $this->assertSame('new', $current->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $historicalOne->id]);
        $this->assertDatabaseHas('orders', ['id' => $historicalTwo->id]);
        $this->assertDatabaseCount('order_status_history', 2);
        $this->assertSame(0, Order::query()->historicalUnprocessed()->count());
    }

    public function test_bulk_action_skips_paid_and_already_processed_orders(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $lead = $this->order('ORD-BULK-LEAD', 'new', 'pending', now()->subDays(50));
        $paid = $this->order('ORD-BULK-PAID', 'new', 'paid', now()->subDays(50));
        $confirmed = $this->order('ORD-BULK-CONFIRMED', 'confirmed', 'pending', now()->subDays(50));

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'all')
            ->callTableBulkAction('markIrrelevant', [$lead, $paid, $confirmed], [
                'reason' => 'Архивная заявка до запуска рабочего процесса',
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame('cancelled', $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->archived_at);
        $this->assertSame('new', $paid->fresh()->status);
        $this->assertSame('confirmed', $confirmed->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $lead->id]);
        $this->assertDatabaseHas('orders', ['id' => $paid->id]);
        $this->assertDatabaseHas('orders', ['id' => $confirmed->id]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $lead->id,
            'status_from' => 'new',
            'status_to' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('order_status_history', ['order_id' => $paid->id]);
        $this->assertDatabaseMissing('order_status_history', ['order_id' => $confirmed->id]);
    }

    public function test_archived_leads_have_a_separate_queue_and_cannot_be_archived_twice(): void
    {
        Carbon::setTestNow('2026-10-10 12:00:00');
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $lead = $this->order('ORD-ARCHIVE-QUEUE', 'new', 'pending', now()->subDays(50));

        $this->actingAs($manager);
        $this->assertTrue($lead->markIrrelevant('Старая заявка, клиенту уже не требуется товар'));
        $this->assertFalse($lead->fresh()->markIrrelevant('Повторное архивирование'));

        Livewire::actingAs($manager)
            ->test(ListOrders::class)
            ->set('activeTab', 'archived')
            ->assertCanSeeTableRecords([$lead]);

        $this->assertSame(1, Order::query()->archived()->count());
        $this->assertSame(0, Order::query()->historicalUnprocessed()->count());
        $this->assertDatabaseCount('order_status_history', 1);
    }

    private function order(string $number, string $status, string $paymentStatus, Carbon $createdAt): Order
    {
        $order = Order::query()->create([
            'number' => $number,
            'status' => $status,
            'customer_name' => 'Тестовый клиент',
            'customer_phone' => '+375291110000',
            'delivery_type' => 'pickup',
            'payment_type' => 'cash',
            'payment_status' => $paymentStatus,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $order;
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\OneCAgentTasks\Pages\ListOneCAgentTasks;
use App\Models\IntegrationSource;
use App\Models\OneCAgentTask;
use App\Models\OneCAgentToken;
use App\Models\OneCReference;
use App\Models\User;
use App\Services\Integrations\OneCBridge\OneCReadWatchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneCReadWatchdogTest extends TestCase
{
    use RefreshDatabase;

    private function ready(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        config(['onec.read_watchdog_enabled' => true, 'onec.supply_sync_enabled' => false]);
        IntegrationSource::where('code', 'onec')->update(['is_active' => true]);
        OneCReference::create(['kind' => 'warehouse', 'ref_key' => 'aaaaaaaa-0000-0000-0000-000000000002',
            'description' => 'Основной', 'is_folder' => false, 'deletion_mark' => false]);
    }

    public function test_online_agent_recovers_due_reads_once_without_queueing_native_writes(): void
    {
        $this->ready();
        [, $key] = OneCAgentToken::issue('watchdog-test', null);
        $headers = ['Authorization' => 'Bearer '.$key, 'X-Kotlov-Agent' => '19'];
        $this->withHeaders($headers)->get('/1c/agent/tasks/next')->assertOk()->assertSee('odata_read');
        $this->assertSame(1, OneCAgentTask::where('payload->stock', true)->count());
        $this->assertSame(1, OneCAgentTask::where('payload->prices', true)->count());
        app(OneCReadWatchdog::class)->enqueueDue();
        $this->assertSame(1, OneCAgentTask::where('payload->stock', true)->count());
        $this->assertSame(0, OneCAgentTask::whereIn('type', ['onec_order_supply', 'onec_order', 'price_batch'])->count());
    }

    public function test_recent_read_is_not_repeated_and_night_poll_does_not_generate_work(): void
    {
        $this->ready();
        OneCAgentTask::create(['type' => 'odata_read', 'title' => 'Recent stock', 'status' => 'done', 'payload' => ['stock' => true]]);
        app(OneCReadWatchdog::class)->enqueueDue();
        $this->assertSame(1, OneCAgentTask::where('payload->stock', true)->count());
        $count = OneCAgentTask::count();
        $this->travelTo(now()->setTime(23, 0));
        app(OneCReadWatchdog::class)->enqueueDue();
        $this->assertSame($count, OneCAgentTask::count());
    }

    public function test_manager_can_request_a_read_of_stock_from_bridge_page(): void
    {
        $this->ready();
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
        Livewire::test(ListOneCAgentTasks::class)
            ->callAction('syncStock')->assertNotified();
        $task = OneCAgentTask::sole();
        $this->assertSame('odata_read', $task->type);
        $this->assertTrue($task->payload['stock']);
        $this->assertSame(['stock', 'reserves'], array_keys($task->payload['queries']));
    }
}

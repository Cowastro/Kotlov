<?php

namespace Tests\Feature;

use App\Models\IntegrationMonitorHeartbeat;
use App\Services\Integrations\IntegrationIssueDetector;
use App\Services\Integrations\IntegrationIssueNotificationService;
use App\Services\Integrations\IntegrationMonitorHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class IntegrationMonitorHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_scan_records_a_successful_heartbeat_with_metrics(): void
    {
        $this->mock(IntegrationIssueDetector::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'detected' => 7,
                'opened' => 2,
                'resolved' => 1,
                'opened_issue_ids' => [10, 11],
            ]);
        $this->mock(IntegrationIssueNotificationService::class)
            ->shouldReceive('notifyOpened')
            ->once()
            ->with([10, 11])
            ->andReturn(1);

        $this->artisan('integration:scan-issues')->assertSuccessful();

        $heartbeat = IntegrationMonitorHeartbeat::query()->firstOrFail();
        $this->assertSame('success', $heartbeat->status);
        $this->assertNotNull($heartbeat->started_at);
        $this->assertNotNull($heartbeat->finished_at);
        $this->assertSame(7, $heartbeat->summary['detected']);
        $this->assertSame(1, $heartbeat->summary['notified']);
        $this->assertSame('healthy', app(IntegrationMonitorHealth::class)->snapshot()['health']);
    }

    public function test_issue_scan_records_failure_and_returns_a_failure_code(): void
    {
        $this->mock(IntegrationIssueDetector::class)
            ->shouldReceive('scan')
            ->once()
            ->andThrow(new RuntimeException('Database unavailable'));
        $this->mock(IntegrationIssueNotificationService::class)
            ->shouldNotReceive('notifyOpened');

        $this->artisan('integration:scan-issues')->assertFailed();

        $heartbeat = IntegrationMonitorHeartbeat::query()->firstOrFail();
        $this->assertSame('failed', $heartbeat->status);
        $this->assertSame('Database unavailable', $heartbeat->error_message);
        $this->assertSame('failed', app(IntegrationMonitorHealth::class)->snapshot()['health']);
    }

    public function test_monitor_health_detects_a_stopped_scheduler(): void
    {
        IntegrationMonitorHeartbeat::query()->create([
            'name' => IntegrationMonitorHeartbeat::ISSUE_SCANNER,
            'status' => 'success',
            'started_at' => now()->subMinutes(11),
            'finished_at' => now()->subMinutes(10),
        ]);

        $snapshot = app(IntegrationMonitorHealth::class)->snapshot(now());

        $this->assertSame('stale', $snapshot['health']);
        $this->assertSame('Планировщик не подтверждал работу более 3 минут', $snapshot['message']);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\IntegrationMonitorHeartbeat;
use App\Services\Integrations\IntegrationIssueDetector;
use App\Services\Integrations\IntegrationIssueNotificationService;
use Illuminate\Console\Command;
use Throwable;

class ScanIntegrationIssuesCommand extends Command
{
    protected $signature = 'integration:scan-issues';

    protected $description = 'Detect and reconcile actionable integration, catalogue and 1C order issues.';

    public function handle(
        IntegrationIssueDetector $detector,
        IntegrationIssueNotificationService $notifications,
    ): int {
        $startedAt = now();
        $startedNs = hrtime(true);
        $heartbeat = IntegrationMonitorHeartbeat::query()->updateOrCreate(
            ['name' => IntegrationMonitorHeartbeat::ISSUE_SCANNER],
            [
                'status' => 'running',
                'started_at' => $startedAt,
                'finished_at' => null,
                'duration_ms' => null,
                'summary' => null,
                'error_message' => null,
            ],
        );

        try {
            $stats = $detector->scan();
            $notified = $notifications->notifyOpened($stats['opened_issue_ids']);
            $summary = [...$stats, 'notified' => $notified];
            $heartbeat->update([
                'status' => 'success',
                'finished_at' => now(),
                'duration_ms' => $this->durationMs($startedNs),
                'summary' => $summary,
                'error_message' => null,
            ]);
            $this->info(sprintf(
                'Detected: %d; newly opened: %d; resolved: %d; important notifications: %d.',
                $stats['detected'],
                $stats['opened'],
                $stats['resolved'],
                $notified,
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $heartbeat->update([
                'status' => 'failed',
                'finished_at' => now(),
                'duration_ms' => $this->durationMs($startedNs),
                'error_message' => $exception->getMessage(),
            ]);
            $this->error('Integration monitor failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function durationMs(int $startedNs): int
    {
        return max(0, (int) round((hrtime(true) - $startedNs) / 1_000_000));
    }
}

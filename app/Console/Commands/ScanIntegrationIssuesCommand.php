<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationIssueDetector;
use App\Services\Integrations\IntegrationIssueNotificationService;
use Illuminate\Console\Command;

class ScanIntegrationIssuesCommand extends Command
{
    protected $signature = 'integration:scan-issues';

    protected $description = 'Detect and reconcile actionable integration, catalogue and 1C order issues.';

    public function handle(
        IntegrationIssueDetector $detector,
        IntegrationIssueNotificationService $notifications,
    ): int {
        $stats = $detector->scan();
        $notified = $notifications->notifyOpened($stats['opened_issue_ids']);
        $this->info(sprintf(
            'Detected: %d; newly opened: %d; resolved: %d; important notifications: %d.',
            $stats['detected'],
            $stats['opened'],
            $stats['resolved'],
            $notified,
        ));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Integrations\IntegrationIssueDetector;
use Illuminate\Console\Command;

class ScanIntegrationIssuesCommand extends Command
{
    protected $signature = 'integration:scan-issues';

    protected $description = 'Detect and reconcile actionable integration, catalogue and 1C order issues.';

    public function handle(IntegrationIssueDetector $detector): int
    {
        $stats = $detector->scan();
        $this->info(sprintf(
            'Detected: %d; newly opened: %d; resolved: %d.',
            $stats['detected'],
            $stats['opened'],
            $stats['resolved'],
        ));

        return self::SUCCESS;
    }
}

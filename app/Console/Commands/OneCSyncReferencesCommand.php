<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCReferences;
use DomainException;
use Illuminate\Console\Command;

/**
 * Keeps the site's copy of 1C in step: the lists the order form chooses from and the recent 1C orders, among which
 * the site finds the orders managers entered in 1C by hand. The agent does the read within 5 minutes.
 */
class OneCSyncReferencesCommand extends Command
{
    protected $signature = 'onec:sync-references
        {--orders-only : Only the 1C orders of the last days}
        {--days=60 : Days of 1C orders to read}';

    protected $description = '«Мост 1С»: queue a read-only refresh of 1C lists and recent 1C orders';

    public function handle(OneCReferences $refs): int
    {
        try {
            $task = $refs->enqueueSync(null, max(0, (int) $this->option('days')), ! $this->option('orders-only'));
            $this->line('metric.onec_sync_references.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_sync_references.queued=0');
        }

        return self::SUCCESS;
    }
}

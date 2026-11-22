<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCPurchaseCosts;
use DomainException;
use Illuminate\Console\Command;

/** The cost of cards from 1C: queues a read-only read of the posted purchases of the last days. */
class OneCSyncPurchasesCommand extends Command
{
    protected $signature = 'onec:sync-purchases {--days=10 : Days of purchases to read (365 once to fill the history)}';

    protected $description = '«Мост 1С»: queue a read of 1C purchases to update the cost of cards';

    public function handle(OneCPurchaseCosts $costs): int
    {
        try {
            $task = $costs->enqueue(null, max(1, (int) $this->option('days')));
            $this->line('metric.onec_sync_purchases.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_sync_purchases.queued=0');
        }

        return self::SUCCESS;
    }
}

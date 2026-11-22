<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCStock;
use DomainException;
use Illuminate\Console\Command;

/** Stock and reserve of the selling warehouse from 1C: queues a read-only read for the agent. */
class OneCSyncStockCommand extends Command
{
    protected $signature = 'onec:sync-stock';

    protected $description = '«Мост 1С»: queue a read of 1C stock and reserves on the selling warehouse';

    public function handle(OneCStock $stock): int
    {
        try {
            $task = $stock->enqueue(null);
            $this->line('metric.onec_sync_stock.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_sync_stock.queued=0');
        }

        return self::SUCCESS;
    }
}

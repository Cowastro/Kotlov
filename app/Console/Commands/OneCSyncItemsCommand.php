<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCNomenclature;
use DomainException;
use Illuminate\Console\Command;

/** The whole 1C «Номенклатура» on the site: queues a read-only read of every item and group. */
class OneCSyncItemsCommand extends Command
{
    protected $signature = 'onec:sync-items';

    protected $description = '«Мост 1С»: queue a read of the whole 1C nomenclature';

    public function handle(OneCNomenclature $nomenclature): int
    {
        try {
            $task = $nomenclature->enqueue(null);
            $this->line('metric.onec_sync_items.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_sync_items.queued=0');
        }

        return self::SUCCESS;
    }
}

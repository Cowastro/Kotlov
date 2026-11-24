<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCPrices;
use DomainException;
use Illuminate\Console\Command;

/** Retail and wholesale prices from 1C (read-only): the 1C retail price becomes the price of the site card. */
class OneCSyncPricesCommand extends Command
{
    protected $signature = 'onec:sync-prices {--full : Read every price record, not only those since the last read}';

    protected $description = '«Мост 1С»: queue a read of 1C retail and wholesale prices';

    public function handle(OneCPrices $prices): int
    {
        try {
            $task = $prices->enqueue(null, (bool) $this->option('full'));
            $this->line('metric.onec_sync_prices.queued='.$task->id);
        } catch (DomainException) {
            $this->line('metric.onec_sync_prices.queued=0');
        }

        return self::SUCCESS;
    }
}

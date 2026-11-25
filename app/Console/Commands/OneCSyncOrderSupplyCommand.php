<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OrderSupplyTaskHandler;
use Illuminate\Console\Command;

class OneCSyncOrderSupplyCommand extends Command
{
    protected $signature = 'onec:sync-order-supply {--limit=20} {--preview : Проверить действия без записи в 1С}';

    protected $description = 'Проверить обеспечение открытых заказов по живым остаткам 1С';

    public function handle(OrderSupplyTaskHandler $handler): int
    {
        $count = $handler->enqueueOpen((int) $this->option('limit'), (bool) $this->option('preview'));
        $this->line('metric.onec_order_supply.queued='.$count);

        return self::SUCCESS;
    }
}

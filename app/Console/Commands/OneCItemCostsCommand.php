<?php

namespace App\Console\Commands;

use App\Services\Integrations\OneCBridge\OneCItemCosts;
use Illuminate\Console\Command;

/** The cost of every 1C item from the purchases already read (the last one, or the 3-month average). */
class OneCItemCostsCommand extends Command
{
    protected $signature = 'onec:item-costs';

    protected $description = '«Мост 1С»: the cost of the 1C items from the purchases read';

    public function handle(OneCItemCosts $costs): int
    {
        $this->line('metric.onec_item_costs.items='.$costs->refresh());

        return self::SUCCESS;
    }
}

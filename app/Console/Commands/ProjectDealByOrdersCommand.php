<?php

namespace App\Console\Commands;

use App\Services\Marketplaces\DealByOrderProjector;
use Illuminate\Console\Command;

class ProjectDealByOrdersCommand extends Command
{
    protected $signature = 'deal:project-orders {--limit=500 : Maximum number of inbox records to process}';

    protected $description = 'Create operational orders from unprocessed Deal.by inbox records';

    public function handle(DealByOrderProjector $projector): int
    {
        $result = $projector->projectPending((int) $this->option('limit'));

        $this->info("Рабочих заказов создано: {$result['processed']}; ошибок: {$result['failed']}.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

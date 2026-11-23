<?php

namespace App\Console\Commands;

use App\Services\Shipping\VamExpressSync;
use Illuminate\Console\Command;

class SyncVamExpressCommand extends Command
{
    protected $signature = 'shipping:sync-vamexpress';

    protected $description = 'Прочитать свежие и привязанные заявки ВАМ Экспресс без изменения данных перевозчика';

    public function handle(VamExpressSync $sync): int
    {
        $this->info($sync->run());

        return self::SUCCESS;
    }
}

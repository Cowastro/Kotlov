<?php

namespace App\Console\Commands;

use App\Services\MembraneTankVolumeFilterSynchronizer;
use Illuminate\Console\Command;

class SyncMembraneTankVolumeFilterCommand extends Command
{
    protected $signature = 'catalog:sync-membrane-tank-volume-filter
        {--apply : Create/update the filter and product links}';

    protected $description = 'Build the membrane tank volume filter from explicit product data and model names';

    public function handle(MembraneTankVolumeFilterSynchronizer $synchronizer): int
    {
        $stats = $synchronizer->sync((bool) $this->option('apply'));

        foreach ($stats as $name => $value) {
            $this->line("metric.filter.membrane_tank_{$name}={$value}");
        }
        $this->line('metric.filter.membrane_tank_apply='.((bool) $this->option('apply') ? 1 : 0));

        if ($stats['category_found'] === 0) {
            $this->error('Membrane tank category was not found.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

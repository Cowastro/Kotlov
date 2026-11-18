<?php

namespace App\Console\Commands;

use App\Models\SelectionStockWatch;
use App\Services\SelectionStockMonitor;
use Illuminate\Console\Command;

class CheckSelectionArrivals extends Command
{
    protected $signature = 'requests:check-arrivals';

    protected $description = 'Notify accounts when previously waiting request quantities become available';

    public function handle(SelectionStockMonitor $monitor): int
    {
        $checked = 0;
        $failed = 0;
        SelectionStockWatch::where('enabled', true)->orderBy('id')->chunkById(50, function ($watches) use ($monitor, &$checked, &$failed) {
            foreach ($watches as $watch) {
                try {
                    $monitor->scan($watch);
                    $checked++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $this->error('Failed watch '.$watch->id);
                    $failed++;
                }
            }
        });
        $this->info('Checked watches: '.$checked);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

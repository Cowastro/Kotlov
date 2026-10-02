<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SyncRusklimatPricelistCommand extends Command
{
    protected $signature = 'supplier:sync-rusklimat-pricelist
        {--apply : Write price and stock changes}
        {--dry-run : Preview without writing}
        {--sheet-url= : Override the consolidated Русклимат Google Sheet URL}
        {--stock-file= : Use a local CSV/XLSX file instead of downloading}
        {--sync-retail-prices : Update products.price from РРЦ}
        {--limit= : Process only the first N source rows}';

    protected $description = 'Safe daily Русклимат price/stock sync for already linked products only.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (! $apply && ! $this->option('dry-run')) {
            $this->warn('No --apply passed: running as dry-run.');
        }

        $arguments = [
            '--only-linked' => true,
            '--no-images' => true,
        ];

        if ($apply) {
            $arguments['--apply'] = true;
        } else {
            $arguments['--dry-run'] = true;
        }

        if ((bool) $this->option('sync-retail-prices')) {
            $arguments['--fix-retail-prices'] = true;
        }

        foreach (['sheet-url', 'stock-file', 'limit'] as $option) {
            $value = $this->option($option);
            if ($value !== null && $value !== '') {
                $arguments['--'.$option] = $value;
            }
        }

        $exitCode = Artisan::call('supplier:sync-rusklimat', $arguments);
        $this->output->write(Artisan::output());

        return $exitCode;
    }
}

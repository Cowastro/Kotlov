<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\StorefrontProductSearch;
use Illuminate\Console\Command;

/**
 * Time of the smart search suggestions as a visitor and as a manager sees them, without a cache.
 *
 *   php artisan search:probe "конвектор ballu" "BEC/EVU-2000"
 */
class ProbeStorefrontSearchCommand extends Command
{
    protected $signature = 'search:probe {queries* : Search queries}';

    protected $description = 'Measure the smart search suggestion time for visitors and managers';

    public function handle(StorefrontProductSearch $search): int
    {
        foreach ($this->argument('queries') as $index => $q) {
            foreach (['pub' => false, 'int' => true] as $who => $internal) {
                $started = microtime(true);
                $query = fn () => Product::active()->notArchived()->select('id')->orderByDesc('rating')->orderByDesc('id')->limit(8);
                $found = $search->applyForSuggestions($query(), $q, $internal)->get();
                $step = 'fast';
                if ($found->isEmpty() && $search->isLongQuery($q)) {
                    $found = $search->applyBestMatch($query(), $q)->get();
                    $step = 'best';
                }
                if ($found->isEmpty()) {
                    $found = $search->apply($query(), $q, $internal)->get();
                    $step = 'full';
                }
                if ($found->isEmpty()) {
                    $found = $search->applyBestMatch($query(), $q)->get();
                    $step = 'best';
                }
                $this->line(sprintf('metric.search_probe.q%d_%s_ms=%d', $index, $who, (int) ((microtime(true) - $started) * 1000)));
                $this->line(sprintf('metric.search_probe.q%d_%s_found=%d', $index, $who, $found->count()));
                $this->line(sprintf('metric.search_probe.q%d_%s_%s=1', $index, $who, $step));
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ProductPopularityService;
use Illuminate\Console\Command;

class RecalculateProductPopularityCommand extends Command
{
    protected $signature = 'catalog:recalculate-popularity';

    protected $description = 'Recalculate catalog popularity from ratings, reviews and recent unique views.';

    public function handle(ProductPopularityService $popularity): int
    {
        $updated = $popularity->recalculate();
        $this->line('metric.catalog_popularity.updated_products='.$updated);

        return self::SUCCESS;
    }
}

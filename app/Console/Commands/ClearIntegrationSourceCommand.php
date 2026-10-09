<?php

namespace App\Console\Commands;

use App\Models\IntegrationSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearIntegrationSourceCommand extends Command
{
    protected $signature = 'integration:clear-source
        {source : Integration source code}
        {--force : Clear the staging catalogue without confirmation}';

    protected $description = 'Clear staged integration products and supplier groups while preserving the source and site catalogue';

    public function handle(): int
    {
        $sourceCode = trim((string) $this->argument('source'));
        $source = IntegrationSource::query()->where('code', $sourceCode)->first();

        if (! $source) {
            $this->error("Integration source [{$sourceCode}] was not found.");

            return self::FAILURE;
        }

        $productCount = $source->products()->count();
        $categoryCount = $source->categories()->count();

        if ($productCount === 0 && $categoryCount === 0) {
            $this->info("Integration source [{$sourceCode}] staging catalogue is already empty.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Delete {$productCount} staged products and {$categoryCount} supplier groups for [{$sourceCode}]?"
        )) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        [$deletedProducts, $deletedCategories] = DB::transaction(function () use ($source): array {
            $deletedProducts = $source->products()->delete();
            $deletedCategories = $source->categories()->delete();

            return [$deletedProducts, $deletedCategories];
        });

        $this->info(
            "Deleted {$deletedProducts} staged products and {$deletedCategories} supplier groups for [{$sourceCode}]."
        );

        return self::SUCCESS;
    }
}

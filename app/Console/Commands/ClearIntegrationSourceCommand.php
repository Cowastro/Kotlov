<?php

namespace App\Console\Commands;

use App\Models\IntegrationSource;
use Illuminate\Console\Command;

class ClearIntegrationSourceCommand extends Command
{
    protected $signature = 'integration:clear-source
        {source : Integration source code}
        {--force : Clear the staging catalogue without confirmation}';

    protected $description = 'Clear staged integration products while preserving the source and site catalogue';

    public function handle(): int
    {
        $sourceCode = trim((string) $this->argument('source'));
        $source = IntegrationSource::query()->where('code', $sourceCode)->first();

        if (! $source) {
            $this->error("Integration source [{$sourceCode}] was not found.");

            return self::FAILURE;
        }

        $count = $source->products()->count();

        if ($count === 0) {
            $this->info("Integration source [{$sourceCode}] staging catalogue is already empty.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Delete {$count} staged integration products for [{$sourceCode}]?"
        )) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        $deleted = $source->products()->delete();

        $this->info("Deleted {$deleted} staged integration products for [{$sourceCode}].");

        return self::SUCCESS;
    }
}

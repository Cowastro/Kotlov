<?php

namespace App\Console\Commands;

use App\Models\IntegrationSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClearIntegrationSourceCommand extends Command
{
    protected $signature = 'integration:clear-source
        {source : Integration source code}
        {--force : Clear the staging catalogue without confirmation}
        {--files : Also remove retained CommerceML upload files for the source}';

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
        $disk = Storage::disk((string) config('onec.exchange.storage_disk'));
        $exchangePath = trim((string) config('onec.exchange.storage_path'), '/').'/'.$source->code;
        $fileCount = $this->option('files') ? count($disk->allFiles($exchangePath)) : 0;

        if ($productCount === 0 && $categoryCount === 0 && $fileCount === 0) {
            $this->info("Integration source [{$sourceCode}] staging catalogue is already empty.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(
            "Delete {$productCount} staged products, {$categoryCount} supplier groups"
            .($this->option('files') ? ", and {$fileCount} retained exchange files" : '')
            ." for [{$sourceCode}]?"
        )) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        [$deletedProducts, $deletedCategories] = DB::transaction(function () use ($source): array {
            $deletedProducts = $source->products()->delete();
            $deletedCategories = $source->categories()->delete();

            return [$deletedProducts, $deletedCategories];
        });

        if ($this->option('files')) {
            $disk->deleteDirectory($exchangePath);
        }

        $this->info(
            "Deleted {$deletedProducts} staged products and {$deletedCategories} supplier groups"
            .($this->option('files') ? ", plus {$fileCount} retained exchange files" : '')
            ." for [{$sourceCode}]."
        );

        return self::SUCCESS;
    }
}

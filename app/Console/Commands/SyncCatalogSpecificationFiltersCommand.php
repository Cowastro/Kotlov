<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\CatalogSpecificationFilters;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SyncCatalogSpecificationFiltersCommand extends Command
{
    protected $signature = 'catalog:sync-specification-filters {--apply : Add only missing exact option links}';

    protected $description = 'Audit all published cards and connect confirmed specs to existing category filter options';

    public function handle(CatalogSpecificationFilters $filters): int
    {
        $cards = 0;
        $plan = [];
        Product::query()->orderable()->whereNotNull('specs')->with('allAttributeValues:id,product_id,attribute_id,option_id,value')
            ->chunkById(300, function ($products) use ($filters, &$cards, &$plan): void {
                foreach ($products as $product) {
                    $cards++;
                    array_push($plan, ...$filters->plan($product));
                }
            });
        $this->line(json_encode(['cards_audited' => $cards, 'cards_missing_filter_links' => count(array_unique(array_column($plan, 'product_id'))),
            'missing_links' => count($plan), 'attributes' => array_count_values(array_column($plan, 'attribute_id'))], JSON_UNESCAPED_UNICODE));
        if (! $this->option('apply') || $plan === []) {
            return self::SUCCESS;
        }
        $path = 'imports/audits/filter-links-'.now()->format('Ymd-His').'.json';
        if (! Storage::disk('local')->put($path, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $this->error('Audit backup could not be saved; nothing changed.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($plan, $filters): void {
            foreach ($plan as $row) {
                $filters->apply($row);
            }
        });
        $this->info('Missing links added. Existing values unchanged. Audit: '.$path);

        return self::SUCCESS;
    }
}

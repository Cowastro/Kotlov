<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

class SyncProductSpecsToAttributesCommand extends Command
{
    protected $signature = 'products:sync-specs-attributes
        {--apply : Write missing product_attribute_values}
        {--limit=0 : Limit products to process}
        {--id=* : Process only selected product IDs}';

    protected $description = 'Sync legacy products.specs JSON into product_attribute_values used by the storefront.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $ids = collect($this->option('id'))
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        $query = Product::query()
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]')
            ->whereDoesntHave('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $checked = 0;
        $candidates = 0;
        $syncedProducts = 0;
        $syncedRows = 0;
        $samples = [];

        $query->chunkById(200, function ($products) use ($enricher, $apply, &$checked, &$candidates, &$syncedProducts, &$syncedRows, &$samples): void {
            foreach ($products as $product) {
                $checked++;
                $specs = $this->normalizeSpecs($product->specs);

                if ($specs === []) {
                    continue;
                }

                $candidates++;
                if (count($samples) < 10) {
                    $samples[] = [$product->id, $product->sku ?: '-', $product->name, count($specs)];
                }

                if (! $apply) {
                    continue;
                }

                $saved = $enricher->syncSpecsToAttributeValues($product, $specs);
                if ($saved > 0) {
                    $syncedProducts++;
                    $syncedRows += $saved;
                }
            }
        });

        $this->info('Checked products: ' . $checked);
        $this->info('Need sync: ' . $candidates);

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Name', 'Specs'], $samples);
        }

        if ($apply) {
            $this->info('Synced products: ' . $syncedProducts);
            $this->info('Synced attribute rows: ' . $syncedRows);
        } else {
            $this->warn('Dry run only. Run with --apply to write product_attribute_values.');
        }

        return self::SUCCESS;
    }

    private function normalizeSpecs(mixed $specs): array
    {
        if (is_string($specs)) {
            $decoded = json_decode($specs, true);
            $specs = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }

        if (! is_array($specs)) {
            return [];
        }

        return collect($specs)
            ->map(function ($value, $key): array {
                if (is_array($value)) {
                    return [
                        'key' => (string) ($value['key'] ?? ''),
                        'value' => (string) ($value['value'] ?? ''),
                        'unit' => (string) ($value['unit'] ?? ''),
                    ];
                }

                return [
                    'key' => is_string($key) ? $key : '',
                    'value' => is_scalar($value) ? (string) $value : '',
                    'unit' => '',
                ];
            })
            ->filter(fn (array $spec): bool => trim($spec['key']) !== '' && trim($spec['value']) !== '')
            ->values()
            ->all();
    }
}

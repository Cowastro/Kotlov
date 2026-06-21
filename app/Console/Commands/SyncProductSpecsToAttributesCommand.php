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
        {--cleanup-unit-only : Delete already synced rows where value contains only a measurement unit}
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

        if ((bool) $this->option('cleanup-unit-only')) {
            return $this->cleanupUnitOnlyValues($enricher, $apply, $limit, $ids);
        }

        $query = Product::query()
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]')
            ->whereDoesntHave('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id');

        $checked = 0;
        $candidates = 0;
        $syncedProducts = 0;
        $syncedRows = 0;
        $samples = [];
        $errors = [];

        $query->chunkById(200, function ($products) use ($enricher, $apply, $limit, &$checked, &$candidates, &$syncedProducts, &$syncedRows, &$samples, &$errors): bool {
            foreach ($products as $product) {
                $checked++;
                $specs = $enricher->filterUsableSpecs($this->normalizeSpecs($product->specs));

                if ($specs === []) {
                    continue;
                }

                if ($limit > 0 && $candidates >= $limit) {
                    return false;
                }

                $candidates++;
                if (count($samples) < 10) {
                    $samples[] = [$product->id, $product->sku ?: '-', $product->name, count($specs)];
                }

                if (! $apply) {
                    continue;
                }

                try {
                    $saved = $enricher->syncSpecsToAttributeValues($product, $specs);
                    if ($saved > 0) {
                        $syncedProducts++;
                        $syncedRows += $saved;
                    }
                } catch (\Throwable $e) {
                    $errors[] = [$product->id, $product->sku ?: '-', mb_substr($e->getMessage(), 0, 180)];
                }
            }

            return true;
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

        if ($errors !== []) {
            $this->warn('Skipped with errors: ' . count($errors));
            $this->table(['ID', 'SKU', 'Error'], array_slice($errors, 0, 10));
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<int, int> $ids
     */
    private function cleanupUnitOnlyValues(ProductSourceEnricher $enricher, bool $apply, int $limit, array $ids): int
    {
        $query = Product::query()
            ->whereHas('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->orderBy('id');

        $checked = 0;
        $affectedProducts = 0;
        $deletedRows = 0;
        $samples = [];

        $query->chunkById(200, function ($products) use ($enricher, $apply, $limit, &$checked, &$affectedProducts, &$deletedRows, &$samples): bool {
            foreach ($products as $product) {
                $checked++;

                if ($limit > 0 && $affectedProducts >= $limit) {
                    return false;
                }

                $deleted = $enricher->deleteUnitOnlyAttributeValues($product, $apply);
                if ($deleted <= 0) {
                    continue;
                }

                $affectedProducts++;
                $deletedRows += $deleted;

                if (count($samples) < 10) {
                    $samples[] = [$product->id, $product->sku ?: '-', $product->name, $deleted];
                }
            }

            return true;
        });

        $this->info('Checked products: ' . $checked);
        $this->info('Products with unit-only values: ' . $affectedProducts);

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Name', 'Rows'], $samples);
        }

        if ($apply) {
            $this->info('Deleted unit-only attribute rows: ' . $deletedRows);
        } else {
            $this->warn('Dry run only. Run with --apply --cleanup-unit-only to delete unit-only rows.');
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

<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncProductSpecsToAttributesCommand extends Command
{
    protected $signature = 'products:sync-specs-attributes
        {--apply : Write missing product_attribute_values}
        {--limit=0 : Limit products to process}
        {--supplier= : Restrict products by supplier code, e.g. rusklimat}
        {--active-only : Restrict products to active catalog cards}
        {--not-archived : Exclude archived products}
        {--force : Sync products even when product_attribute_values already exist}
        {--audit-bad-attributes : Show product attribute rows with empty/unit-only/mojibake values}
        {--bad-reason=* : Filter audit by reason: empty_value, unit_only_value, value_has_unit_suffix, mojibake_name, mojibake_value}
        {--cleanup-empty-values : Delete already synced rows where a value attribute has an empty value}
        {--cleanup-unit-only : Delete already synced rows where value contains only a measurement unit}
        {--cleanup-leading-unit-names : Repair attribute names like ?ммГабаритные размеры, мм}
        {--cleanup-mojibake : Repair already synced rows with broken UTF-8/Windows-1251 text}
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
        $supplierCode = trim((string) $this->option('supplier'));
        $activeOnly = (bool) $this->option('active-only');
        $notArchived = (bool) $this->option('not-archived');

        if ((bool) $this->option('audit-bad-attributes')) {
            $reasons = collect($this->option('bad-reason'))
                ->map(fn ($reason): string => trim((string) $reason))
                ->filter()
                ->values()
                ->all();

            return $this->auditBadAttributes($limit, $ids, $reasons, $supplierCode, $activeOnly, $notArchived);
        }

        if ((bool) $this->option('cleanup-empty-values')) {
            return $this->cleanupEmptyValues($enricher, $apply, $limit, $ids, $supplierCode, $activeOnly, $notArchived);
        }

        if ((bool) $this->option('cleanup-unit-only')) {
            return $this->cleanupUnitOnlyValues($enricher, $apply, $limit, $ids, $supplierCode, $activeOnly, $notArchived);
        }

        if ((bool) $this->option('cleanup-leading-unit-names')) {
            return $this->cleanupLeadingUnitAttributeNames($enricher, $apply, $limit, $ids, $supplierCode, $activeOnly, $notArchived);
        }

        if ((bool) $this->option('cleanup-mojibake')) {
            return $this->cleanupMojibakeValues($enricher, $apply, $limit, $ids, $supplierCode, $activeOnly, $notArchived);
        }

        $query = Product::query()
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]')
            ->when(! (bool) $this->option('force'), fn ($query) => $query->whereDoesntHave('allAttributeValues'))
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($notArchived, fn ($query) => $query->where('is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereHas(
                'supplierProducts.supplier',
                fn ($query) => $query->where('code', $supplierCode)
            ))
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
    private function cleanupEmptyValues(ProductSourceEnricher $enricher, bool $apply, int $limit, array $ids, string $supplierCode, bool $activeOnly, bool $notArchived): int
    {
        $query = Product::query()
            ->whereHas('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($notArchived, fn ($query) => $query->where('is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereHas(
                'supplierProducts.supplier',
                fn ($query) => $query->where('code', $supplierCode)
            ))
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

                $deleted = $enricher->deleteEmptyAttributeValues($product, $apply);
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
        $this->info('Products with empty values: ' . $affectedProducts);

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Name', 'Rows'], $samples);
        }

        if ($apply) {
            $this->info('Deleted empty attribute rows: ' . $deletedRows);
        } else {
            $this->warn('Dry run only. Run with --apply --cleanup-empty-values to delete empty rows.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, int> $ids
     */
    private function cleanupUnitOnlyValues(ProductSourceEnricher $enricher, bool $apply, int $limit, array $ids, string $supplierCode, bool $activeOnly, bool $notArchived): int
    {
        $query = Product::query()
            ->whereHas('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($notArchived, fn ($query) => $query->where('is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereHas(
                'supplierProducts.supplier',
                fn ($query) => $query->where('code', $supplierCode)
            ))
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

    /**
     * @param array<int, int> $ids
     */
    private function cleanupLeadingUnitAttributeNames(ProductSourceEnricher $enricher, bool $apply, int $limit, array $ids, string $supplierCode, bool $activeOnly, bool $notArchived): int
    {
        $query = Product::query()
            ->whereHas('allAttributeValues', fn ($query) => $query->whereHas('attribute', fn ($query) => $query->where('name', 'like', '?%')))
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($notArchived, fn ($query) => $query->where('is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereHas(
                'supplierProducts.supplier',
                fn ($query) => $query->where('code', $supplierCode)
            ))
            ->orderBy('id');

        $checked = 0;
        $affectedProducts = 0;
        $changedRows = 0;
        $samples = [];

        $query->chunkById(200, function ($products) use ($enricher, $apply, $limit, &$checked, &$affectedProducts, &$changedRows, &$samples): bool {
            foreach ($products as $product) {
                $checked++;

                if ($limit > 0 && $affectedProducts >= $limit) {
                    return false;
                }

                $changed = $enricher->repairLeadingUnitAttributeNames($product, $apply);
                if ($changed <= 0) {
                    continue;
                }

                $affectedProducts++;
                $changedRows += $changed;

                if (count($samples) < 10) {
                    $samples[] = [$product->id, $product->sku ?: '-', $product->name, $changed];
                }
            }

            return true;
        });

        $this->info('Checked products: ' . $checked);
        $this->info('Products with leading unit attribute names: ' . $affectedProducts);

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Name', 'Rows'], $samples);
        }

        if ($apply) {
            $this->info('Repaired leading unit attribute rows: ' . $changedRows);
        } else {
            $this->warn('Dry run only. Run with --apply --cleanup-leading-unit-names to repair attribute names.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, int> $ids
     * @param array<int, string> $onlyReasons
     */
    private function auditBadAttributes(int $limit, array $ids, array $onlyReasons, string $supplierCode, bool $activeOnly, bool $notArchived): int
    {
        $rows = DB::table('product_attribute_values')
            ->join('products', 'products.id', '=', 'product_attribute_values.product_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->when($ids !== [], fn ($query) => $query->whereIn('products.id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('products.is_active', true))
            ->when($notArchived, fn ($query) => $query->where('products.is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereExists(function ($query) use ($supplierCode): void {
                $query->from('supplier_products as supplier_filter')
                    ->join('suppliers as supplier_filter_suppliers', 'supplier_filter_suppliers.id', '=', 'supplier_filter.supplier_id')
                    ->whereColumn('supplier_filter.product_id', 'products.id')
                    ->where('supplier_filter_suppliers.code', $supplierCode);
            }))
            ->orderBy('products.id')
            ->orderBy('product_attribute_values.id')
            ->get([
                'product_attribute_values.id',
                'product_attribute_values.product_id',
                'product_attribute_values.value',
                'product_attribute_values.option_id',
                'products.sku',
                'products.name as product_name',
                'products.is_active',
                'products.is_archived',
                'attributes.name as attribute_name',
                'attributes.suffix',
                'attributes.type as attribute_type',
            ]);

        $checked = 0;
        $badRows = 0;
        $badProducts = [];
        $samples = [];
        $reasonCounts = [];

        foreach ($rows as $row) {
            $checked++;
            $reasons = $this->badAttributeReasons(
                (string) ($row->attribute_name ?? ''),
                (string) ($row->value ?? ''),
                (string) ($row->suffix ?? ''),
                (string) ($row->attribute_type ?? 'value'),
                $row->option_id !== null
            );

            if ($reasons === []) {
                continue;
            }

            if ($onlyReasons !== [] && array_intersect($reasons, $onlyReasons) === []) {
                continue;
            }

            $badRows++;
            $badProducts[(int) $row->product_id] = true;

            foreach ($reasons as $reason) {
                $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
            }

            if ($limit === 0 || count($samples) < $limit) {
                $samples[] = [
                    $row->product_id,
                    $row->sku ?: '-',
                    $this->supplierNamesForProduct((int) $row->product_id),
                    ((bool) $row->is_active ? 'yes' : 'no') . '/' . ((bool) $row->is_archived ? 'yes' : 'no'),
                    mb_substr((string) $row->product_name, 0, 46),
                    mb_substr((string) ($row->attribute_name ?? '-'), 0, 34),
                    mb_substr((string) ($row->value ?? ''), 0, 34),
                    (string) ($row->suffix ?? ''),
                    implode(', ', $reasons),
                ];
            }
        }

        $this->info('Checked attribute rows: ' . $checked);
        $this->info('Bad attribute rows: ' . $badRows);
        $this->info('Products with bad attributes: ' . count($badProducts));

        if ($reasonCounts !== []) {
            $this->table(
                ['Reason', 'Rows'],
                collect($reasonCounts)
                    ->sortDesc()
                    ->map(fn (int $count, string $reason): array => [$reason, $count])
                    ->values()
                    ->all()
            );
        }

        if ($samples !== []) {
            $this->table(['Product ID', 'SKU', 'Suppliers', 'Active/Archived', 'Product', 'Attribute', 'Value', 'Unit', 'Reason'], $samples);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, int> $ids
     */
    private function cleanupMojibakeValues(ProductSourceEnricher $enricher, bool $apply, int $limit, array $ids, string $supplierCode, bool $activeOnly, bool $notArchived): int
    {
        $query = Product::query()
            ->whereHas('allAttributeValues')
            ->when($ids !== [], fn ($query) => $query->whereIn('id', $ids))
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->when($notArchived, fn ($query) => $query->where('is_archived', false))
            ->when($supplierCode !== '', fn ($query) => $query->whereHas(
                'supplierProducts.supplier',
                fn ($query) => $query->where('code', $supplierCode)
            ))
            ->orderBy('id');

        $checked = 0;
        $affectedProducts = 0;
        $changedRows = 0;
        $samples = [];

        $query->chunkById(200, function ($products) use ($enricher, $apply, $limit, &$checked, &$affectedProducts, &$changedRows, &$samples): bool {
            foreach ($products as $product) {
                $checked++;

                if ($limit > 0 && $affectedProducts >= $limit) {
                    return false;
                }

                $changed = $enricher->repairMojibakeAttributeValues($product, $apply);
                if ($changed <= 0) {
                    continue;
                }

                $affectedProducts++;
                $changedRows += $changed;

                if (count($samples) < 10) {
                    $samples[] = [$product->id, $product->sku ?: '-', $product->name, $changed];
                }
            }

            return true;
        });

        $this->info('Checked products: ' . $checked);
        $this->info('Products with mojibake values: ' . $affectedProducts);

        if ($samples !== []) {
            $this->table(['ID', 'SKU', 'Name', 'Rows'], $samples);
        }

        if ($apply) {
            $this->info('Repaired mojibake attribute rows: ' . $changedRows);
        } else {
            $this->warn('Dry run only. Run with --apply --cleanup-mojibake to repair mojibake rows.');
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

    /**
     * @return array<int, string>
     */
    private function badAttributeReasons(string $name, string $value, string $unit, string $type, bool $hasOption): array
    {
        $name = trim($name);
        $value = trim($value);
        $unit = trim($unit);
        $reasons = [];

        if ($name === '') {
            $reasons[] = 'empty_name';
        } elseif ($this->looksLikeBrokenEncoding($name)) {
            $reasons[] = 'mojibake_name';
        }

        if ($value === '' && $type === 'value' && ! $hasOption) {
            $reasons[] = 'empty_value';
        } elseif ($value !== '' && $this->looksLikeBrokenEncoding($value)) {
            $reasons[] = 'mojibake_value';
        }

        if ($value !== '' && $this->isMeasurementOnly($value, $name)) {
            $reasons[] = 'unit_only_value';
        }

        if ($value !== '' && $unit !== '' && $this->valueAlreadyContainsUnit($value, $unit)) {
            $reasons[] = 'value_has_unit_suffix';
        }

        return array_values(array_unique($reasons));
    }

    private function looksLikeBrokenEncoding(string $value): bool
    {
        return str_contains($value, '�')
            || preg_match('/(?:Р[’Ѓ“”•–—˜™љ›њќћџ ЎўЈ¤Ґ¦§Ё©Є«¬®Ї°±Ііґµ¶·ё№є»јЅѕї]|С[Ѓ‚ѓ„…†‡€‰Љ‹ЊЌЋЏђ‘’“”•–—˜™љ›њќћџ])+/u', $value) === 1
            || preg_match('/(?:Đ.|Ă.){2,}/u', $value) === 1;
    }

    private function isMeasurementOnly(string $value, ?string $attributeName = null): bool
    {
        if ($attributeName !== null && $this->isEnergyEfficiencyClassValue($attributeName, $value)) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace(['.', ','], '', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return preg_match('/^(?:вт|квт|мвт|в|а|ма|ом|бар|па|кпа|мпа|л|мл|м3|м³|м2|м²|мм|см|м|кг|г|шт|мин|ч|мес|год|лет|°c|°с|c|с|дюйм)$/u', $normalized) === 1;
    }

    private function isEnergyEfficiencyClassValue(string $attributeName, string $value): bool
    {
        $normalizedName = mb_strtolower(trim($attributeName));
        $normalizedValue = mb_strtolower(trim($value));

        return str_contains($normalizedName, 'энергоэффектив')
            && preg_match('/^[a-gа-г](?:\+{1,3})?$/u', $normalizedValue) === 1;
    }

    private function valueAlreadyContainsUnit(string $value, string $unit): bool
    {
        $value = mb_strtolower(trim($value));
        $unit = mb_strtolower(trim($unit));

        if ($value === '' || $unit === '') {
            return false;
        }

        if ($this->isMeasurementOnly($value)) {
            return true;
        }

        $quotedUnit = preg_quote($unit, '/');

        return preg_match('/(?:^|[\s\d.,])' . $quotedUnit . '$/u', $value) === 1;
    }

    private function supplierNamesForProduct(int $productId): string
    {
        $names = DB::table('supplier_products')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
            ->where('supplier_products.product_id', $productId)
            ->pluck('suppliers.name')
            ->filter()
            ->unique()
            ->values();

        return $names->isNotEmpty() ? $names->implode(', ') : '-';
    }
}

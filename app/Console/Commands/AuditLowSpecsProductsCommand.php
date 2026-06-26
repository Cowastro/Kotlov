<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLowSpecsProductsCommand extends Command
{
    protected $signature = 'products:audit-low-specs
        {--max=2 : Maximum product_attribute_values rows}
        {--supplier= : Supplier code filter}
        {--brand= : Brand name filter}
        {--category= : Category name filter}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--with-source-only : Only products with supplier source URLs}
        {--missing-source-only : Only products without supplier source URLs}
        {--limit=100 : Rows to print/export, 0 means all}
        {--csv= : Optional CSV report path, relative to project root}';

    protected $description = 'Audit products that have too few visible technical attribute rows.';

    public function handle(): int
    {
        $max = max(0, (int) $this->option('max'));
        $limit = max(0, (int) $this->option('limit'));

        $query = $this->baseQuery($max);

        $total = DB::query()->fromSub(clone $query, 'low_specs')->count();

        $rowsQuery = (clone $query)
            ->orderBy('attribute_rows')
            ->orderBy('brand')
            ->orderBy('product_id');

        $allRows = $rowsQuery->get()->map(fn ($row) => $this->normalizeRow($row));

        $rows = $limit > 0
            ? $allRows->take($limit)->values()
            : $allRows;

        $this->info("Products with {$max} or fewer attribute rows: {$total}");
        $this->line('Showing rows: ' . $rows->count() . ($limit > 0 ? " (limit {$limit})" : ''));
        $this->newLine();

        $this->printSummary('By brand', $allRows, 'brand');
        $this->printSummary('By supplier', $allRows, 'suppliers');
        $this->printSummary('By category', $allRows, 'category');

        $this->newLine();
        $this->table(
            ['ID', 'SKU', 'Brand', 'Category', 'Attrs', 'Suppliers', 'Source domains', 'Product'],
            $rows->map(fn ($row) => [
                $row['product_id'],
                $row['sku'],
                $row['brand'],
                $row['category'],
                $row['attribute_rows'],
                $row['suppliers'],
                $row['source_domains'],
                mb_strimwidth($row['name'], 0, 56, '...'),
            ])->all()
        );

        if ($csv = trim((string) $this->option('csv'))) {
            $path = $this->writeCsv($csv, $allRows);
            $this->info('CSV written: ' . $path);
        }

        return self::SUCCESS;
    }

    private function baseQuery(int $max)
    {
        $attributeCounts = DB::table('product_attribute_values')
            ->select('product_id', DB::raw('COUNT(*) as attribute_rows'))
            ->groupBy('product_id');

        $query = DB::table('products as p')
            ->leftJoinSub($attributeCounts, 'pav', 'pav.product_id', '=', 'p.id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->whereRaw('COALESCE(pav.attribute_rows, 0) <= ?', [$max])
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                DB::raw("COALESCE(b.name, '-') as brand"),
                DB::raw("COALESCE(c.name, '-') as category"),
                DB::raw('COALESCE(pav.attribute_rows, 0) as attribute_rows'),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT s.code ORDER BY s.code SEPARATOR ', '), '-') as suppliers"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT sp.source_url ORDER BY sp.source_url SEPARATOR ' | '), '') as source_urls"),
            ])
            ->groupBy('p.id', 'p.sku', 'p.name', 'b.name', 'c.name', 'pav.attribute_rows');

        if ((bool) $this->option('not-archived')) {
            $query->where('p.is_archived', false);
        }

        if ((bool) $this->option('active-only') && Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query->where('s.code', $supplier);
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where('b.name', 'like', '%' . $brand . '%');
        }

        if ($category = trim((string) $this->option('category'))) {
            $query->where('c.name', 'like', '%' . $category . '%');
        }

        if ((bool) $this->option('with-source-only')) {
            $query->whereNotNull('sp.source_url')->where('sp.source_url', 'like', 'http%');
        }

        if ((bool) $this->option('missing-source-only')) {
            $query->where(function ($query): void {
                $query->whereNull('sp.source_url')
                    ->orWhere('sp.source_url', '')
                    ->orWhere('sp.source_url', 'not like', 'http%');
            });
        }

        return $query;
    }

    private function normalizeRow(object $row): array
    {
        $sourceUrls = array_values(array_filter(array_map('trim', explode('|', (string) $row->source_urls))));
        $sourceDomains = collect($sourceUrls)
            ->map(fn (string $url) => parse_url($url, PHP_URL_HOST) ?: '')
            ->filter()
            ->unique()
            ->implode(', ');

        return [
            'product_id' => (int) $row->product_id,
            'sku' => (string) $row->sku,
            'name' => (string) $row->name,
            'brand' => (string) $row->brand,
            'category' => (string) $row->category,
            'attribute_rows' => (int) $row->attribute_rows,
            'suppliers' => (string) $row->suppliers,
            'source_domains' => $sourceDomains !== '' ? $sourceDomains : '-',
            'source_urls' => implode(' | ', $sourceUrls),
        ];
    }

    private function printSummary(string $title, Collection $rows, string $key): void
    {
        $summary = $rows
            ->groupBy(fn (array $row) => $row[$key] ?: '-')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'count' => $items->count(),
                'with_source' => $items->where('source_domains', '!=', '-')->count(),
            ])
            ->sortByDesc('count')
            ->take(20)
            ->values();

        $this->line('<info>' . $title . '</info>');
        $this->table(
            ['Name', 'Products', 'With source'],
            $summary->map(fn (array $row) => [
                mb_strimwidth($row['name'], 0, 52, '...'),
                $row['count'],
                $row['with_source'],
            ])->all()
        );
    }

    private function writeCsv(string $path, Collection $rows): string
    {
        if (! preg_match('/^([A-Z]:[\\\\\/]|\/)/i', $path)) {
            $path = base_path($path);
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'w');
        fputcsv($handle, [
            'product_id',
            'sku',
            'brand',
            'category',
            'attribute_rows',
            'suppliers',
            'source_domains',
            'name',
            'source_urls',
        ]);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['product_id'],
                $row['sku'],
                $row['brand'],
                $row['category'],
                $row['attribute_rows'],
                $row['suppliers'],
                $row['source_domains'],
                $row['name'],
                $row['source_urls'],
            ]);
        }

        fclose($handle);

        return $path;
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditProductContentHealthCommand extends Command
{
    protected $signature = 'products:audit-content-health
        {--supplier= : Supplier code filter}
        {--brand= : Brand name filter}
        {--category= : Category name filter}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--with-source-only : Only products with supplier source URLs}
        {--missing-source-only : Only products without supplier source URLs}
        {--issues= : Comma-separated issue filter: no_photo,no_content,no_short,low_attrs,no_source}
        {--max-attrs=2 : Maximum attribute rows for low_attrs}
        {--limit=100 : Rows to print/export, 0 means all}
        {--csv= : Optional CSV report path, relative to project root}';

    protected $description = 'Audit product card health: photos, descriptions, attributes and source URLs.';

    public function handle(): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $maxAttrs = max(0, (int) $this->option('max-attrs'));
        $issueFilter = $this->issueFilter();

        $rows = $this->baseRows($maxAttrs)
            ->map(fn (object $row): array => $this->normalizeRow($row, $maxAttrs))
            ->filter(fn (array $row): bool => $this->matchesIssueFilter($row, $issueFilter))
            ->values();

        $shown = $limit > 0 ? $rows->take($limit)->values() : $rows;

        $this->info('Products with content-health issues: ' . $rows->count());
        $this->line('Showing rows: ' . $shown->count() . ($limit > 0 ? " (limit {$limit})" : ''));
        $this->newLine();

        $this->printIssueSummary($rows);
        $this->printSummary('By supplier', $rows, 'suppliers');
        $this->printSummary('By brand', $rows, 'brand');
        $this->printSummary('By category', $rows, 'category');

        $this->newLine();
        $this->table(
            ['ID', 'SKU', 'Brand', 'Category', 'Suppliers', 'Attrs', 'Issues', 'Source domains', 'Product'],
            $shown->map(fn (array $row): array => [
                $row['product_id'],
                $row['sku'],
                $row['brand'],
                $row['category'],
                $row['suppliers'],
                $row['attribute_rows'],
                implode(',', $row['issues']),
                $row['source_domains'],
                mb_strimwidth($row['name'], 0, 58, '...'),
            ])->all()
        );

        if ($csv = trim((string) $this->option('csv'))) {
            $path = $this->writeCsv($csv, $rows);
            $this->info('CSV written: ' . $path);
        }

        return self::SUCCESS;
    }

    private function baseRows(int $maxAttrs): Collection
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
            ->select([
                'p.id as product_id',
                'p.sku',
                'p.name',
                'p.images',
                'p.content',
                'p.short_description',
                DB::raw('COALESCE(pav.attribute_rows, 0) as attribute_rows'),
                DB::raw("COALESCE(b.name, '-') as brand"),
                DB::raw("COALESCE(c.name, '-') as category"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT s.code ORDER BY s.code SEPARATOR ', '), '-') as suppliers"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT sp.source_url ORDER BY sp.source_url SEPARATOR ' | '), '') as source_urls"),
            ])
            ->groupBy(
                'p.id',
                'p.sku',
                'p.name',
                'p.images',
                'p.content',
                'p.short_description',
                'pav.attribute_rows',
                'b.name',
                'c.name'
            );

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

        return $query
            ->orderBy('b.name')
            ->orderBy('p.id')
            ->get()
            ->filter(function (object $row) use ($maxAttrs): bool {
                return $this->imageIsEmpty((string) ($row->images ?? ''))
                    || trim(strip_tags((string) ($row->content ?? ''))) === ''
                    || trim((string) ($row->short_description ?? '')) === ''
                    || (int) $row->attribute_rows <= $maxAttrs
                    || $this->sourceDomains((string) $row->source_urls) === '-';
            })
            ->values();
    }

    private function normalizeRow(object $row, int $maxAttrs): array
    {
        $issues = [];

        if ($this->imageIsEmpty((string) ($row->images ?? ''))) {
            $issues[] = 'no_photo';
        }

        if (trim(strip_tags((string) ($row->content ?? ''))) === '') {
            $issues[] = 'no_content';
        }

        if (trim((string) ($row->short_description ?? '')) === '') {
            $issues[] = 'no_short';
        }

        if ((int) $row->attribute_rows <= $maxAttrs) {
            $issues[] = 'low_attrs';
        }

        $sourceDomains = $this->sourceDomains((string) $row->source_urls);
        if ($sourceDomains === '-') {
            $issues[] = 'no_source';
        }

        return [
            'product_id' => (int) $row->product_id,
            'sku' => (string) $row->sku,
            'name' => (string) $row->name,
            'brand' => (string) $row->brand,
            'category' => (string) $row->category,
            'suppliers' => (string) $row->suppliers,
            'attribute_rows' => (int) $row->attribute_rows,
            'source_domains' => $sourceDomains,
            'source_urls' => implode(' | ', $this->sourceUrls((string) $row->source_urls)),
            'issues' => $issues,
        ];
    }

    private function imageIsEmpty(string $images): bool
    {
        $images = trim($images);
        if ($images === '' || $images === '[]' || $images === 'null') {
            return true;
        }

        $decoded = json_decode($images, true);
        return ! is_array($decoded) || count(array_filter($decoded)) === 0;
    }

    private function sourceUrls(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode('|', $value)), fn (string $url): bool => str_starts_with($url, 'http')));
    }

    private function sourceDomains(string $value): string
    {
        $domains = collect($this->sourceUrls($value))
            ->map(fn (string $url): string => parse_url($url, PHP_URL_HOST) ?: '')
            ->filter()
            ->unique()
            ->values();

        return $domains->isEmpty() ? '-' : $domains->implode(', ');
    }

    private function issueFilter(): array
    {
        $raw = trim((string) $this->option('issues'));
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function matchesIssueFilter(array $row, array $filters): bool
    {
        if ($filters === []) {
            return true;
        }

        return array_intersect($filters, $row['issues']) !== [];
    }

    private function printIssueSummary(Collection $rows): void
    {
        $issues = ['no_photo', 'no_content', 'no_short', 'low_attrs', 'no_source'];
        $this->line('<info>By issue</info>');
        $this->table(
            ['Issue', 'Products'],
            collect($issues)->map(fn (string $issue): array => [
                $issue,
                $rows->filter(fn (array $row): bool => in_array($issue, $row['issues'], true))->count(),
            ])->all()
        );
    }

    private function printSummary(string $title, Collection $rows, string $key): void
    {
        $summary = $rows
            ->groupBy(fn (array $row): string => $row[$key] ?: '-')
            ->map(fn (Collection $items, string $name): array => [
                'name' => $name,
                'count' => $items->count(),
                'no_photo' => $items->filter(fn (array $row): bool => in_array('no_photo', $row['issues'], true))->count(),
                'no_content' => $items->filter(fn (array $row): bool => in_array('no_content', $row['issues'], true))->count(),
                'low_attrs' => $items->filter(fn (array $row): bool => in_array('low_attrs', $row['issues'], true))->count(),
            ])
            ->sortByDesc('count')
            ->take(20)
            ->values();

        $this->line('<info>' . $title . '</info>');
        $this->table(
            ['Name', 'Products', 'No photo', 'No content', 'Low attrs'],
            $summary->map(fn (array $row): array => [
                mb_strimwidth($row['name'], 0, 52, '...'),
                $row['count'],
                $row['no_photo'],
                $row['no_content'],
                $row['low_attrs'],
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
            'suppliers',
            'attribute_rows',
            'issues',
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
                $row['suppliers'],
                $row['attribute_rows'],
                implode(',', $row['issues']),
                $row['source_domains'],
                $row['name'],
                $row['source_urls'],
            ]);
        }

        fclose($handle);

        return $path;
    }
}

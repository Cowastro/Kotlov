<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditProductContentTermsCommand extends Command
{
    protected $signature = 'products:audit-content-terms
        {--brand= : Brand name filter}
        {--category= : Category name filter}
        {--product= : Product id or SKU filter}
        {--supplier= : Supplier code filter}
        {--profile= : Built-in term profile: varmega-fittings}
        {--terms= : Comma-separated terms to flag}
        {--active-only : Only active products}
        {--not-archived : Only not archived products}
        {--limit=100 : Rows to print, 0 means all}
        {--csv= : Optional CSV report path, relative to project root}';

    protected $description = 'Audit product content for forbidden or suspicious words in a filtered product scope.';

    public function handle(): int
    {
        $terms = $this->terms();
        if ($terms === []) {
            $this->error('No terms to audit. Use --terms=... or --profile=varmega-fittings.');

            return self::FAILURE;
        }

        $limit = max(0, (int) $this->option('limit'));

        $rows = $this->baseRows()
            ->map(fn (object $row): array => $this->rowWithMatches($row, $terms))
            ->filter(fn (array $row): bool => $row['matches'] !== [])
            ->values();

        $shown = $limit > 0 ? $rows->take($limit)->values() : $rows;

        $this->table(['metric', 'count'], [
            ['checked', $this->baseRows()->count()],
            ['products_with_matches', $rows->count()],
        ]);

        $this->printSummary($rows);

        $this->table(
            ['ID', 'SKU', 'Brand', 'Category', 'Terms', 'Product', 'Snippet'],
            $shown->map(fn (array $row): array => [
                $row['id'],
                $row['sku'],
                $row['brand'],
                $row['category'],
                implode(', ', $row['matches']),
                mb_strimwidth($row['name'], 0, 50, '...'),
                Str::limit($row['snippet'], 170, ''),
            ])->all()
        );

        if ($csv = trim((string) $this->option('csv'))) {
            $this->info('CSV written: ' . $this->writeCsv($csv, $rows));
        }

        return self::SUCCESS;
    }

    private function baseRows(): Collection
    {
        $hasMetaDescription = Schema::hasColumn('products', 'meta_description');

        $groupBy = [
            'p.id',
            'p.sku',
            'p.name',
            'p.content',
            'p.short_description',
            'b.name',
            'c.name',
        ];

        if ($hasMetaDescription) {
            $groupBy[] = 'p.meta_description';
        }

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('supplier_products as sp', 'sp.product_id', '=', 'p.id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.content',
                'p.short_description',
                DB::raw($hasMetaDescription ? 'p.meta_description' : "'' as meta_description"),
                DB::raw("COALESCE(b.name, '-') as brand"),
                DB::raw("COALESCE(c.name, '-') as category"),
                DB::raw("COALESCE(GROUP_CONCAT(DISTINCT s.code ORDER BY s.code SEPARATOR ', '), '-') as suppliers"),
            ])
            ->groupBy(...$groupBy)
            ->orderBy('b.name')
            ->orderBy('p.id');

        if ((bool) $this->option('not-archived')) {
            $query->where('p.is_archived', false);
        }

        if ((bool) $this->option('active-only') && Schema::hasColumn('products', 'is_active')) {
            $query->where('p.is_active', true);
        }

        if ($product = trim((string) $this->option('product'))) {
            $query->where(function ($query) use ($product): void {
                $query->where('p.sku', $product);

                if (ctype_digit($product)) {
                    $query->orWhere('p.id', (int) $product);
                }
            });
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where('b.name', 'like', '%' . $brand . '%');
        }

        if ($category = trim((string) $this->option('category'))) {
            $query->where('c.name', 'like', '%' . $category . '%');
        }

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query->where('s.code', $supplier);
        }

        return $query->get();
    }

    /**
     * @param string[] $terms
     * @return array{id:int,sku:string,name:string,brand:string,category:string,matches:string[],snippet:string}
     */
    private function rowWithMatches(object $row, array $terms): array
    {
        $text = $this->plainText(implode(' ', [
            $row->content ?? '',
            $row->short_description ?? '',
            $row->meta_description ?? '',
        ]));

        $matches = [];
        foreach ($terms as $term) {
            if (preg_match('/(?<![\pL\pN])' . preg_quote($term, '/') . '(?![\pL\pN])/iu', $text)) {
                $matches[] = $term;
            }
        }

        return [
            'id' => (int) $row->id,
            'sku' => (string) $row->sku,
            'name' => (string) $row->name,
            'brand' => (string) $row->brand,
            'category' => (string) $row->category,
            'matches' => array_values(array_unique($matches)),
            'snippet' => $text,
        ];
    }

    private function plainText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    /**
     * @return string[]
     */
    private function terms(): array
    {
        $terms = [];

        if (trim((string) $this->option('profile')) === 'varmega-fittings') {
            $terms = [
                'котел',
                'котёл',
                'котла',
                'котлы',
                'твердотопливный',
                'твердотопливные',
                'радиатор',
                'радиаторы',
                'радиаторный',
                'радиаторная',
                'бойлер',
                'водонагреватель',
                'насос',
                'насосный',
                'горелка',
                'печь',
                'камин',
                'квт',
            ];
        }

        if ($raw = trim((string) $this->option('terms'))) {
            $terms = array_merge($terms, array_map('trim', explode(',', $raw)));
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $term): string => mb_strtolower($term),
            $terms
        ))));
    }

    private function printSummary(Collection $rows): void
    {
        $summary = [];
        foreach ($rows as $row) {
            foreach ($row['matches'] as $term) {
                $summary[$term] = ($summary[$term] ?? 0) + 1;
            }
        }

        if ($summary === []) {
            return;
        }

        arsort($summary);
        $this->table(
            ['term', 'products'],
            collect($summary)->map(fn (int $count, string $term): array => [$term, $count])->values()->all()
        );
    }

    private function writeCsv(string $target, Collection $rows): string
    {
        $path = base_path($target);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'wb');
        fputcsv($handle, ['id', 'sku', 'brand', 'category', 'matches', 'name', 'snippet']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['id'],
                $row['sku'],
                $row['brand'],
                $row['category'],
                implode('|', $row['matches']),
                $row['name'],
                $row['snippet'],
            ]);
        }

        fclose($handle);

        return $path;
    }
}

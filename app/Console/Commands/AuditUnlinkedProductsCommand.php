<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditUnlinkedProductsCommand extends Command
{
    protected $signature = 'catalog:audit-unlinked-products
        {--output=storage/app/reports/catalog/unlinked-products.csv : CSV report path}
        {--brand= : Optional exact brand filter}
        {--active-only : Include only active products}
        {--limit= : Maximum number of rows}';

    protected $description = 'Export active catalogue products that have no linked supplier channel';

    public function handle(): int
    {
        $output = $this->absolutePath((string) $this->option('output'));

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_archived', false)
            ->whereNotExists(fn ($supplierProducts) => $supplierProducts
                ->selectRaw('1')
                ->from('supplier_products as sp')
                ->whereColumn('sp.product_id', 'p.id'))
            ->whereNotExists(fn ($integrationProducts) => $integrationProducts
                ->selectRaw('1')
                ->from('integration_products as ip')
                ->join('integration_sources as src', 'src.id', '=', 'ip.integration_source_id')
                ->whereColumn('ip.product_id', 'p.id')
                ->where('ip.match_status', 'matched')
                ->where('src.is_active', true));

        if ((bool) $this->option('active-only')) {
            $query->where('p.is_active', true);
        }

        if (filled($this->option('brand'))) {
            $query->where('b.name', (string) $this->option('brand'));
        }

        if (filled($this->option('limit'))) {
            $query->limit(max(1, (int) $this->option('limit')));
        }

        $rows = $query
            ->orderBy('b.name')
            ->orderBy('c.name')
            ->orderBy('p.name')
            ->get([
                'p.id',
                'p.sku',
                'p.name',
                'p.slug',
                'b.name as brand',
                'c.name as category',
                'p.price',
                'p.price_old',
                'p.currency',
                'p.is_active',
                'p.in_stock',
                'p.availability_status',
                'p.content_source_url',
                'p.market_price_verified_at',
                'p.market_price_source_url',
                'p.supplier_candidate_name',
                'p.supplier_candidate_url',
                'p.updated_at',
            ]);

        File::ensureDirectoryExists(dirname($output));
        $handle = fopen($output, 'wb');

        if ($handle === false) {
            $this->error("Cannot create report: {$output}");

            return self::FAILURE;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [
            'product_id', 'sku', 'name', 'slug', 'brand', 'category',
            'price', 'price_old', 'currency', 'is_active', 'in_stock',
            'availability_status', 'content_source_url', 'market_price_verified_at',
            'market_price_source_url', 'supplier_candidate_name',
            'supplier_candidate_url', 'updated_at',
        ], ';');

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row->id,
                $row->sku,
                $row->name,
                $row->slug,
                $row->brand,
                $row->category,
                $row->price,
                $row->price_old,
                $row->currency,
                (int) $row->is_active,
                (int) $row->in_stock,
                $row->availability_status,
                $row->content_source_url,
                $row->market_price_verified_at,
                $row->market_price_source_url,
                $row->supplier_candidate_name,
                $row->supplier_candidate_url,
                $row->updated_at,
            ], ';');
        }

        fclose($handle);

        $this->table(
            ['metric', 'count'],
            [
                ['products_without_supplier_channel', $rows->count()],
                ['brands', $rows->pluck('brand')->filter()->unique()->count()],
                ['categories', $rows->pluck('category')->filter()->unique()->count()],
                ['with_public_price', $rows->filter(fn ($row) => (float) $row->price > 0)->count()],
                ['with_content_source', $rows->filter(fn ($row) => filled($row->content_source_url))->count()],
                ['with_verified_market_price', $rows->filter(fn ($row) => filled($row->market_price_verified_at))->count()],
                ['with_supplier_candidate', $rows->filter(fn ($row) => filled($row->supplier_candidate_url))->count()],
            ],
        );
        $this->info("Report: {$output}");

        return self::SUCCESS;
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}

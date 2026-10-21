<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class AuditUnlinkedProductsCommand extends Command
{
    protected $signature = 'catalog:audit-unlinked-products
        {--output=storage/app/reports/catalog/unlinked-products.csv : CSV report path}
        {--brand= : Optional exact brand filter}
        {--active-only : Include only active products}
        {--operational-only : Include products without an operational supplier route, even when a non-operational link exists}
        {--limit= : Maximum number of rows}';

    protected $description = 'Export active catalogue products that have no linked supplier channel';

    public function handle(): int
    {
        $output = $this->absolutePath((string) $this->option('output'));

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.is_archived', false);

        if ((bool) $this->option('operational-only')) {
            $candidateIds = $this->productsWithoutOperationalSupplier();
            $query->whereIn('p.id', $candidateIds === [] ? [-1] : $candidateIds);
        } else {
            $query
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
        }

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
                'p.updated_at',
            ]);

        $channelHints = $this->channelHints($rows->pluck('id')->map(fn ($id) => (int) $id)->all());

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
            'availability_status', 'content_source_url', 'updated_at',
            'supplier_product_channels', 'integration_channels', 'purchase_price_channels',
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
                $row->updated_at,
                $channelHints[$row->id]['supplier_products'] ?? '',
                $channelHints[$row->id]['integrations'] ?? '',
                $channelHints[$row->id]['purchase_prices'] ?? '',
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
            ],
        );
        $this->info("Report: {$output}");

        return self::SUCCESS;
    }

    /** @return array<int> */
    private function productsWithoutOperationalSupplier(): array
    {
        $ids = [];
        $products = Product::query()
            ->where('is_archived', false)
            ->with([
                'supplierProducts.supplier.latestChannelTransition',
                'integrationProducts.source.supplier',
                'purchasePriceSources.supplier',
            ]);

        if ((bool) $this->option('active-only')) {
            $products->where('is_active', true);
        }

        if (filled($this->option('brand'))) {
            $brand = (string) $this->option('brand');
            $products->whereHas('brand', fn ($query) => $query->where('name', $brand));
        }

        $products->orderBy('id')->chunkById(250, function ($chunk) use (&$ids): void {
            foreach ($chunk as $product) {
                if (! $product->hasAssignedSupplier()) {
                    $ids[] = (int) $product->id;
                }
            }
        });

        return $ids;
    }

    /** @param  array<int>  $productIds */
    private function channelHints(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->whereIntegerInRaw('id', $productIds)
            ->with([
                'supplierProducts.supplier:id,name,is_active',
                'integrationProducts.source.supplier:id,name,is_active',
                'purchasePriceSources.supplier:id,name,is_active',
            ])
            ->get()
            ->mapWithKeys(function (Product $product): array {
                $supplierProducts = $product->supplierProducts
                    ->map(fn ($item): string => sprintf(
                        '%s [active=%s; confidence=%s; price_byn=%s]',
                        $item->supplier?->name ?: 'Поставщик не указан',
                        $item->supplier?->is_active ? '1' : '0',
                        $item->match_confidence ?: '—',
                        $item->price_byn ?: '—',
                    ))
                    ->implode(' | ');
                $integrations = $product->integrationProducts
                    ->map(fn ($item): string => sprintf(
                        '%s [source_active=%s; supplier_active=%s; status=%s; price_byn=%s]',
                        $item->source?->name ?: 'Источник не указан',
                        $item->source?->is_active ? '1' : '0',
                        $item->source?->supplier?->is_active ? '1' : '0',
                        $item->match_status ?: '—',
                        $item->price_byn ?: '—',
                    ))
                    ->implode(' | ');
                $purchasePrices = $product->purchasePriceSources
                    ->map(fn ($source): string => sprintf(
                        '%s [active=%s; price_byn=%s; valid_until=%s]',
                        $source->supplier?->name ?: ($source->supplier_name ?: 'Поставщик не указан'),
                        $source->is_active ? '1' : '0',
                        $source->price_byn ?: '—',
                        $source->valid_until?->toDateString() ?: '—',
                    ))
                    ->implode(' | ');

                return [$product->id => [
                    'supplier_products' => $supplierProducts,
                    'integrations' => $integrations,
                    'purchase_prices' => $purchasePrices,
                ]];
            })
            ->all();
    }

    private function absolutePath(string $path): string
    {
        if (preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }
}

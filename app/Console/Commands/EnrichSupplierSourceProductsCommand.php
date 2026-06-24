<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnrichSupplierSourceProductsCommand extends Command
{
    protected $signature = 'supplier:enrich-source-products
        {--supplier= : Supplier code, for example rn-profi}
        {--brand= : Brand name filter}
        {--domain= : Source URL domain filter, for example varmega.ru}
        {--product= : Process one product ID}
        {--created-today : Process only products created today}
        {--created-from= : Process only products created from this date/time}
        {--created-to= : Process only products created before this date/time}
        {--limit=50 : Max products per run, 0 means all}
        {--offset=0 : Skip products}
        {--apply : Write changes to DB, default is dry-run}
        {--force : Process even products that already have photos, specs and content}
        {--overwrite-images : Replace existing product images instead of appending}
        {--replace-specs : Delete existing product attributes and replace specs from source}
        {--skip-ai : Skip AI content generation}
        {--sleep=1200 : Delay between HTTP requests, ms}';

    protected $description = 'Enrich supplier-linked products from supplier_products.source_url.';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $apply = (bool) $this->option('apply');
        $limit = max(0, (int) $this->option('limit'));
        $offset = max(0, (int) $this->option('offset'));
        $sleep = max(300, (int) $this->option('sleep'));
        $force = (bool) $this->option('force');

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: source enrichment will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: source enrichment preview only.</>');

        $query = DB::table('supplier_products as sp')
            ->join('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->where('p.is_archived', false)
            ->whereNotNull('sp.source_url')
            ->where('sp.source_url', 'like', 'http%')
            ->select([
                'p.id',
                'p.name',
                'p.images',
                'p.specs',
                'p.content',
                'b.name as brand',
                's.code as supplier_code',
                'sp.supplier_article',
                'sp.source_url',
            ]);

        if ($supplier = trim((string) $this->option('supplier'))) {
            $query->where('s.code', $supplier);
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->where('b.name', 'like', '%' . $brand . '%');
        }

        if ($domain = trim((string) $this->option('domain'))) {
            $query->where('sp.source_url', 'like', '%' . $domain . '%');
        }

        if ($productId = (int) $this->option('product')) {
            $query->where('p.id', $productId);
        }

        if ((bool) $this->option('created-today')) {
            $query->whereDate('p.created_at', now()->toDateString());
        }

        if ($createdFrom = trim((string) $this->option('created-from'))) {
            $query->where('p.created_at', '>=', \Carbon\Carbon::parse($createdFrom));
        }

        if ($createdTo = trim((string) $this->option('created-to'))) {
            $query->where('p.created_at', '<=', \Carbon\Carbon::parse($createdTo));
        }

        if (! $force) {
            $query->where(function ($q): void {
                $q->whereNull('p.images')
                    ->orWhere('p.images', '')
                    ->orWhere('p.images', '[]')
                    ->orWhereNull('p.specs')
                    ->orWhere('p.specs', '')
                    ->orWhere('p.specs', '[]')
                    ->orWhere('p.specs', '{}')
                    ->orWhereNull('p.content')
                    ->orWhere('p.content', '');
            });
        }

        $total = (clone $query)->distinct('p.id')->count('p.id');
        $rows = $query
            ->orderBy('p.id');

        if ($limit > 0) {
            $rows->limit($limit);
        }

        if ($offset > 0) {
            $rows->offset($offset);
        }

        $rows = $rows
            ->get()
            ->unique('id')
            ->values();

        $this->info(sprintf(
            'Products with source URLs: %d (processing %d, offset %d%s)',
            $total,
            $rows->count(),
            $offset,
            $force ? ', --force' : ''
        ));

        $stats = [
            'processed' => 0,
            'enriched' => 0,
            'images_found' => 0,
            'images_saved' => 0,
            'specs_found' => 0,
            'attributes_saved' => 0,
            'ai_done' => 0,
            'errors' => 0,
        ];

        foreach ($rows as $row) {
            $stats['processed']++;
            $product = Product::find((int) $row->id);
            if (! $product) {
                $stats['errors']++;
                continue;
            }

            $this->line(sprintf(
                '[%d/%d] #%d %s %s',
                $stats['processed'],
                $rows->count(),
                $product->id,
                (string) $row->supplier_article,
                mb_substr((string) $row->name, 0, 70)
            ));
            $this->line('  source: ' . $row->source_url);

            try {
                $result = $enricher->enrich($product, (string) $row->source_url, [
                    'preview_only' => ! $apply,
                    'replace_images' => (bool) $this->option('overwrite-images'),
                    'update_images' => true,
                    'update_specs' => true,
                    'replace_specs' => (bool) $this->option('replace-specs') || $force,
                    'update_service' => true,
                    'update_documents' => true,
                    'update_video' => true,
                    'update_content' => ! (bool) $this->option('skip-ai'),
                ]);

                $stats['images_found'] += (int) ($result['images_found'] ?? 0);
                $stats['images_saved'] += (int) ($result['images_saved'] ?? 0);
                $stats['specs_found'] += (int) ($result['specs_found'] ?? 0);
                $stats['attributes_saved'] += (int) ($result['attribute_values_saved'] ?? 0);

                $updated = $result['updated_fields'] ?? [];
                if (in_array('content', $updated, true)) {
                    $stats['ai_done']++;
                }

                if ($apply && $updated !== []) {
                    $stats['enriched']++;
                } elseif (! $apply) {
                    $stats['enriched']++;
                }

                $this->line(sprintf(
                    '  found: images=%d specs=%d service=%d docs=%d video=%d%s',
                    (int) ($result['images_found'] ?? 0),
                    (int) ($result['specs_found'] ?? 0),
                    (int) ($result['service_found'] ?? 0),
                    (int) ($result['documents_found'] ?? 0),
                    (int) ($result['video_found'] ?? 0),
                    $updated !== [] ? ' updated=' . implode(',', $updated) : ''
                ));

                foreach (($result['errors'] ?? []) as $error) {
                    $this->warn('  warning: ' . $error);
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn('  ERROR: ' . $e->getMessage());
            }

            usleep($sleep * 1000);
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(
            fn (string $key, int $value): array => [$key, $value],
            array_keys($stats),
            array_values($stats),
        ));

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}

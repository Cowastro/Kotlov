<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditBaniaCatalogCommand extends Command
{
    protected $signature = 'supplier:audit-bania-catalog
        {--dry-run : Preview without database writes}
        {--apply : Apply archive action when --archive-linked-missing is set}
        {--archive-linked-missing : Archive BANIA-linked products missing from recent supplier scans}
        {--stale-hours=24 : Treat BANIA links older than this many hours as missing after a full scan}
        {--include-child-categories : Include products from child categories of the BANIA category scope}
        {--limit= : Limit archive candidates for testing}';

    protected $description = 'Read-only BANIA catalog audit and safe archive candidates for linked stale products.';

    private const SUPPLIER_CODE = 'bania';

    private const CATEGORY_SLUGS = [
        'drovyanye-pechi-dlya-bani',
        'pechi-dlya-bani',
        'dlya-bani',
        'bani-i-sauny',
        'elektrokamenki',
        'pechi-sauna',
        'pechi-kamenka',
        'pechki',
        'pechi',
        'pechi-kaminy',
        'kaminy',
        'topki',
        'kotly',
        'kotly-na-drovah',
        'belorusskie-kotly',
        'kombinirovannye-kotly',
        'kotly-na-ugle',
        'kotly-na-pelletah',
        'pechnoe-i-kaminnoe-lite',
        'dveri-dlya-ban-i-saun',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $archive = (bool) $this->option('archive-linked-missing');
        $staleHours = max(1, (int) $this->option('stale-hours'));
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $cutoff = now()->subHours($staleHours);

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: enabled. Only --archive-linked-missing candidates can be archived.</>'
            : '<fg=yellow;options=bold>DRY RUN: database will not be changed.</>');

        $supplierId = (int) DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier BANIA.by not found.');
            return self::FAILURE;
        }

        $categoryIds = $this->categoryIds((bool) $this->option('include-child-categories'));
        if ($categoryIds === []) {
            $this->error('No target BANIA category ids found.');
            return self::FAILURE;
        }

        $linked = $this->baniaLinkedProducts($supplierId);
        $linkedMissing = $this->linkedMissingCandidates($supplierId, $cutoff, $limit);
        $legacy = $this->legacyCandidates($supplierId, $categoryIds);

        $reportDir = storage_path('app/reports/bania');
        if (! is_dir($reportDir)) {
            mkdir($reportDir, 0775, true);
        }

        $stamp = now()->format('Y-m-d-H-i');
        $linkedMissingPath = $reportDir . "/bania-linked-missing-{$stamp}.csv";
        $legacyPath = $reportDir . "/bania-legacy-archive-candidates-{$stamp}.csv";

        $this->writeCsv($linkedMissingPath, $this->linkedMissingRows($linkedMissing, $supplierId));
        $this->writeCsv($legacyPath, $this->legacyRows($legacy));

        $archived = 0;
        if ($apply && $archive && $linkedMissing->isNotEmpty()) {
            $ids = $linkedMissing
                ->where('has_other_supplier_stock', false)
                ->where('is_archived', false)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($ids !== []) {
                $payload = [
                    'is_archived' => true,
                    'is_active' => false,
                    'in_stock' => false,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('products', 'availability_status')) {
                    $payload['availability_status'] = 'check';
                }

                $archived = DB::table('products')->whereIn('id', $ids)->update($payload);
            }
        }

        $this->table(
            ['metric', 'count'],
            [
                ['bania_supplier_products', $linked->count()],
                ['linked_stale_or_missing_candidates', $linkedMissing->count()],
                ['linked_candidates_safe_to_archive', $linkedMissing->where('has_other_supplier_stock', false)->where('is_archived', false)->count()],
                ['linked_candidates_with_other_supplier_stock', $linkedMissing->where('has_other_supplier_stock', true)->count()],
                ['legacy_unlinked_products_in_bania_categories', $legacy->count()],
                ['archived', $archived],
            ]
        );

        $this->line('Linked missing report: ' . $linkedMissingPath);
        $this->line('Legacy candidates report: ' . $legacyPath);

        if (! $apply) {
            $this->line('Run with --apply --archive-linked-missing only after full BANIA section scans are complete.');
        }

        return self::SUCCESS;
    }

    private function categoryIds(bool $includeChildren): array
    {
        $categories = DB::table('categories')
            ->get(['id', 'parent_id', 'slug'])
            ->map(fn ($category) => [
                'id' => (int) $category->id,
                'parent_id' => (int) $category->parent_id,
                'slug' => (string) $category->slug,
            ]);

        $ids = $categories
            ->whereIn('slug', self::CATEGORY_SLUGS)
            ->pluck('id')
            ->all();

        if (! $includeChildren) {
            return array_values(array_unique($ids));
        }

        $all = array_fill_keys($ids, true);
        do {
            $added = false;
            foreach ($categories as $category) {
                if (isset($all[$category['id']])) {
                    continue;
                }
                if (isset($all[$category['parent_id']])) {
                    $all[$category['id']] = true;
                    $added = true;
                }
            }
        } while ($added);

        return array_keys($all);
    }

    private function baniaLinkedProducts(int $supplierId): Collection
    {
        return DB::table('supplier_products as sp')
            ->leftJoin('products as p', 'p.id', '=', 'sp.product_id')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->where('sp.supplier_id', $supplierId)
            ->select([
                'sp.id as supplier_product_id',
                'sp.product_id',
                'sp.supplier_article',
                'sp.supplier_name',
                'sp.source_url',
                'sp.price_byn',
                'sp.in_stock as supplier_in_stock',
                'sp.stock_status',
                'sp.last_synced_at',
                'p.sku',
                'p.name as product_name',
                'p.price as product_price',
                'p.in_stock as product_in_stock',
                'p.is_active',
                'p.is_archived',
                'b.name as brand',
                'c.name as category',
                'c.slug as category_slug',
            ])
            ->orderBy('p.id')
            ->get();
    }

    private function linkedMissingCandidates(int $supplierId, \Carbon\CarbonInterface $cutoff, ?int $limit): Collection
    {
        $query = $this->baniaLinkedProducts($supplierId)
            ->filter(fn ($row) => $row->product_id && (! $row->last_synced_at || $row->last_synced_at < $cutoff->toDateTimeString()))
            ->values();

        $productIds = $query->pluck('product_id')->filter()->unique()->values()->all();
        $otherStock = $this->otherSupplierStockMap($supplierId, $productIds);

        $rows = $query->map(function ($row) use ($otherStock) {
            $row->has_other_supplier_stock = isset($otherStock[(int) $row->product_id]);
            return $row;
        });

        return $limit ? $rows->take($limit)->values() : $rows;
    }

    private function legacyCandidates(int $supplierId, array $categoryIds): Collection
    {
        return DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('supplier_products as bania_sp', function ($join) use ($supplierId) {
                $join->on('bania_sp.product_id', '=', 'p.id')
                    ->where('bania_sp.supplier_id', $supplierId);
            })
            ->whereIn('p.category_id', $categoryIds)
            ->whereNull('bania_sp.id')
            ->where('p.is_archived', false)
            ->select([
                'p.id',
                'p.sku',
                'p.name',
                'p.price',
                'p.in_stock',
                'p.is_active',
                'b.name as brand',
                'c.name as category',
                'c.slug as category_slug',
            ])
            ->orderBy('c.name')
            ->orderBy('b.name')
            ->orderBy('p.name')
            ->get();
    }

    private function otherSupplierStockMap(int $supplierId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return DB::table('supplier_products')
            ->whereIn('product_id', $productIds)
            ->where('supplier_id', '<>', $supplierId)
            ->where('in_stock', true)
            ->pluck('product_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private function linkedMissingRows(Collection $rows, int $supplierId): array
    {
        return $rows->map(fn ($row) => [
            'action' => $row->has_other_supplier_stock || $row->is_archived ? 'skip' : 'archive_candidate',
            'reason' => $row->has_other_supplier_stock ? 'other supplier in stock' : 'BANIA link was not refreshed by recent full scan',
            'supplier_product_id' => $row->supplier_product_id,
            'product_id' => $row->product_id,
            'sku' => $row->sku,
            'product_name' => $row->product_name,
            'brand' => $row->brand,
            'category' => $row->category,
            'supplier_article' => $row->supplier_article,
            'supplier_name' => $row->supplier_name,
            'source_url' => $row->source_url,
            'supplier_price' => $row->price_byn,
            'product_price' => $row->product_price,
            'supplier_in_stock' => (int) $row->supplier_in_stock,
            'stock_status' => $row->stock_status,
            'product_in_stock' => (int) $row->product_in_stock,
            'has_other_supplier_stock' => (int) $row->has_other_supplier_stock,
            'is_active' => (int) $row->is_active,
            'is_archived' => (int) $row->is_archived,
            'last_synced_at' => $row->last_synced_at,
        ])->all();
    }

    private function legacyRows(Collection $rows): array
    {
        return $rows->map(fn ($row) => [
            'action' => 'manual_review_legacy',
            'reason' => 'Product is in BANIA category scope but has no BANIA supplier link',
            'product_id' => $row->id,
            'sku' => $row->sku,
            'product_name' => $row->name,
            'brand' => $row->brand,
            'category' => $row->category,
            'category_slug' => $row->category_slug,
            'product_price' => $row->price,
            'product_in_stock' => (int) $row->in_stock,
            'is_active' => (int) $row->is_active,
        ])->all();
    }

    private function writeCsv(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Cannot write report: ' . $path);
        }

        if ($rows === []) {
            fputcsv($handle, ['empty'], ',');
            fclose($handle);
            return;
        }

        fputcsv($handle, array_keys($rows[0]), ',');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',');
        }

        fclose($handle);
    }
}

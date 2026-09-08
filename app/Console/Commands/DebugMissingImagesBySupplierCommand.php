<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Site-wide breakdown of active products with NO photo at all (empty/null
 * images — the "hole" class of problem found in Мета-Бел's brand page,
 * distinct from debug:find-broken-images which only catches a populated
 * images array pointing at a dead file/URL). Grouped by supplier so the
 * worst offenders are obvious at a glance.
 *
 *   php artisan debug:missing-images-by-supplier
 *   php artisan debug:missing-images-by-supplier --supplier=metabel --list
 */
class DebugMissingImagesBySupplierCommand extends Command
{
    protected $signature = 'debug:missing-images-by-supplier
        {--supplier= : Limit to one supplier code (see suppliers.code)}
        {--list : List the actual product names/slugs, not just counts}
        {--limit=200 : Cap rows shown with --list}';

    protected $description = 'Count active products with a completely empty images array, grouped by supplier';

    public function handle(): int
    {
        $supplierFilter = $this->option('supplier');
        $list = (bool) $this->option('list');
        $limit = (int) $this->option('limit');

        $missingWhere = function ($q) {
            $q->whereNull('p.images')
              ->orWhere('p.images', '')
              ->orWhere('p.images', '[]')
              ->orWhereRaw("JSON_VALID(p.images) AND JSON_LENGTH(p.images) = 0");
        };

        $base = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->join('suppliers as s', 'sp.supplier_id', '=', 's.id')
            ->where('p.is_archived', false)
            ->when($supplierFilter, fn ($q) => $q->where('s.code', $supplierFilter))
            ->where($missingWhere);

        if ($list) {
            $rows = (clone $base)
                ->distinct()
                ->orderBy('s.code')
                ->orderBy('p.id')
                ->limit($limit)
                ->get(['p.id', 'p.sku', 'p.name', 'p.slug', 's.code as supplier_code']);

            $this->info("Products with empty images (limit {$limit}):");
            $this->table(
                ['id', 'sku', 'supplier', 'name', 'slug'],
                $rows->map(fn ($r) => [$r->id, $r->sku, $r->supplier_code, mb_strimwidth($r->name, 0, 50, '...'), $r->slug])->all()
            );

            return self::SUCCESS;
        }

        $counts = (clone $base)
            ->select('s.code as supplier_code', 's.name as supplier_name', DB::raw('COUNT(DISTINCT p.id) as missing'))
            ->groupBy('s.code', 's.name')
            ->orderByDesc('missing')
            ->get();

        $totalActive = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->join('suppliers as s', 'sp.supplier_id', '=', 's.id')
            ->where('p.is_archived', false)
            ->when($supplierFilter, fn ($q) => $q->where('s.code', $supplierFilter))
            ->distinct()
            ->count('p.id');

        $totalMissing = (int) $counts->sum('missing');

        $this->info(sprintf(
            'Active products with a supplier link: %d total, %d with no photo at all (%.1f%%)',
            $totalActive,
            $totalMissing,
            $totalActive > 0 ? $totalMissing / $totalActive * 100 : 0
        ));

        $this->table(
            ['supplier code', 'supplier name', 'missing photos'],
            $counts->map(fn ($r) => [$r->supplier_code, $r->supplier_name, $r->missing])->all()
        );

        return self::SUCCESS;
    }
}

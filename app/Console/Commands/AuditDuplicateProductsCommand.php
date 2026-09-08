<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit duplicate products in the database.
 *
 * Usage:
 *   php artisan products:audit-duplicates            # summary only
 *   php artisan products:audit-duplicates --full     # full list per group
 *   php artisan products:audit-duplicates --brand=Везувий
 */
class AuditDuplicateProductsCommand extends Command
{
    protected $signature = 'products:audit-duplicates
                            {--full : Show every product in each duplicate group}
                            {--brand= : Filter by brand name}
                            {--min=2 : Minimum duplicates in group to show}';

    protected $description = 'Find duplicate products (same name, or slug with -N suffix)';

    public function handle(): int
    {
        $brandFilter = $this->option('brand');
        $min         = (int) $this->option('min');

        $this->info('=== ОБЩАЯ СТАТИСТИКА ===');

        $total  = DB::table('products')->count();
        $active = DB::table('products')->where('is_active', true)->count();
        $this->line("Всего товаров: {$total}");
        $this->line("Активных: {$active}");

        // Slug с суффиксом -N (вероятные авто-дубли от uniqueSlug)
        $slugSuffix = DB::table('products')
            ->whereRaw("slug REGEXP '-[0-9]+$'")
            ->count();
        $this->line("Slug с суффиксом -N: {$slugSuffix}");

        // Без supplier_products
        $noSp = DB::table('products')
            ->whereNotExists(fn($q) => $q->from('supplier_products')->whereColumn('product_id', 'products.id'))
            ->count();
        $this->line("Без supplier_products: {$noSp}");

        // ── Дубли по имени ─────────────────────────────────────────────────
        $this->info("\n=== ДУБЛИ ПО ИМЕНИ (одно название — несколько записей) ===");

        $query = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->selectRaw('LOWER(p.name) as name_lower,
                         COUNT(p.id)   as cnt,
                         GROUP_CONCAT(p.id       ORDER BY p.id SEPARATOR "|") as ids,
                         GROUP_CONCAT(p.slug      ORDER BY p.id SEPARATOR "|") as slugs,
                         GROUP_CONCAT(p.is_active ORDER BY p.id SEPARATOR "|") as actives,
                         GROUP_CONCAT(IFNULL(b.name,"—") ORDER BY p.id SEPARATOR "|") as brands');

        if ($brandFilter) {
            $query->where('b.name', 'like', '%' . $brandFilter . '%');
        }

        $groups = $query
            ->groupByRaw('LOWER(p.name)')
            ->having('cnt', '>=', $min)
            ->orderByDesc('cnt')
            ->orderByRaw('LOWER(p.name)')
            ->get();

        if ($groups->isEmpty()) {
            $this->line('Дублей по имени не найдено.');
        } else {
            $this->line("Найдено групп: " . $groups->count());
            $totalDups = $groups->sum(fn($g) => $g->cnt - 1);
            $this->line("Лишних записей (дублей сверх одной): {$totalDups}");

            if ($this->option('full')) {
                $this->newLine();
                foreach ($groups as $g) {
                    $ids     = explode('|', $g->ids);
                    $slugs   = explode('|', $g->slugs);
                    $actives = explode('|', $g->actives);
                    $brands  = explode('|', $g->brands);

                    $this->warn("[{$g->cnt}x] {$g->name_lower}");
                    foreach ($ids as $i => $id) {
                        $active = $actives[$i] ? '✓' : '✗';
                        $this->line("    [{$active}] id={$id}  brand={$brands[$i]}  slug={$slugs[$i]}");
                    }
                }
            } else {
                $this->newLine();
                foreach ($groups->take(50) as $g) {
                    $this->warn("[{$g->cnt}x] {$g->name_lower}  (ids: {$g->ids})");
                }
                if ($groups->count() > 50) {
                    $this->line('... и ещё ' . ($groups->count() - 50) . ' групп. Используйте --full для деталей.');
                }
            }
        }

        // ── Slug-суффикс примеры ────────────────────────────────────────────
        $this->info("\n=== SLUG С СУФФИКСОМ -N (создан uniqueSlug при конфликте) ===");

        $suffixQuery = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->whereRaw("p.slug REGEXP '-[0-9]+$'")
            ->select('p.id', 'p.slug', 'p.name', 'p.is_active', 'b.name as brand');

        if ($brandFilter) {
            $suffixQuery->where('b.name', 'like', '%' . $brandFilter . '%');
        }

        $suffixRows = $suffixQuery->orderBy('p.slug')->get();

        if ($suffixRows->isEmpty()) {
            $this->line('Не найдено.');
        } else {
            $this->line("Найдено: " . $suffixRows->count());
            if ($this->option('full')) {
                foreach ($suffixRows as $r) {
                    $a = $r->is_active ? '✓' : '✗';
                    $this->line("  [{$a}] id={$r->id}  brand=" . ($r->brand ?? '—') . "  {$r->slug}  — {$r->name}");
                }
            } else {
                foreach ($suffixRows->take(40) as $r) {
                    $a = $r->is_active ? '✓' : '✗';
                    $this->line("  [{$a}] {$r->slug}");
                }
                if ($suffixRows->count() > 40) {
                    $this->line('... и ещё ' . ($suffixRows->count() - 40) . '. Используйте --full.');
                }
            }
        }

        // ── По брендам сводка ───────────────────────────────────────────────
        $this->info("\n=== СВОДКА ДУБЛЕЙ ПО БРЕНДАМ ===");

        $brandStats = DB::table('products as p')
            ->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
            ->selectRaw('IFNULL(b.name,"без бренда") as brand,
                         COUNT(p.id) as total,
                         SUM(CASE WHEN p.slug REGEXP "-[0-9]+$" THEN 1 ELSE 0 END) as slug_dups')
            ->groupBy('b.id', 'b.name')
            ->having('slug_dups', '>', 0)
            ->orderByDesc('slug_dups')
            ->get();

        if ($brandStats->isEmpty()) {
            $this->line('Нет брендов с дублями по slug.');
        } else {
            $headers = ['Бренд', 'Всего', 'Slug-дублей'];
            $rows    = $brandStats->map(fn($r) => [$r->brand, $r->total, $r->slug_dups])->toArray();
            $this->table($headers, $rows);
        }

        return self::SUCCESS;
    }
}

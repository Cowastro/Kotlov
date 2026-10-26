<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who supplies the cards of a brand: before taking a brand from a new price,
 * see how many cards we have and which suppliers already carry them.
 * Read-only; brands are matched by slug or by a word of the brand name.
 *
 *   php artisan catalog:brand-suppliers hotex ferroli s-tank
 */
class BrandSuppliersCommand extends Command
{
    protected $signature = 'catalog:brand-suppliers {brands* : Brand slugs or words of brand names}';

    protected $description = 'Cards of each brand and the suppliers linked to them';

    public function handle(): int
    {
        foreach ($this->argument('brands') as $word) {
            $key = Str::slug($word, '_');
            $brandIds = DB::table('brands')
                ->where(fn ($q) => $q->where('slug', $word)->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(str_replace('-', ' ', $word)).'%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($word).'%']))
                ->pluck('id');
            $this->line("metric.brand_suppliers.{$key}_brands=".$brandIds->count());
            if ($brandIds->isEmpty()) {
                continue;
            }

            $cards = DB::table('products')->whereIn('brand_id', $brandIds)->where('is_archived', false);
            $this->line("metric.brand_suppliers.{$key}_cards=".(clone $cards)->count());
            $this->line("metric.brand_suppliers.{$key}_active=".(clone $cards)->where('is_active', true)->count());
            $this->line("metric.brand_suppliers.{$key}_no_supplier=".(clone $cards)->whereNotExists(fn ($q) => $q->from('supplier_products')->whereColumn('supplier_products.product_id', 'products.id'))->count());

            DB::table('supplier_products as sp')
                ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->join('products as p', 'p.id', '=', 'sp.product_id')
                ->whereIn('p.brand_id', $brandIds)
                ->where('p.is_archived', false)
                ->groupBy('s.code')
                ->selectRaw('s.code, COUNT(*) as links, SUM(sp.in_stock = 1) as in_stock, MAX(sp.last_synced_at) as last_synced')
                ->get()
                ->each(function ($row) use ($key): void {
                    $age = $row->last_synced ? (int) abs(now()->diffInDays($row->last_synced)) : 9999;
                    $this->line(sprintf('audit.brand_suppliers.link=%s:%s:%d:%d:%d', $key, Str::slug((string) $row->code, '_'), $row->links, (int) $row->in_stock, $age));
                });
        }

        return self::SUCCESS;
    }
}

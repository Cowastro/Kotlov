<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: shows how the structured «Характеристики» tab is fed for the
 * categories of Rusklimat products — which attributes (in_product) exist per
 * category, and how many Rusklimat products actually have attribute VALUES
 * (i.e. a populated tab) vs none. Helps decide how to backfill specs as
 * product_attribute_values instead of products.specs / content.
 *
 *   php artisan supplier:inspect-attributes-rusklimat
 *   php artisan supplier:inspect-attributes-rusklimat --category=305
 */
class InspectAttributesRusklimatCommand extends Command
{
    protected $signature = 'supplier:inspect-attributes-rusklimat
        {--category=  : Show the attribute list for one category id}';

    protected $description = 'Read-only: attribute-tab coverage for Rusklimat product categories.';

    private const SUPPLIER_CODE = 'rusklimat';

    public function handle(): int
    {
        $supplierId = DB::table('suppliers')->where('code', self::SUPPLIER_CODE)->value('id');
        if (! $supplierId) {
            $this->error('Supplier not found.');
            return self::FAILURE;
        }

        // Active rusklimat products grouped by category, with attribute-value coverage.
        $rows = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->leftJoin('categories as c', 'p.category_id', '=', 'c.id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.is_archived', false)
            ->groupBy('p.category_id', 'c.name')
            ->select('p.category_id', 'c.name as category', DB::raw('count(distinct p.id) as products'))
            ->orderByDesc(DB::raw('count(distinct p.id)'))
            ->get();

        $withValues = DB::table('products as p')
            ->join('supplier_products as sp', 'p.id', '=', 'sp.product_id')
            ->where('sp.supplier_id', $supplierId)
            ->where('p.is_archived', false)
            ->whereExists(function ($q) {
                $q->from('product_attribute_values as pav')->whereColumn('pav.product_id', 'p.id');
            })
            ->select('p.category_id', DB::raw('count(distinct p.id) as c'))
            ->groupBy('p.category_id')
            ->pluck('c', 'category_id');

        // Attribute count (in_product) per category.
        $attrCount = DB::table('attributes')
            ->where('in_product', true)
            ->select('category_id', DB::raw('count(*) as c'))
            ->groupBy('category_id')
            ->pluck('c', 'category_id');

        $this->info('── Rusklimat active products: «Характеристики» tab coverage ──');
        $this->table(
            ['cat_id', 'category', 'active', 'with tab (has values)', 'in_product attrs in cat'],
            $rows->map(fn ($r) => [
                $r->category_id,
                mb_substr((string) ($r->category ?? '—'), 0, 28),
                $r->products,
                $withValues[$r->category_id] ?? 0,
                $attrCount[$r->category_id] ?? 0,
            ])->all()
        );

        // Optional: list attributes for one category.
        if ($this->option('category')) {
            $catId = (int) $this->option('category');
            $attrs = DB::table('attributes')
                ->where('category_id', $catId)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'type', 'suffix', 'in_product', 'in_filter']);

            $this->newLine();
            $this->info(sprintf('── Attributes of category %d ──', $catId));
            $this->table(
                ['id', 'name', 'type', 'suffix', 'in_product', 'in_filter'],
                $attrs->map(fn ($a) => [
                    $a->id, mb_substr((string) $a->name, 0, 30), $a->type,
                    $a->suffix, $a->in_product ? 'yes' : '', $a->in_filter ? 'yes' : '',
                ])->all()
            );
        }

        $this->newLine();
        $this->line('<fg=yellow>Вывод:</> вкладка берётся из product_attribute_values. Если «with tab» ≈ 0 — спарсенные specs надо писать в attribute_values (а не в products.specs/content).');

        return self::SUCCESS;
    }
}

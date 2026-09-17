<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Temporary read-only diagnostic: dumps Ariston products with their current
 * price alongside any linked Thermostudio supplier_products row, so we can
 * see which products are linked (and thus reachable by
 * supplier:sync-thermostudio-pricelist --sync-retail-prices) versus
 * unmatched and stuck on a stale price.
 *
 * Safe to delete once the Ariston opt/retail price cleanup is done.
 */
class DebugAristonPricesCommand extends Command
{
    protected $signature = 'debug:ariston-prices {--category= : Filter by category name substring}';

    protected $description = 'Dump Ariston products with price + linked Thermostudio supplier_products info';

    public function handle(): int
    {
        $brandId = DB::table('brands')->where('slug', 'ariston')->value('id');
        if (! $brandId) {
            $this->error('Ariston brand not found.');
            return self::FAILURE;
        }

        $supplierId = DB::table('suppliers')->where('code', 'thermostudio')->value('id');

        $query = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.brand_id', $brandId)
            ->where('products.is_archived', false)
            ->select('products.id', 'products.sku', 'products.name', 'products.price', 'categories.name as category');

        if ($cat = $this->option('category')) {
            $query->where('categories.name', 'like', '%' . $cat . '%');
        }

        $products = $query->orderBy('products.id')->get();

        $supplierByProduct = [];
        if ($supplierId) {
            DB::table('supplier_products')
                ->where('supplier_id', $supplierId)
                ->whereIn('product_id', $products->pluck('id'))
                ->get(['product_id', 'supplier_article', 'price_byn', 'raw'])
                ->each(function ($row) use (&$supplierByProduct) {
                    $supplierByProduct[$row->product_id] = $row;
                });
        }

        $this->line(sprintf('Ariston products: %d', $products->count()));

        foreach ($products as $p) {
            $sp = $supplierByProduct[$p->id] ?? null;
            $retail = null;
            if ($sp && $sp->raw) {
                $raw = json_decode($sp->raw, true);
                $retail = $raw['retail_byn'] ?? null;
            }

            $flag = '';
            if ($sp && $retail !== null && (float) $p->price !== (float) $retail) {
                $flag = ' <<< PRICE MISMATCH (retail=' . $retail . ')';
            } elseif (! $sp) {
                $flag = ' <<< UNLINKED';
            }

            $this->line(sprintf(
                '%d | %s | price=%s | cat=%s | article=%s | opt=%s | retail=%s | %s%s',
                $p->id,
                $p->sku,
                $p->price,
                $p->category,
                $sp->supplier_article ?? '-',
                $sp->price_byn ?? '-',
                $retail ?? '-',
                $p->name,
                $flag
            ));
        }

        return self::SUCCESS;
    }
}

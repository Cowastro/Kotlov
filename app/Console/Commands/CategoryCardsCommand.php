<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only: every card of a category with its brand, price and supplier links,
 * to check for existing cards before adding a supplier's range.
 *
 *   php artisan catalog:category-cards dveri-dlya-bani-i-sauny
 */
class CategoryCardsCommand extends Command
{
    protected $signature = 'catalog:category-cards {categories?* : Category slugs} {--tree : Every category: id, parent, active, cards}';

    protected $description = 'List the cards of categories with brand, price and suppliers';

    public function handle(): int
    {
        if ($this->option('tree')) {
            $counts = DB::table('products')->where('is_archived', false)->groupBy('category_id')->pluck(DB::raw('count(*)'), 'category_id');
            foreach (DB::table('categories')->orderBy('parent_id')->orderBy('id')->get(['id', 'parent_id', 'slug', 'is_active']) as $category) {
                $this->line(sprintf('audit.category_cards.tree=%d:%d:%s:%d:%d', $category->id, (int) $category->parent_id, Str::slug((string) $category->slug) ?: 'none',
                    (int) $category->is_active, (int) ($counts[$category->id] ?? 0)));
            }

            return self::SUCCESS;
        }
        foreach ($this->argument('categories') as $slug) {
            $category = DB::table('categories')->where('slug', $slug)->first(['id', 'parent_id', 'is_active']);
            if (! $category) {
                $this->line('audit.category_cards.missing='.Str::slug($slug));

                continue;
            }
            $children = DB::table('categories')->where('parent_id', $category->id)->pluck('slug');
            $this->line(sprintf('audit.category_cards.category=%s:%d:%d:%d:%s', Str::slug($slug), $category->id, (int) $category->parent_id, (int) $category->is_active,
                $children->map(fn ($child) => Str::slug((string) $child))->implode(',') ?: 'none'));

            $cards = DB::table('products as p')->leftJoin('brands as b', 'b.id', '=', 'p.brand_id')
                ->where('p.category_id', $category->id)->where('p.is_archived', false)
                ->orderBy('p.id')->get(['p.id', 'p.slug', 'p.price', 'p.is_active', 'b.slug as brand']);
            $links = DB::table('supplier_products as sp')->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
                ->whereIn('sp.product_id', $cards->pluck('id'))->get(['sp.product_id', 's.code'])->groupBy('product_id');
            $this->line('metric.category_cards.'.Str::slug($slug, '_').'='.$cards->count());
            foreach ($cards as $card) {
                $this->line(sprintf('audit.category_cards.card=%d:%s:%s:%d:%d:%s', $card->id, $card->slug, Str::slug((string) $card->brand) ?: 'none',
                    (int) round((float) $card->price), (int) $card->is_active,
                    $links->get($card->id, collect())->map(fn ($link) => Str::slug((string) $link->code, '_'))->unique()->implode(',') ?: 'none'));
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read only: what hangs on a card, to tell which of two duplicates stays — brand, category, shown or not, price,
 * photos, length of the description, supplier links (code and article), 1C rows, order lines.
 *
 *   php artisan catalog:describe-cards 9011 teplov-i-suxov-zont-zm-r-430-05-d120-u3
 */
class DescribeCardsCommand extends Command
{
    protected $signature = 'catalog:describe-cards {cards* : Card ids or slugs}';

    protected $description = 'Show what hangs on cards (links, 1C rows, photos, orders) to choose between duplicates';

    public function handle(): int
    {
        foreach ((array) $this->argument('cards') as $value) {
            $product = ctype_digit((string) $value) ? Product::query()->find((int) $value) : Product::query()->where('slug', $value)->first();
            if (! $product) {
                $this->line('audit.describe_cards.missing='.Str::slug((string) $value));

                continue;
            }
            $links = DB::table('supplier_products')->join('suppliers', 'suppliers.id', '=', 'supplier_products.supplier_id')
                ->where('supplier_products.product_id', $product->id)
                ->get(['suppliers.code', 'supplier_products.supplier_article'])
                ->map(fn ($link) => $link->code.'='.preg_replace('/[^A-Za-z0-9._-]/', '', (string) $link->supplier_article))->implode(';');
            $this->line(sprintf('audit.describe_cards.card=%d|%s|b%d|c%d|act%d|arch%d|%.2f|img%d|txt%d|orders%d|onec%d|%s',
                $product->id, $product->slug, (int) $product->brand_id, (int) $product->category_id, (int) $product->is_active, (int) $product->is_archived,
                (float) $product->price, count($product->imagePaths()), mb_strlen(strip_tags((string) $product->content)),
                DB::table('order_items')->where('product_id', $product->id)->count(),
                DB::table('integration_products')->where('product_id', $product->id)->count(), $links ?: '-'));
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives a product a new URL slug and keeps the old address working with a
 * 301 redirect; redirects that pointed at the old address are re-pointed so
 * no chains appear.
 *
 *   php artisan catalog:rename-product-slug 5437 kamin-ecokamin-krit-grafit            # dry run
 *   php artisan catalog:rename-product-slug 5437 kamin-ecokamin-krit-grafit --apply
 */
class RenameProductSlugCommand extends Command
{
    protected $signature = 'catalog:rename-product-slug {product : Product id} {slug : New slug} {--apply : Save the slug and the redirect}';

    protected $description = 'Rename a product slug with a 301 redirect from the old URL';

    public function handle(): int
    {
        $product = Product::query()->with('category')->find((int) $this->argument('product'));
        $slug = (string) $this->argument('slug');
        if (! $product || ! $product->category) {
            $this->error('Товар или его категория не найдены.');

            return self::FAILURE;
        }
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            $this->error('Адрес может содержать только латиницу, цифры и дефисы.');

            return self::FAILURE;
        }
        if (Product::query()->where('slug', $slug)->whereKeyNot($product->id)->exists()) {
            $this->error('Этот адрес уже занят другим товаром.');

            return self::FAILURE;
        }

        $from = '/'.$product->category->slug.'/'.$product->slug;
        $to = '/'.$product->category->slug.'/'.$slug;
        $chained = DB::table('redirects')->where('to_url', $from)->count();
        $this->line(sprintf('audit.product_slug.rename=%d:%s:%s', $product->id, $product->slug, $slug));
        $this->line("metric.product_slug.chained_redirects={$chained}");

        if (! $this->option('apply') || $product->slug === $slug) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($product, $slug, $from, $to): void {
            $product->forceFill(['slug' => $slug])->save();
            DB::table('redirects')->where('to_url', $from)->update(['to_url' => $to, 'updated_at' => now()]);
            // The new address must not be shadowed by an old redirect from it.
            DB::table('redirects')->where('from_url', $to)->delete();
            DB::table('redirects')->updateOrInsert(['from_url' => $from], ['to_url' => $to, 'status_code' => 301, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]);
        });
        $this->line('metric.product_slug.renamed=1');

        return self::SUCCESS;
    }
}

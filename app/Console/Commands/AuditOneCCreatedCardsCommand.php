<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Read-only: what the cards created from 1C rows still lack — photo, own photo (not borrowed
 * from another card), description, specs, price.
 *
 *   php artisan integration:audit-onec-cards
 */
class AuditOneCCreatedCardsCommand extends Command
{
    protected $signature = 'integration:audit-onec-cards {--source=onec : Integration source code}';

    protected $description = 'List what the cards created from 1C still lack';

    public function handle(): int
    {
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $ids = IntegrationProduct::query()->where('integration_source_id', $source?->id)->where('match_method', 'manual_created')
            ->whereNotNull('product_id')->pluck('product_id');
        $products = Product::query()->whereIn('id', $ids)->where('is_archived', false)->with('category:id,slug')->get();

        $stats = ['cards' => $products->count(), 'complete' => 0, 'no_photo' => 0, 'borrowed_photo' => 0, 'no_content' => 0, 'no_specs' => 0, 'no_price' => 0, 'inactive' => 0];
        foreach ($products as $product) {
            $images = (array) $product->images;
            $own = Str::lower(Str::slug((string) $product->sku));
            $flags = array_keys(array_filter([
                'no_photo' => $images === [],
                'borrowed_photo' => $images !== [] && ! collect($images)->contains(fn ($path) => str_contains(Str::lower((string) $path), $own)),
                'no_content' => trim(strip_tags((string) $product->content)) === '',
                'no_specs' => empty($product->specs),
                'no_price' => (float) $product->price <= 0,
                'inactive' => ! $product->is_active,
            ]));
            if ($flags === []) {
                $stats['complete']++;

                continue;
            }
            foreach ($flags as $flag) {
                $stats[$flag]++;
            }
            $this->line(sprintf('audit.onec_cards.card=%d:%s:%s:%s', $product->id, Str::slug((string) $product->category?->slug) ?: 'none',
                Str::limit($product->slug, 70, ''), implode('.', $flags)));
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.onec_cards.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}

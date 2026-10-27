<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Sets the retail price of given cards — e.g. a market price picked by hand for stock bought
 * from another company that has no cost in 1C. The price is shown at once (confirmation cleared).
 *
 *   php artisan catalog:set-price 30257:14 30266:16            # dry run
 *   php artisan catalog:set-price 30257:14 30266:16 --apply
 */
class SetProductPriceCommand extends Command
{
    protected $signature = 'catalog:set-price {pairs* : PRODUCT_ID:PRICE} {--apply : Save the prices}';

    protected $description = 'Set the retail price of given cards';

    public function handle(): int
    {
        $stats = ['cards' => 0, 'changed' => 0, 'skipped' => 0];
        foreach ((array) $this->argument('pairs') as $pair) {
            [$id, $price] = array_pad(explode(':', (string) $pair, 2), 2, '');
            $product = Product::query()->find((int) $id);
            $price = round((float) str_replace(',', '.', $price), 2);
            if (! $product || $price <= 0) {
                $stats['skipped']++;
                $this->line('audit.set_price.skipped='.(int) $id);

                continue;
            }
            $stats['cards']++;
            $this->line(sprintf('audit.set_price.card=%d:%s:%s', $product->id, number_format((float) $product->price, 2, '.', ''), number_format($price, 2, '.', '')));
            if (abs((float) $product->price - $price) < 0.01 && ! $product->requires_price_confirmation) {
                continue;
            }
            $stats['changed']++;
            if ($this->option('apply')) {
                $product->forceFill(['price' => $price, 'requires_price_confirmation' => false])->save();
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.set_price.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}

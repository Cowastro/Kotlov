<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * Hand-verified retail prices from ООО «АВОТАР» price list dated 24.09.2026.
 * Source column: «Цена розничная BYN с НДС за единицу (шт.)».
 *
 * The public KOTLOV cards combine colour and left/right variants, while the
 * supplier price list contains separate rows. Retail is identical for every
 * colour and side within the same Smart Line length, so matching by the exact
 * KOTLOV SKU and length is unambiguous.
 *
 *   php artisan supplier:sync-avotar-retail
 *   php artisan supplier:sync-avotar-retail --apply
 */
class SyncAvotarRetailPricesCommand extends Command
{
    protected $signature = 'supplier:sync-avotar-retail {--apply : Write price changes}';

    protected $description = 'Sync verified Mr. Tektum Smart Line retail prices from АВОТАР price list dated 24.09.2026.';

    /** @var array<string,array{price:float,length:string}> */
    private const MAP = [
        'PS-010.839' => ['price' => 350.00, 'length' => '1.1'],
        'PS-010.840' => ['price' => 440.00, 'length' => '1.6'],
        'PS-010.841' => ['price' => 530.00, 'length' => '2.1'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $products = Product::query()
            ->whereIn('sku', array_keys(self::MAP))
            ->get()
            ->keyBy('sku');

        $missing = [];
        $invalid = [];
        $changed = 0;
        $unchanged = 0;

        foreach (self::MAP as $sku => $item) {
            $product = $products->get($sku);
            if (! $product) {
                $missing[] = $sku;
                continue;
            }

            $normalizedName = str_replace(',', '.', mb_strtolower((string) $product->name));
            if (! str_contains($normalizedName, 'tektum') || ! str_contains($normalizedName, $item['length'])) {
                $invalid[] = sprintf('%s: %s', $sku, $product->name);
                continue;
            }

            $oldPrice = (float) $product->price;
            $newPrice = (float) $item['price'];
            if (abs($oldPrice - $newPrice) < 0.005) {
                $unchanged++;
                continue;
            }

            $this->line(sprintf(
                '%s %s: %s BYN -> %s BYN',
                $sku,
                $product->name,
                number_format($oldPrice, 2, '.', ''),
                number_format($newPrice, 2, '.', '')
            ));
            $changed++;

            if ($apply) {
                $product->forceFill(['price' => $newPrice])->save();
            }
        }

        if ($missing !== []) {
            $this->warn('Missing SKUs: ' . implode(', ', $missing));
        }
        if ($invalid !== []) {
            $this->error('Safety check failed: ' . implode('; ', $invalid));
        }

        $this->info(sprintf(
            '%s matched=%d changed=%d unchanged=%d missing=%d invalid=%d',
            $apply ? 'APPLIED' : 'DRY-RUN',
            $products->count(),
            $changed,
            $unchanged,
            count($missing),
            count($invalid)
        ));

        return ($missing === [] && $invalid === []) ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * One-off dump of the live catalog's {id, sku, name, brand, price, currency}
 * for active, non-archived products — used to sync prices into an external
 * marketplace export (sbg.by / deal.by) by fuzzy name matching, done locally.
 *
 * Prints NDJSON between two markers so it can be pulled straight out of the
 * GitHub Actions log rather than published anywhere.
 *
 *   php artisan catalog:export-prices
 */
class ExportCatalogPricesCommand extends Command
{
    protected $signature = 'catalog:export-prices {--brand= : Optional brand name filter}';

    protected $description = 'Dump {sku, name, brand, price, currency} for active products as NDJSON (for external price sync)';

    public function handle(): int
    {
        $query = Product::query()
            ->where('is_archived', false)
            ->where('is_active', true)
            ->with('brand:id,name')
            ->orderBy('id');

        if ($brand = trim((string) $this->option('brand'))) {
            $query->whereHas('brand', fn ($q) => $q->where('name', 'like', '%' . $brand . '%'));
        }

        $this->info('EXPORT_START');

        $query->chunk(500, function ($products) {
            foreach ($products as $product) {
                $this->line(json_encode([
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand' => $product->brand->name ?? null,
                    'price' => $product->price !== null ? (float) $product->price : null,
                    'currency' => $product->currency,
                ], JSON_UNESCAPED_UNICODE));
            }
        });

        $this->info('EXPORT_END');

        return self::SUCCESS;
    }
}

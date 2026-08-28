<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

/**
 * One-off dump of the live catalog's {id, sku, name, brand, price, currency}
 * for active, non-archived products — used to sync prices into an external
 * marketplace export (sbg.by / deal.by) by fuzzy name matching, done locally.
 *
 * Writes NDJSON to public/exports/ (fetched directly over HTTPS and deleted
 * right after) instead of printing to the GitHub Actions log — GH Actions
 * masks any log substring that happens to match a registered secret (e.g.
 * SERVER_PORT's digits), which was silently corrupting price numbers into
 * "***" when printed there.
 *
 *   php artisan catalog:export-prices
 */
class ExportCatalogPricesCommand extends Command
{
    protected $signature = 'catalog:export-prices {--brand= : Optional brand name filter} {--delete : Delete the exported file instead of writing it} {--include-archived : Also include archived/inactive products}';

    protected $description = 'Write {sku, name, brand, price, currency} for active products as NDJSON to public/exports/ (for external price sync)';

    private const OUTPUT_PATH = 'exports/kotlov-catalog-export.ndjson';

    public function handle(): int
    {
        $path = public_path(self::OUTPUT_PATH);

        if ($this->option('delete')) {
            if (file_exists($path)) {
                unlink($path);
                $this->info('Deleted: ' . $path);
            } else {
                $this->info('Nothing to delete.');
            }

            return self::SUCCESS;
        }

        $query = Product::query()
            ->with('brand:id,name')
            ->orderBy('id');

        if (! $this->option('include-archived')) {
            $query->where('is_archived', false)->where('is_active', true);
        }

        if ($brand = trim((string) $this->option('brand'))) {
            $query->whereHas('brand', fn ($q) => $q->where('name', 'like', '%' . $brand . '%'));
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $handle = fopen($path, 'w');
        $count = 0;

        $query->chunk(500, function ($products) use ($handle, &$count) {
            foreach ($products as $product) {
                fwrite($handle, json_encode([
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand' => $product->brand->name ?? null,
                    'price' => $product->price !== null ? (float) $product->price : null,
                    'currency' => $product->currency,
                    'is_archived' => (bool) $product->is_archived,
                    'is_active' => (bool) $product->is_active,
                ], JSON_UNESCAPED_UNICODE) . "\n");
                $count++;
            }
        });

        fclose($handle);

        $this->info(sprintf('Wrote %d products to %s', $count, self::OUTPUT_PATH));

        return self::SUCCESS;
    }
}

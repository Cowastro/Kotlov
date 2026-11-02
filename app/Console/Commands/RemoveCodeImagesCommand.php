<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;

/**
 * Photos found on the internet that are a QR code or a barcode of a shop page, not the item: they are taken off
 * the cards. A card left without a photo is hidden again until one is found, and its price list offers go back
 * to the photo search queue (which now skips such pictures).
 *
 *   php artisan catalog:remove-code-images                 # dry run: the cards
 *   php artisan catalog:remove-code-images --apply
 */
class RemoveCodeImagesCommand extends Command
{
    protected $signature = 'catalog:remove-code-images
        {--since=2026-10-01 : Only pictures saved since then}
        {--apply : Take the pictures off}';

    protected $description = 'Take QR codes and barcodes saved as photos off the cards';

    public function handle(): int
    {
        $since = strtotime((string) $this->option('since')) ?: 0;
        $stats = ['checked' => 0, 'code_images' => 0, 'cards' => 0, 'hidden' => 0];
        Product::query()->where('images', 'like', '%source-enrichment%')->orderBy('id')
            ->chunkById(500, function ($products) use ($since, &$stats): void {
                foreach ($products as $product) {
                    $images = (array) $product->images;
                    $codes = array_values(array_filter($images, function ($path) use ($since, &$stats): bool {
                        $file = public_path(ltrim((string) $path, '/'));
                        if (! is_string($path) || ! str_contains($path, 'source-enrichment/') || ! is_file($file) || filemtime($file) < $since) {
                            return false;
                        }
                        $stats['checked']++;

                        return ProductSourceEnricher::looksLikeCodeImage((string) file_get_contents($file));
                    }));
                    if ($codes === []) {
                        continue;
                    }
                    $left = array_values(array_diff($images, $codes));
                    $stats['code_images'] += count($codes);
                    $stats['cards']++;
                    $stats['hidden'] += (int) ($left === [] && $product->is_active);
                    $this->line(sprintf('audit.code_images.card=%d:%s:%d', $product->id, $product->slug, count($left)));
                    if (! $this->option('apply')) {
                        continue;
                    }
                    $product->forceFill($left === []
                        ? ['images' => [], 'is_active' => false, 'created_via' => Product::CREATED_VIA_AWAITING_PHOTOS]
                        : ['images' => $left])->save();
                    if ($left === []) {
                        SupplierProduct::query()->where('product_id', $product->id)->get()->each(fn (SupplierProduct $offer) => $offer
                            ->forceFill(['raw' => array_merge((array) $offer->raw, ['photo_searched_at' => null])])->save());
                    }
                }
            });
        foreach ($stats as $key => $value) {
            $this->line("metric.code_images.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}

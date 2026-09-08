<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Manually attach one or more researched photo URLs to a single product's
 * images array — for the tail of broken-image cases with no automated
 * supplier-sync source (a discontinued SKU, a spare part / accessory with
 * no dedicated scraper), where a human (or a web search) has found the
 * right photo by hand. Same spirit as the one-off ComfortProm мангалы /
 * Pegas-Asgard photo fixes earlier, generalized into a reusable command
 * instead of a bespoke script each time.
 *
 * Downloads each URL, verifies it's actually an image (content-type +
 * minimum byte size, to catch an error page saved with a 200 status),
 * saves it under public/img/products/manual-fix/, and either replaces or
 * appends to the product's images array.
 *
 *   php artisan catalog:attach-image 12345 --url=https://example.com/photo1.jpg --url=https://example.com/photo2.jpg --apply
 */
class CatalogAttachImageCommand extends Command
{
    protected $signature = 'catalog:attach-image
        {product : Product ID, or SKU (e.g. KOTLOV-000105) when --sku is passed}
        {--sku : Treat the product argument as a SKU instead of a numeric ID}
        {--url=* : One or more image URLs to download and attach, in order}
        {--replace : Replace the existing images array instead of appending}
        {--apply : Write changes to the database (default: dry-run preview)}';

    protected $description = 'Manually attach researched photo URL(s) to one product (for broken images with no automated source)';

    private const IMAGE_DIR = 'img/products/manual-fix';

    public function handle(): int
    {
        $productArg = (string) $this->argument('product');
        $urls       = (array) $this->option('url');
        $apply      = (bool) $this->option('apply');
        $replace    = (bool) $this->option('replace');

        if ($urls === []) {
            $this->error('At least one --url= is required.');
            return self::FAILURE;
        }

        $product = $this->option('sku')
            ? DB::table('products')->where('sku', $productArg)->first()
            : DB::table('products')->where('id', (int) $productArg)->first();

        if (! $product) {
            $this->error("Product {$productArg} not found.");
            return self::FAILURE;
        }

        $productId = (int) $product->id;

        $this->info("Product #{$productId}: {$product->name} (sku {$product->sku})");
        $this->line($apply ? '<fg=red;options=bold>APPLY</>' : '<fg=yellow;options=bold>DRY RUN</>');

        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $existing = json_decode((string) $product->images, true);
        if (! is_array($existing)) {
            $existing = [];
        }

        $newPaths = [];
        foreach ($urls as $i => $url) {
            try {
                $resp = Http::timeout(20)
                    ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'])
                    ->get($url);
            } catch (\Throwable $e) {
                $this->warn("  ✗ fetch failed: {$url} ({$e->getMessage()})");
                continue;
            }

            if (! $resp->successful()) {
                $this->warn("  ✗ HTTP {$resp->status()}: {$url}");
                continue;
            }

            $body = $resp->body();
            $contentType = (string) $resp->header('Content-Type');
            // 500 bytes (not 2000): some legitimate sources (e.g. ru-buderus.com's
            // 160x160 catalog thumbnails) are genuinely ~1.5KB webp files — a real,
            // correctly-typed image, just small. The content-type check is what
            // actually guards against an HTML error page mislabeled as an image;
            // size alone only needs to rule out a near-empty/truncated response.
            if (strlen($body) < 500 || ! str_starts_with($contentType, 'image/')) {
                $this->warn("  ✗ not a real image (content-type={$contentType}, bytes=" . strlen($body) . "): {$url}");
                continue;
            }

            $ext = match (true) {
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'png') => 'png',
                default => 'jpg',
            };
            $filename = 'p' . $productId . '-manual-' . ($i + 1) . '-' . substr(md5($url), 0, 8) . '.' . $ext;
            $target = $dir . DIRECTORY_SEPARATOR . $filename;

            if ($apply) {
                file_put_contents($target, $body);
            }

            $newPaths[] = self::IMAGE_DIR . '/' . $filename;
            $this->info('  ✓ ' . strlen($body) . " bytes, {$contentType} -> {$filename}");
        }

        if ($newPaths === []) {
            $this->error('No valid images downloaded, nothing to attach.');
            return self::FAILURE;
        }

        $final = $replace ? $newPaths : array_values(array_unique(array_merge($existing, $newPaths)));

        $this->newLine();
        $this->info(($replace ? 'Would replace' : 'Would append, final') . ' images array (' . count($final) . '):');
        foreach ($final as $p) {
            $this->line('  ' . $p);
        }

        if ($apply) {
            DB::table('products')->where('id', $productId)->update([
                'images' => json_encode($final, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
            $this->info("Updated product #{$productId}.");
        } else {
            $this->line('(dry run — pass --apply to write)');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SetProductImagesFromUrlsCommand extends Command
{
    protected $signature = 'products:set-images-from-urls
        {--item=* : Product ref,image URL,public-relative path}
        {--apply : Write files and update product images}';

    protected $description = 'Download verified image URLs and set them as product images in bulk.';

    public function handle(): int
    {
        $items = array_values(array_filter((array) $this->option('item')));
        $apply = (bool) $this->option('apply');

        if (empty($items)) {
            $this->error('At least one --item is required.');

            return self::FAILURE;
        }

        if (! $apply) {
            $this->warn('DRY RUN - no file or database changes will be written.');
        }

        $errors = 0;
        $updated = 0;

        foreach ($items as $raw) {
            [$ref, $imageUrl, $path] = array_pad(explode(',', (string) $raw, 3), 3, '');
            $ref = trim($ref);
            $imageUrl = trim($imageUrl);
            $path = ltrim(str_replace('\\', '/', trim($path)), '/');

            if ($ref === '' || $imageUrl === '' || $path === '' || ! str_starts_with($path, 'img/products/')) {
                $this->error("Invalid item: {$raw}");
                $errors++;
                continue;
            }

            $product = $this->resolveProduct($ref);
            if (! $product) {
                $this->error("Product {$ref} not found by id, sku or slug.");
                $errors++;
                continue;
            }

            $this->line(sprintf('#%d %s -> %s', $product->id, $product->name, $path));

            if (! $apply) {
                continue;
            }

            $body = $this->downloadImage($imageUrl);
            if ($body === null) {
                $this->error("Image download failed for {$ref}: {$imageUrl}");
                $errors++;
                continue;
            }

            $absolute = public_path($path);
            File::ensureDirectoryExists(dirname($absolute));
            File::put($absolute, $body);

            $product->images = [$path];
            $product->save();

            $updated++;
        }

        $this->newLine();
        $this->table(['metric', 'count'], [
            ['items', count($items)],
            ['updated', $updated],
            ['errors', $errors],
        ]);

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveProduct(string $ref): ?Product
    {
        if (ctype_digit($ref)) {
            $product = Product::query()->find((int) $ref);
            if ($product) {
                return $product;
            }
        }

        return Product::query()
            ->where('sku', $ref)
            ->orWhere('slug', $ref)
            ->first();
    }

    private function downloadImage(string $url): ?string
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/',
            ])
            ->get($url);

        if (! $response->ok()) {
            return null;
        }

        $body = $response->body();

        return @getimagesizefromstring($body) === false ? null : $body;
    }
}

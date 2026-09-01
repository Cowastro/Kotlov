<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SetProductImageFromUrlCommand extends Command
{
    protected $signature = 'products:set-image-from-url
        {--product= : Product ID, SKU or slug}
        {--image-url= : Source image URL to download}
        {--image-page-url= : Product page URL; the command will use its og:image}
        {--path= : Public-relative target path, e.g. img/products/restored/file.png}
        {--apply : Write file and update product images}';

    protected $description = 'Download one verified image URL and set it as the only image for one product.';

    public function handle(): int
    {
        $productRef = trim((string) $this->option('product'));
        $imageUrl = trim((string) $this->option('image-url'));
        $imagePageUrl = trim((string) $this->option('image-page-url'));
        $path = ltrim(str_replace('\\', '/', trim((string) $this->option('path'))), '/');
        $apply = (bool) $this->option('apply');

        if ($productRef === '' || ($imageUrl === '' && $imagePageUrl === '') || $path === '') {
            $this->error('--product, --path and either --image-url or --image-page-url are required.');

            return self::FAILURE;
        }

        if (! str_starts_with($path, 'img/products/')) {
            $this->error('--path must be inside img/products/.');

            return self::FAILURE;
        }

        $product = $this->resolveProduct($productRef);
        if (! $product) {
            $this->error("Product {$productRef} not found by id, sku or slug.");

            return self::FAILURE;
        }

        if ($imageUrl === '') {
            $imageUrl = $this->extractImageUrlFromPage($imagePageUrl);
            if ($imageUrl === null) {
                $this->error("No usable og:image found on {$imagePageUrl}.");

                return self::FAILURE;
            }
        }

        $this->line(sprintf('#%d %s', $product->id, $product->name));
        $this->line("source: {$imageUrl}");
        $this->line("target: {$path}");

        if (! $apply) {
            $this->warn('DRY RUN - no file or database changes will be written.');

            return self::SUCCESS;
        }

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Referer' => parse_url($imageUrl, PHP_URL_SCHEME) . '://' . parse_url($imageUrl, PHP_URL_HOST) . '/',
            ])
            ->get($imageUrl);

        if (! $response->ok()) {
            $this->error('Image download failed: HTTP ' . $response->status());

            return self::FAILURE;
        }

        $body = $response->body();
        $info = @getimagesizefromstring($body);
        if ($info === false) {
            $this->error('Downloaded response is not an image.');

            return self::FAILURE;
        }

        $absolute = public_path($path);
        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $body);

        $product->images = [$path];
        $product->save();

        $this->info(sprintf('Saved %dx%d image and updated product.', $info[0] ?? 0, $info[1] ?? 0));

        return self::SUCCESS;
    }

    private function extractImageUrlFromPage(string $url): ?string
    {
        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])
            ->get($url);

        if (! $response->ok()) {
            return null;
        }

        $html = $response->body();

        if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/iu', $html, $match)
            || preg_match('/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/iu', $html, $match)) {
            return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
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
}

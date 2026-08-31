<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SetProductImageFromUrlCommand extends Command
{
    protected $signature = 'products:set-image-from-url
        {--product= : Product ID}
        {--image-url= : Source image URL to download}
        {--path= : Public-relative target path, e.g. img/products/restored/file.png}
        {--apply : Write file and update product images}';

    protected $description = 'Download one verified image URL and set it as the only image for one product.';

    public function handle(): int
    {
        $productId = (int) $this->option('product');
        $imageUrl = trim((string) $this->option('image-url'));
        $path = ltrim(str_replace('\\', '/', trim((string) $this->option('path'))), '/');
        $apply = (bool) $this->option('apply');

        if ($productId <= 0 || $imageUrl === '' || $path === '') {
            $this->error('--product, --image-url and --path are required.');

            return self::FAILURE;
        }

        if (! str_starts_with($path, 'img/products/')) {
            $this->error('--path must be inside img/products/.');

            return self::FAILURE;
        }

        $product = Product::query()->find($productId);
        if (! $product) {
            $this->error("Product #{$productId} not found.");

            return self::FAILURE;
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

        $product->images = json_encode([$path], JSON_UNESCAPED_UNICODE);
        $product->save();

        $this->info(sprintf('Saved %dx%d image and updated product.', $info[0] ?? 0, $info[1] ?? 0));

        return self::SUCCESS;
    }
}

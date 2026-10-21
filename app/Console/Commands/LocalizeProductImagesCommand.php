<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LocalizeProductImagesCommand extends Command
{
    protected $signature = 'catalog:localize-product-images
        {--brand= : Exact brand name}
        {--products= : Comma-separated product IDs}
        {--apply : Download files and replace remote image URLs}';

    protected $description = 'Download remote product gallery images to KOTLOV.BY and preserve existing local images.';

    public function handle(): int
    {
        $brand = trim((string) $this->option('brand'));
        $productIds = $this->productIds((string) $this->option('products'));
        $apply = (bool) $this->option('apply');

        if ($brand === '' && $productIds === []) {
            $this->error('--brand or --products is required.');

            return self::FAILURE;
        }

        $products = Product::query()
            ->active()
            ->notArchived()
            ->when($brand !== '', fn ($query) => $query->whereHas(
                'brand',
                fn ($brandQuery) => $brandQuery->whereRaw('LOWER(name) = ?', [mb_strtolower($brand)])
            ))
            ->when($productIds !== [], fn ($query) => $query->whereIn('id', $productIds))
            ->with('brand:id,name')
            ->get(['id', 'brand_id', 'name', 'slug', 'images']);

        $stats = [
            'cards_scanned' => $products->count(),
            'cards_with_remote' => 0,
            'remote_images' => 0,
            'cards_updated' => 0,
            'images_saved' => 0,
            'errors' => 0,
        ];

        foreach ($products as $product) {
            $images = is_array($product->images) ? $product->images : [];
            $remoteCount = collect($images)->filter(fn ($image): bool => $this->isRemote((string) $image))->count();
            if ($remoteCount === 0) {
                continue;
            }

            $stats['cards_with_remote']++;
            $stats['remote_images'] += $remoteCount;
            $this->line(sprintf('#%d %s: %d remote image(s)', $product->id, $product->slug, $remoteCount));

            if (! $apply) {
                continue;
            }

            $localized = $images;
            $cardFailed = false;
            foreach ($images as $index => $image) {
                $url = trim((string) $image);
                if (! $this->isRemote($url)) {
                    continue;
                }

                $download = $this->download($url);
                if ($download === null) {
                    $stats['errors']++;
                    $cardFailed = true;
                    $this->warn("  download failed: {$url}");
                    continue;
                }

                [$body, $extension] = $download;
                $brandSlug = Str::slug((string) ($product->brand?->name ?: 'brand')) ?: 'brand';
                $productSlug = Str::slug((string) ($product->slug ?: $product->name)) ?: 'product-'.$product->id;
                $relative = sprintf(
                    'img/products/localized/%s/%s-%02d-%s.%s',
                    $brandSlug,
                    $productSlug,
                    $index + 1,
                    substr(hash('sha256', $url), 0, 10),
                    $extension
                );
                $absolute = public_path($relative);
                File::ensureDirectoryExists(dirname($absolute));
                File::put($absolute, $body);
                $localized[$index] = $relative;
                $stats['images_saved']++;
            }

            if ($cardFailed) {
                continue;
            }

            $product->images = array_values($localized);
            $product->save();
            $stats['cards_updated']++;
        }

        foreach ($stats as $metric => $value) {
            $this->line("metric.localize_images.{$metric}={$value}");
        }

        return $stats['errors'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{0:string,1:string}|null */
    private function download(string $url): ?array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; KOTLOV.BY image localizer)',
                    'Accept' => 'image/avif,image/webp,image/png,image/jpeg,image/gif,*/*;q=0.5',
                    'Referer' => parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok() || strlen($response->body()) > 15 * 1024 * 1024) {
            return null;
        }

        $body = $response->body();
        $info = @getimagesizefromstring($body);
        if ($info === false) {
            return null;
        }

        $extension = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/avif' => 'avif',
            default => null,
        };

        return $extension ? [$body, $extension] : null;
    }

    private function isRemote(string $image): bool
    {
        return str_starts_with($image, 'http://') || str_starts_with($image, 'https://');
    }

    /** @return array<int,int> */
    private function productIds(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn ($id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()->values()->all();
    }
}

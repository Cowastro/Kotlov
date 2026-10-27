<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductVariantSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cards whose photo shows «Фото скоро появится»: the path leads to an old-site file that is not
 * on the server — mostly a bare file name («konus.jpeg») copied from another card, which the site
 * looks up under the new card's id. The photo is relinked to the card that has the file, or else
 * the nearest card of the same element (the variant selector: next diameter, volume…) lends its photos.
 *
 *   php artisan catalog:fix-stub-photos            # list
 *   php artisan catalog:fix-stub-photos --apply
 */
class FixStubPhotosCommand extends Command
{
    protected $signature = 'catalog:fix-stub-photos
        {--ids=* : Only these card ids}
        {--apply : Write the photos}';

    protected $description = 'Replace photos that show the stub with the real file or the photos of a similar card';

    public function handle(ProductVariantSelector $selector): int
    {
        $stats = ['cards' => 0, 'stub' => 0, 'relinked' => 0, 'sibling' => 0, 'unresolved' => 0];
        $query = Product::query()->where('is_archived', false)->whereRaw('JSON_LENGTH(images) > 0')
            ->when($this->option('ids'), fn ($q) => $q->whereIn('id', array_map('intval', (array) $this->option('ids'))));

        $query->chunkById(500, function ($products) use (&$stats, $selector): void {
            foreach ($products as $product) {
                $stats['cards']++;
                $paths = $product->imagePaths();
                // The stub shows when the first photo is missing.
                if ($paths === [] || $this->works($product, 0)) {
                    continue;
                }
                $stats['stub']++;

                $relinked = [];
                foreach ($paths as $index => $path) {
                    $relinked[] = $this->works($product, $index) ? $this->portableAt($product, $index) : $this->fileElsewhere($product, $path);
                }
                $relinked = array_values(array_unique(array_filter($relinked)));
                [$images, $method] = $relinked !== [] ? [$relinked, 'relinked'] : [$this->siblingPhotos($product, $selector), 'sibling'];

                if ($images === []) {
                    $stats['unresolved']++;
                    $this->line(sprintf('audit.stub_photos.card=%d:%s:none', $product->id, Str::limit((string) $product->slug, 70, '')));

                    continue;
                }
                $stats[$method]++;
                $this->line(sprintf('audit.stub_photos.card=%d:%s:%s', $product->id, Str::limit((string) $product->slug, 70, ''), $method));
                if ($this->option('apply')) {
                    $product->forceFill(['images' => $images])->save();
                }
            }
        });

        foreach ($stats as $key => $value) {
            $this->line("metric.stub_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Photos not on the old-site proxy are taken as they are; proxy ones must have their file. */
    private function works(Product $product, int $index): bool
    {
        $url = $product->imageUrl($index);

        return ! str_starts_with($url, '/proxy-image/') || is_file(public_path('images/'.substr($url, strlen('/proxy-image/'))));
    }

    /** The path of one photo that leads to the same file from any card. */
    private function portableAt(Product $product, int $index): ?string
    {
        $path = $product->imagePaths()[$index] ?? '';
        if (preg_match('#^(https?://|product/|products/|/?img/)#', $path)) {
            return $path;
        }
        $url = $product->imageUrl($index);

        return str_starts_with($url, '/proxy-image/') ? substr($url, strlen('/proxy-image/')) : null;
    }

    /** @return list<string> */
    private function workingPhotos(Product $product): array
    {
        $photos = [];
        foreach (array_keys($product->imagePaths()) as $index) {
            if ($this->works($product, $index)) {
                $photos[] = $this->portableAt($product, $index);
            }
        }

        return array_values(array_filter($photos));
    }

    /** The same file name on another card, where it does resolve to a file. */
    private function fileElsewhere(Product $product, string $path): ?string
    {
        if (str_contains($path, '/') || str_starts_with($path, 'http')) {
            return null;
        }
        $donors = Product::query()->where('id', '!=', $product->id)
            ->where('images', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], json_encode($path)).'%')
            ->limit(20)->get(['id', 'sku', 'images']);
        foreach ($donors as $donor) {
            $index = array_search($path, $donor->imagePaths(), true);
            if ($index !== false && $this->works($donor, (int) $index)) {
                return $this->portableAt($donor, (int) $index);
            }
        }

        return null;
    }

    /** @return list<string> photos of the nearest card of the same element that has working ones */
    private function siblingPhotos(Product $product, ProductVariantSelector $selector): array
    {
        $variants = ($selector->forAdmin($product)['variants'] ?? collect())->values();
        $position = (int) $variants->search(fn ($variant) => $variant['is_current']);
        $nearest = $variants->reject(fn ($variant) => $variant['is_current'])
            ->sortBy(fn ($variant, $key) => [abs($key - $position), $key]);
        foreach ($nearest as $variant) {
            $photos = $this->workingPhotos($variant['product']);
            if ($photos !== []) {
                return $photos;
            }
        }

        return [];
    }
}

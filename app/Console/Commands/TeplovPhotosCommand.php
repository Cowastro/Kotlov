<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\TeplovSiteCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Cards of a TiS system published from teplov.ru carry the maker's picture URL, and teplov.ru no
 * longer serves many of its pictures. The working photo of the part (or of its series) is
 * downloaded to the site instead — the same lookup as for the chimney cards created from 1C.
 *
 *   php artisan catalog:teplov-photos --article-prefix=TISF1-            # list
 *   php artisan catalog:teplov-photos --article-prefix=TISF1- --apply
 */
class TeplovPhotosCommand extends Command
{
    protected $signature = 'catalog:teplov-photos
        {--article-prefix=TISF1- : Supplier article prefix of the system (TISF1-, TIS30-, TIS50-)}
        {--apply : Download the photos}';

    protected $description = 'Download working teplov.ru photos for the cards of a TiS system';

    public function handle(TeplovSiteCatalog $teplov, ProductSourceEnricher $enricher): int
    {
        $links = SupplierProduct::query()->where('supplier_article', 'like', $this->option('article-prefix').'%')
            ->whereNotNull('product_id')->get(['product_id', 'supplier_name'])->unique('product_id');
        $stats = ['cards' => $links->count(), 'own_photo' => 0, 'found' => 0, 'saved' => 0, 'not_found' => 0];
        foreach ($links as $link) {
            $product = Product::query()->find($link->product_id);
            if (! $product) {
                continue;
            }
            // A photo already on the site stays; only maker links are replaced.
            if (collect($product->imagePaths())->contains(fn (string $path) => ! str_contains($path, 'teplov.ru/'))) {
                $stats['own_photo']++;

                continue;
            }
            $photos = $teplov->workingPhotos((string) $link->supplier_name);
            if ($photos === []) {
                $stats['not_found']++;
                $this->line(sprintf('audit.teplov_photos.none=%d:%s', $product->id, Str::limit((string) $product->slug, 70, '')));

                continue;
            }
            $stats['found']++;
            $this->line(sprintf('audit.teplov_photos.photo=%d:%s:%d', $product->id, Str::limit((string) $product->slug, 70, ''), count($photos)));
            if ($this->option('apply')) {
                $saved = (int) ($enricher->enrichFromParsed($product, $photos[0], ['images' => []], [
                    'verified_images' => $photos, 'update_images' => true, 'replace_images' => true, 'update_specs' => false,
                    'update_service' => false, 'update_documents' => false, 'update_video' => false, 'update_content' => false,
                ])['images_saved'] ?? 0);
                $stats['saved'] += (int) ($saved > 0);
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.teplov_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}

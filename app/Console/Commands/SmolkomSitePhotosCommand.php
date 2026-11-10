<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\SupplierProductAvailabilityService;
use App\Services\Suppliers\FireplaceBrandSiteCatalog;
use App\Services\Suppliers\SmolcomSiteCatalog;
use Illuminate\Console\Command;

/**
 * Photos from smolcom.by for the Смолком cards that have none: the offer of the same article («GOVV4Ч») carries the
 * photos of exactly that colour. Dimplex and Royal Flame fireplaces take the photos of dimplex.ru / royalflame.ru, whose
 * pages show the same article («64920045»). A card hidden for want of a photo is shown once it has one. Cards with photos are
 * never touched.
 *
 *   php artisan supplier:smolkom-site-photos            # dry run
 *   php artisan supplier:smolkom-site-photos --apply
 */
class SmolkomSitePhotosCommand extends Command
{
    protected $signature = 'supplier:smolkom-site-photos {--apply : Download the photos}';

    protected $description = 'Photos from smolcom.by, dimplex.ru and royalflame.ru (same article) for Смолком cards without a photo';

    public function handle(SmolcomSiteCatalog $site, FireplaceBrandSiteCatalog $brands, ProductSourceEnricher $enricher): int
    {
        $supplier = Supplier::query()->where('code', SyncSmolkomCommand::SUPPLIER_CODE)->first();
        if (! $supplier) {
            $this->line('metric.smolkom_site_photos.supplier=0');

            return self::FAILURE;
        }
        $offers = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')
            ->with('product')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => $offer->product && ! $offer->product->is_archived
                && array_filter((array) $offer->product->images) === [] && filled($offer->raw['article'] ?? null))
            ->values();
        $stats = ['without_photo' => $offers->count(), 'site_offers' => $site->size(), 'brand_site_offers' => $brands->size(), 'found' => 0, 'found_brand_site' => 0, 'saved' => 0, 'published' => 0, 'errors' => 0];
        $touched = [];
        foreach ($offers as $offer) {
            $photos = $site->photos((string) $offer->raw['article']);
            $source = 'https://smolcom.by/';
            if ($photos === [] && ($photos = $brands->photos((string) $offer->raw['article'])) !== []) {
                $source = (string) preg_replace('~^(https://[^/]+/).*$~', '$1', $photos[0]);
                $stats['found_brand_site']++;
            }
            $product = $offer->product;
            $this->line(sprintf('audit.smolkom_site_photos.card=%s:%s', $product->slug, $photos !== [] ? count($photos) : 'none'));
            if ($photos === []) {
                continue;
            }
            $stats['found']++;
            if (! $this->option('apply')) {
                continue;
            }
            try {
                $enricher->enrichFromParsed($product, $source, ['images' => [], 'specs' => [], 'description' => ''], [
                    'verified_images' => $photos, 'update_images' => true, 'update_specs' => false, 'update_content' => false,
                    'update_service' => false, 'update_documents' => false, 'update_video' => false,
                ]);
                $product->refresh();
                if (array_filter((array) $product->images) === []) {
                    continue;
                }
                $stats['saved']++;
                if ($product->created_via === Product::CREATED_VIA_AWAITING_PHOTOS) {
                    $product->forceFill(['is_active' => true, 'created_via' => null])->save();
                    $stats['published']++;
                    $touched[] = (int) $product->id;
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        if ($touched !== []) {
            app(SupplierProductAvailabilityService::class)->refreshMany($touched);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.smolkom_site_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }
}

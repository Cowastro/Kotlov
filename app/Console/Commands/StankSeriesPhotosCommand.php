<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Suppliers\StankPriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Photos for the hidden S-TANK cards the photo search finds nothing for (AT ETRO, SS ETRO, CT, HFWT DUO…): a tank of
 * a series looks the same at every volume, so the card takes the series render of s-tank.ru (saved in
 * public/img/products/stank/series, the connection scheme second) or, for a series not there, the photos of our
 * own card of that series. A card that gets photos is published.
 *
 *   php artisan supplier:stank-series-photos           # plan
 *   php artisan supplier:stank-series-photos --apply   # set the photos and publish
 */
class StankSeriesPhotosCommand extends Command
{
    protected $signature = 'supplier:stank-series-photos {--apply : Set the photos and publish the cards}';

    protected $description = 'Give the hidden S-TANK cards the photos of their series';

    public const DIR = 'img/products/stank/series';

    public function handle(): int
    {
        $supplier = Supplier::query()->where('code', 'stank')->first();
        $brandId = (int) DB::table('brands')->where('name', 'S-TANK')->value('id');
        if (! $supplier || ! $brandId) {
            $this->line('metric.stank_series_photos.supplier='.(int) (bool) $supplier);

            return self::FAILURE;
        }
        $article = SupplierProduct::query()->where('supplier_id', $supplier->id)->whereNotNull('product_id')->pluck('supplier_article', 'product_id');
        $keyOf = fn (Product $card): ?string => isset($article[$card->id]) && StankPriceList::row((string) $article[$card->id])
            ? (string) $article[$card->id] : StankPriceList::key((string) $card->name);

        $cards = Product::query()->where('brand_id', $brandId)->where('is_archived', false)->orderBy('id')->get(['id', 'name', 'slug', 'images', 'is_active', 'created_via']);
        // Our own published cards of a series, the photos of the first one.
        $ownImages = [];
        foreach ($cards as $card) {
            if ($card->created_via !== Product::CREATED_VIA_AWAITING_PHOTOS && ($paths = $card->imagePaths()) !== [] && ($key = $keyOf($card)) !== null) {
                $ownImages[StankPriceList::series($key)] ??= array_slice($paths, 0, 4);
            }
        }
        $stats = ['hidden' => 0, 'site' => 0, 'own' => 0, 'none' => 0, 'published' => 0];
        foreach ($cards as $card) {
            if ($card->created_via !== Product::CREATED_VIA_AWAITING_PHOTOS || $card->imagePaths() !== []) {
                continue;
            }
            $stats['hidden']++;
            $key = $keyOf($card);
            $series = $key !== null ? StankPriceList::series($key) : '';
            $images = $key !== null ? self::siteImages($key) : [];
            $source = $images !== [] ? 'site' : 'none';
            if ($images === [] && $series !== '' && isset($ownImages[$series])) {
                [$images, $source] = [$ownImages[$series], 'own'];
            }
            $stats[$source]++;
            $this->line(sprintf('audit.stank_series_photos.card=%d:%s:%s', $card->id, strtolower($series) ?: 'none', $source));
            if ($images !== [] && $this->option('apply')) {
                $card->forceFill(['images' => $images, 'is_active' => true, 'created_via' => null])->save();
                $stats['published']++;
            }
        }
        foreach ($stats as $name => $value) {
            $this->line("metric.stank_series_photos.{$name}={$value}");
        }

        return self::SUCCESS;
    }

    /**
     * The s-tank.ru picture of a model by the longest prefix of its key there: «ATETRO-300» → the series render
     * «stank-atetro-1.webp» and its scheme «-2»; «ITEX-6-3X» → «stank-itex-6-1.webp»; «ANODE-MG-450» → «stank-anode-mg-1.webp».
     */
    public static function siteImages(string $key): array
    {
        $parts = explode('-', strtolower($key));
        for ($n = count($parts); $n >= 1; $n--) {
            $images = [];
            foreach ([1, 2] as $i) {
                $path = self::DIR.'/stank-'.implode('-', array_slice($parts, 0, $n)).'-'.$i.'.webp';
                if (is_file(public_path($path))) {
                    $images[] = $path;
                }
            }
            if ($images !== []) {
                return $images;
            }
        }

        return [];
    }
}

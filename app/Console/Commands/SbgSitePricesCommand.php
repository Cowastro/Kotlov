<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\Marketplaces\DealByClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * SanBusinessGroup sells the same goods on sbg.by (Deal.by), where every product carries the 1C
 * article («TS.FRT.TRB.0115.82670») and the retail price the company stands behind. Cards linked to
 * a 1C row get that price: by default the cards created from 1C, with --all-linked every linked card.
 *
 *   php artisan integration:sbg-site-prices            # compare
 *   php artisan integration:sbg-site-prices --apply
 */
class SbgSitePricesCommand extends Command
{
    protected $signature = 'integration:sbg-site-prices
        {--source=onec : Integration source code}
        {--all-linked : Every card linked to a 1C row, not only the ones created from 1C}
        {--show-site= : Only list the sbg.by products whose name has this word (slug, article, price)}
        {--photos : Replace photos borrowed from a neighbour card with the sbg.by photos of the same article}
        {--apply : Set the sbg.by price on the cards}';

    protected $description = 'Compare (and set) card prices with the sbg.by price of the same 1C article';

    private const PAGE_SIZE = 100;

    public function handle(DealByClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->line('metric.sbg_prices.configured=0');

            return self::FAILURE;
        }
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $remote = $this->remotePrices($client);
        $this->line('metric.sbg_prices.site_products='.$remote->count());
        if ($word = $this->option('show-site')) {
            foreach ($this->siteProducts as $site) {
                if (mb_stripos($site['name'], $word) !== false) {
                    $this->line(sprintf('audit.sbg_prices.site=%s:%s:%s', Str::slug(Str::limit($site['name'], 90, '')), Str::slug($site['sku']) ?: 'none', number_format($site['price'], 2, '.', '')));
                }
            }

            return self::SUCCESS;
        }

        $items = IntegrationProduct::query()->where('integration_source_id', $source?->id)->whereNotNull('product_id')
            ->whereNotNull('external_sku')->where('external_sku', '!=', '')
            ->when(! $this->option('all-linked'), fn ($q) => $q->where('match_method', 'manual_created'))
            ->get(['id', 'product_id', 'external_sku']);
        $products = Product::query()->whereIn('id', $items->pluck('product_id'))->get(['id', 'slug', 'price', 'requires_price_confirmation'])->keyBy('id');

        if ($this->option('photos')) {
            return $this->replaceBorrowedPhotos($items);
        }

        $stats = ['cards' => $items->count(), 'on_site' => 0, 'same' => 0, 'differs' => 0, 'updated' => 0, 'not_on_site' => 0];
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            $price = $remote->get(Str::upper(trim((string) $item->external_sku)));
            if (! $product) {
                continue;
            }
            if ($price === null) {
                $stats['not_on_site']++;

                continue;
            }
            $stats['on_site']++;
            if (abs((float) $product->price - $price) < 0.01 && ! $product->requires_price_confirmation) {
                $stats['same']++;

                continue;
            }
            $stats['differs']++;
            $this->line(sprintf('audit.sbg_prices.differs=%d:%s:%s:%s', $product->id, Str::limit($product->slug, 70, ''),
                number_format((float) $product->price, 2, '.', ''), number_format($price, 2, '.', '')));
            if ($this->option('apply')) {
                $product->forceFill(['price' => $price, 'requires_price_confirmation' => false])->save();
                $stats['updated']++;
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.sbg_prices.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /**
     * A card whose photo files do not carry its own SKU borrowed them from another card (the closest
     * element of the series): the sbg.by photos of its own article show the real part.
     */
    private function replaceBorrowedPhotos(Collection $items): int
    {
        $stats = ['cards' => $items->count(), 'borrowed' => 0, 'site_photos' => 0, 'replaced' => 0, 'images_saved' => 0];
        $products = Product::query()->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
        foreach ($items as $item) {
            $product = $products->get($item->product_id);
            if (! $product) {
                continue;
            }
            $own = Str::slug((string) $product->sku);
            $images = (array) $product->images;
            $borrowed = $images === [] || collect($images)->every(fn ($path) => ! str_contains(Str::lower((string) $path), $own));
            if (! $borrowed) {
                continue;
            }
            $stats['borrowed']++;
            $site = $this->siteImages[Str::upper(trim((string) $item->external_sku))] ?? [];
            if ($site === []) {
                continue;
            }
            $stats['site_photos']++;
            $this->line(sprintf('audit.sbg_prices.photo=%d:%s:%d', $product->id, Str::limit($product->slug, 70, ''), count($site)));
            if (! $this->option('apply')) {
                continue;
            }
            $saved = (int) (app(\App\Services\ProductSourceEnricher::class)->enrichFromParsed($product, $site[0], ['images' => []], [
                'verified_images' => $site, 'update_images' => true, 'replace_images' => true, 'update_specs' => false, 'update_service' => false,
                'update_documents' => false, 'update_video' => false, 'update_content' => false,
            ])['images_saved'] ?? 0);
            $stats['images_saved'] += $saved;
            $stats['replaced'] += (int) ($saved > 0);
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.sbg_prices.photos_{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** @var array<string,array<int,string>> 1C article => sbg.by photo URLs */
    private array $siteImages = [];

    /** @var array<int,array{name:string,sku:string,price:float}> */
    private array $siteProducts = [];

    /** @return Collection<string,float> 1C article => sbg.by price */
    private function remotePrices(DealByClient $client): Collection
    {
        $prices = collect();
        $lastId = null;
        for ($page = 1; $page <= 2000; $page++) {
            $items = collect($client->listProducts(self::PAGE_SIZE, $lastId)['products'] ?? [])->filter(fn ($item) => is_array($item))->values();
            foreach ($items as $item) {
                $sku = Str::upper(trim((string) ($item['sku'] ?? '')));
                $images = array_values(array_filter(array_merge([(string) ($item['main_image'] ?? '')],
                    array_map(fn ($image) => is_array($image) ? (string) ($image['url'] ?? '') : (string) $image, (array) ($item['images'] ?? [])))));
                if ($sku !== '' && $images !== []) {
                    // The API gives previews («…_w328_h250_name.jpg»); the same URL without the size is the full photo.
                    $images = array_map(fn (string $url) => (string) preg_replace('/_w\d+_h\d+_/', '_', $url), $images);
                    $this->siteImages[$sku] = array_values(array_unique($images));
                }
                if (is_numeric($item['price'] ?? null) && (float) $item['price'] > 0) {
                    $this->siteProducts[] = ['name' => (string) ($item['name'] ?? ''), 'sku' => $sku, 'price' => round((float) $item['price'], 2)];
                }
                if ($sku !== '' && is_numeric($item['price'] ?? null) && (float) $item['price'] > 0) {
                    $prices[$sku] = round((float) $item['price'], 2);
                }
            }
            if ($items->count() < self::PAGE_SIZE) {
                break;
            }
            $next = (int) data_get($items->last(), 'id');
            if ($next <= 0 || $next === $lastId) {
                break;
            }
            $lastId = $next;
        }

        return $prices;
    }
}

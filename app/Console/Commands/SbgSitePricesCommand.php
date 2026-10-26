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

        $items = IntegrationProduct::query()->where('integration_source_id', $source?->id)->whereNotNull('product_id')
            ->whereNotNull('external_sku')->where('external_sku', '!=', '')
            ->when(! $this->option('all-linked'), fn ($q) => $q->where('match_method', 'manual_created'))
            ->get(['id', 'product_id', 'external_sku']);
        $products = Product::query()->whereIn('id', $items->pluck('product_id'))->get(['id', 'slug', 'price', 'requires_price_confirmation'])->keyBy('id');

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

    /** @return Collection<string,float> 1C article => sbg.by price */
    private function remotePrices(DealByClient $client): Collection
    {
        $prices = collect();
        $lastId = null;
        for ($page = 1; $page <= 2000; $page++) {
            $items = collect($client->listProducts(self::PAGE_SIZE, $lastId)['products'] ?? [])->filter(fn ($item) => is_array($item))->values();
            foreach ($items as $item) {
                $sku = Str::upper(trim((string) ($item['sku'] ?? '')));
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

<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Services\Market\MarketWebResearchService;
use Illuminate\Console\Command;

/**
 * Part of the Чистый берег file gives no margin: РРЦ equals опт. For such cards the retail price is taken
 * from the market — the median of exact offers found by web research (the supplier's own shop and ours left
 * out) — and only when it is above опт. The price is kept on the offer (raw.market_price), so the morning
 * sync goes on using it. A card with no market offers, or a market below опт, stays at РРЦ.
 *
 *   php artisan supplier:chisty-bereg-market-prices             # dry run: how many cards wait
 *   php artisan supplier:chisty-bereg-market-prices --limit=10 --apply
 */
class ChistyBeregMarketPricesCommand extends Command
{
    protected $signature = 'supplier:chisty-bereg-market-prices
        {--limit=10 : Cards researched in one run}
        {--recheck-days=30 : Research a card again after this many days}
        {--reset-misses-since= : Put back to the queue the cards checked since then without a price (an AI outage)}
        {--apply : Research and write the market prices}';

    protected $description = 'Market retail prices for Чистый берег cards whose РРЦ equals опт';

    /** The supplier's own shop sells at РРЦ, our sites are not the market. */
    private const OWN_DOMAINS = ['bereg.by', 'kotlov.by', 'sbg.by'];

    public function handle(MarketWebResearchService $research): int
    {
        $supplier = Supplier::query()->where('code', SyncChistyBeregCommand::SUPPLIER_CODE)->first();
        $offers = $supplier ? $this->candidates($supplier) : collect();
        $stats = ['no_margin' => $offers->count(), 'researched' => 0, 'priced' => 0, 'below_cost' => 0, 'not_found' => 0, 'errors' => 0];
        $due = $offers->filter(fn (SupplierProduct $offer): bool => ! filled($offer->raw['market_checked_at'] ?? null)
            || now()->subDays(max(1, (int) $this->option('recheck-days')))->gt($offer->raw['market_checked_at']));
        if ($since = $this->option('reset-misses-since')) {
            $stats['reset'] = 0;
            foreach ($offers as $offer) {
                $checked = $offer->raw['market_checked_at'] ?? null;
                if (filled($checked) && ($offer->raw['market_price'] ?? null) === null && $checked >= $since) {
                    $stats['reset']++;
                    if ($this->option('apply')) {
                        $offer->forceFill(['raw' => array_merge((array) $offer->raw, ['market_checked_at' => null])])->save();
                    }
                }
            }
        }
        $stats['due'] = $due->count();

        if ($this->option('apply')) {
            foreach ($due->take(max(1, (int) $this->option('limit'))) as $offer) {
                // The daily limit of background AI searches is spent: the rest waits for tomorrow.
                if (! MarketWebResearchService::takeBackgroundSearch()) {
                    $stats['daily_limit'] = 1;
                    break;
                }
                try {
                    $this->research($offer, $research, $stats);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->warn($offer->supplier_article.': '.$e->getMessage());
                }
            }
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.chisty_bereg_market.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Linked offers without margin on cards priced by Чистый берег alone. */
    private function candidates(Supplier $supplier)
    {
        return SupplierProduct::query()
            ->where('supplier_id', $supplier->id)
            ->whereNotNull('product_id')
            ->where('price_byn', '>', 0)
            ->with('product')
            ->orderByDesc('in_stock')
            ->orderBy('id')
            ->get()
            ->filter(function (SupplierProduct $offer) use ($supplier): bool {
                $rrc = (float) ($offer->raw['retail'] ?? 0);

                return $offer->product
                    && $rrc > 0 && $rrc <= (float) $offer->price_byn + 0.005
                    && ! $offer->product->operationalSupplierProducts()->contains(fn (SupplierProduct $item): bool => (int) $item->supplier_id !== (int) $supplier->id);
            })
            ->values();
    }

    /** @param array<string, int> $stats */
    private function research(SupplierProduct $offer, MarketWebResearchService $research, array &$stats): void
    {
        $product = $offer->product;
        $wholesale = (float) $offer->price_byn;
        $report = $research->research($product, $wholesale);
        // No AI balance or a network error: the card stays in the queue for the next run.
        if (($report['status'] ?? null) !== 'ready') {
            $stats['errors']++;
            $this->warn($offer->supplier_article.': '.($report['message'] ?? 'research failed'));

            return;
        }
        $stats['researched']++;
        $market = self::marketPrice((array) ($report['offers'] ?? []));

        $raw = (array) $offer->raw;
        $raw['market_checked_at'] = now()->toDateTimeString();
        $raw['market_price'] = $market !== null && $market > $wholesale + 0.005 ? $market : null;
        $offer->forceFill(['raw' => $raw])->save();

        if ($market === null) {
            $stats['not_found']++;
            $this->line(sprintf('audit.chisty_bereg_market.not_found=%d', $product->id));

            return;
        }
        if ($raw['market_price'] === null) {
            $stats['below_cost']++;
            $this->line(sprintf('audit.chisty_bereg_market.below_cost=%d:%.2f:%.2f', $product->id, $wholesale, $market));

            return;
        }

        $product->forceFill(['price' => $market, 'requires_price_confirmation' => false])->save();
        $stats['priced']++;
        $this->line(sprintf('audit.chisty_bereg_market.priced=%d:%.2f:%.2f', $product->id, $wholesale, $market));
    }

    /** Median BYN price of exact offers outside the supplier's and our own shops. */
    public static function marketPrice(array $offers): ?float
    {
        $prices = collect($offers)
            ->filter(fn ($offer): bool => is_array($offer) && ($offer['match'] ?? null) === 'exact' && is_numeric($offer['price_byn'] ?? null) && (float) $offer['price_byn'] > 0)
            ->reject(function (array $offer): bool {
                $host = preg_replace('/^www\./', '', mb_strtolower((string) ($offer['domain'] ?? parse_url((string) ($offer['url'] ?? ''), PHP_URL_HOST))));

                return collect(self::OWN_DOMAINS)->contains(fn (string $own): bool => $host === $own || str_ends_with($host, '.'.$own));
            })
            ->map(fn (array $offer): float => (float) $offer['price_byn'])
            ->sort()
            ->values();
        if ($prices->isEmpty()) {
            return null;
        }
        $middle = intdiv($prices->count(), 2);

        return round($prices->count() % 2 ? $prices[$middle] : ($prices[$middle - 1] + $prices[$middle]) / 2, 2);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Support\Suppliers\EcokaminChannel;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * ЭкоКамин promotion until 31.10.2026 (Дельта fireboxes, Мадрид, Рио, Крит,
 * Эклипс, Родос, Прага, Мюнхен, Три стекла). Prices mirror the manufacturer:
 * ecokamin.ru shows the promotional and the regular rouble price of every
 * model, the card gets both, converted with the same rate as its current
 * price. Our retail prices were already synced from ecokamin.ru, so no own
 * discount is calculated — re-running always resets to the manufacturer's
 * numbers (this also repaired the first run that discounted twice).
 * catalog:expire-sale-campaigns restores the regular price after the end date.
 *
 *   php artisan catalog:apply-ecokamin-october-sale            # dry run
 *   php artisan catalog:apply-ecokamin-october-sale --apply
 */
class ApplyEcokaminOctoberSaleCommand extends Command
{
    protected $signature = 'catalog:apply-ecokamin-october-sale {--apply : Set the promotional prices}';

    protected $description = 'ЭкоКамин October 2026 promotion with the manufacturer\'s promotional and regular prices';

    public const CAMPAIGN = 'ecokamin-2026-10';

    public const ENDS_AT = '2026-10-31 23:59:59';

    /** ecokamin.ru catalogue sections of the promotion. */
    public const SECTIONS = [
        'kaminnye_topki/delta', 'kaminy/kaminy_madrid', 'kaminy/kaminy_rio', 'kaminy/kamin_krit', 'kaminy/eklips',
        'kaminy/rodos', 'kaminy/praga', 'kaminy/myunkhen', 'kaminy/kaminy_tri_stekla',
    ];

    private const MAX_PAGES = 8;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $supplierId = EcokaminChannel::supplierId();
        if (! $supplierId) {
            $this->error('Поставщик ЭкоКамин (sanbusinessgroup) не найден.');

            return self::FAILURE;
        }
        $source = $this->sourcePrices();
        if (count($source) < 20) {
            $this->error('ecokamin.ru вернул слишком мало товаров: '.count($source));

            return self::FAILURE;
        }

        // bitrix id of every linked card (source_wp_id, or the id at the end of source_url).
        $links = EcokaminChannel::links($supplierId)->whereNotNull('product_id')->get(['product_id', 'source_wp_id', 'source_url']);
        $bitrixByProduct = [];
        foreach ($links as $link) {
            $id = (string) ($link->source_wp_id ?: (preg_match('~/(\d+)/?$~', (string) $link->source_url, $m) ? $m[1] : ''));
            if ($id !== '' && isset($source[$id])) {
                $bitrixByProduct[(int) $link->product_id] = $id;
            }
        }
        $products = Product::query()
            ->where(fn ($q) => $q->whereIn('id', array_keys($bitrixByProduct))->orWhere('sale_campaign', self::CAMPAIGN))
            ->where('is_archived', false)
            ->orderBy('id')
            ->get();

        // Card prices are manufacturer roubles × the official rate of their last sync.
        $referenceRate = EcokaminChannel::rubRate();

        $endsAt = CarbonImmutable::parse(self::ENDS_AT, 'Europe/Minsk')->utc();
        $stats = ['source_models' => count($source), 'linked' => count($bitrixByProduct), 'promo' => 0, 'not_discounted_by_maker' => 0, 'not_linked_removed' => 0, 'rate_outdated' => 0, 'changed' => 0];
        $writes = [];
        foreach ($products as $product) {
            $bitrix = $bitrixByProduct[$product->id] ?? null;
            $base = $this->basePrice($product);
            if (! $bitrix) {
                // Left from the first run but not offered by the manufacturer page: back to the regular price.
                $stats['not_linked_removed']++;
                $writes[$product->id] = ['price' => $base, 'price_old' => null, 'is_sale' => false, 'sale_campaign' => null, 'sale_ends_at' => null];
                $this->line(sprintf('audit.ecokamin_sale.item=%d:%s:0:%d:%d:off', $product->id, $product->slug, (int) $base, (int) $base));

                continue;
            }
            ['current' => $current, 'old' => $old] = $source[$bitrix];
            $rate = $this->cardRate($product, $source[$bitrix], $referenceRate);
            if ($rate === null) {
                $stats['rate_outdated']++;
                $rate = $referenceRate;
            }
            $price = round($current * $rate, 2);
            if ($old === null || $old <= $current) {
                if ($product->sale_campaign === self::CAMPAIGN) {
                    $stats['not_discounted_by_maker']++;
                    $writes[$product->id] = ['price' => $price, 'price_old' => null, 'is_sale' => false, 'sale_campaign' => null, 'sale_ends_at' => null];
                    $this->line(sprintf('audit.ecokamin_sale.item=%d:%s:0:%d:%d:off', $product->id, $product->slug, (int) $price, (int) $price));
                }

                continue;
            }
            $priceOld = round($old * $rate, 2);
            $percent = (int) round((1 - $current / $old) * 100);
            $stats['promo']++;
            $writes[$product->id] = ['price' => $price, 'price_old' => $priceOld, 'is_sale' => true, 'sale_campaign' => self::CAMPAIGN, 'sale_ends_at' => $endsAt];
            $this->line(sprintf('audit.ecokamin_sale.item=%d:%s:%d:%d:%d:%s', $product->id, $product->slug, $percent, (int) round($priceOld), (int) round($price), $product->sale_campaign === self::CAMPAIGN ? 'active' : 'new'));
        }

        if ($apply) {
            DB::transaction(function () use ($writes, $products, &$stats): void {
                foreach ($products as $product) {
                    if (! isset($writes[$product->id])) {
                        continue;
                    }
                    $product->forceFill($writes[$product->id]);
                    if ($product->isDirty()) {
                        $product->save();
                        $stats['changed']++;
                    }
                }
            });
            Cache::forget('home:data:v6');
            \App\Support\Promotions\EcokaminPromotion::forget();
        }

        foreach ($stats as $key => $value) {
            $this->line("metric.ecokamin_sale.{$key}={$value}");
        }
        if (! $apply) {
            $this->warn('Предпросмотр: цены не изменены. Для записи добавьте --apply.');
        }

        return self::SUCCESS;
    }

    /**
     * BYN per rouble behind the card price: price / promotional rouble price,
     * or — for cards of the first run that took another 20 % off — price_old /
     * promotional price. The candidate closest to the official rate wins;
     * null when the card is too far from it (outdated price).
     *
     * @param  array{current: float, old: ?float}  $source
     */
    private function cardRate(Product $product, array $source, float $referenceRate): ?float
    {
        $candidates = [(float) $product->price / $source['current']];
        if ($product->sale_campaign === self::CAMPAIGN && (float) $product->price_old > 0) {
            $candidates[] = (float) $product->price_old / $source['current'];
        }
        usort($candidates, fn (float $a, float $b): int => abs($a - $referenceRate) <=> abs($b - $referenceRate));

        return abs($candidates[0] - $referenceRate) / $referenceRate <= 0.05 ? $candidates[0] : null;
    }

    /** Regular card price before the campaign (the first run kept it in price_old). */
    private function basePrice(Product $product): float
    {
        return $product->sale_campaign === self::CAMPAIGN && (float) $product->price_old > 0
            ? (float) $product->price_old
            : (float) $product->price;
    }

    /** @return array<string, array{current: float, old: ?float}> bitrix id => rouble prices */
    private function sourcePrices(): array
    {
        $prices = [];
        foreach (self::SECTIONS as $section) {
            $pageParam = 'PAGEN_1';
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                $url = 'https://ecokamin.ru/catalog/'.$section.'/'.($page > 1 ? '?'.$pageParam.'='.$page : '');
                $html = $this->fetch($url);
                // Bitrix numbers the pager per page template (PAGEN_1, PAGEN_7…): take it from the page.
                if ($page === 1 && preg_match('~\?(PAGEN_\d+)=2~', $html, $pager)) {
                    $pageParam = $pager[1];
                }
                $found = 0;
                foreach ($this->parseListing($html) as $id => $item) {
                    if (! isset($prices[$id])) {
                        $prices[$id] = $item;
                        $found++;
                    }
                }
                if ($found === 0) {
                    break;
                }
            }
        }

        return $prices;
    }

    /** @return array<string, array{current: float, old: ?float}> */
    public function parseListing(string $html): array
    {
        $items = [];
        preg_match_all('~<a href="/catalog/[^"]+/(\d+)/" class="dark_link">~u', $html, $anchors, PREG_OFFSET_CAPTURE);
        foreach ($anchors[1] as $i => [$id, $offset]) {
            $next = $anchors[0][$i + 1][1] ?? strlen($html);
            $chunk = substr($html, $offset, $next - $offset);
            if (! preg_match('~<div class="price"\s+data-currency="[A-Z]+"\s+data-value="([\d.]+)"~u', $chunk, $current)) {
                continue;
            }
            $old = preg_match('~<div class="price discount"\s+data-currency="[A-Z]+"\s+data-value="([\d.]+)"~u', $chunk, $o) ? (float) $o[1] : null;
            $items[$id] = ['current' => (float) $current[1], 'old' => $old];
        }

        return $items;
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(40)->retry(3, 1500, throw: false)->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])->get($url);

        return $response->successful() ? $response->body() : '';
    }
}

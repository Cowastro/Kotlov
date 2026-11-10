<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\SupplierProduct;
use App\Services\ProductSourceEnricher;
use App\Services\Suppliers\WebProductSources;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Photos for the Чистый берег cards hidden for want of one (bereg.by has no page or no picture):
 * the internet search of «Найти в интернете» by the maker code and by the title, and the first
 * shop page that names this very item — its maker code, or its brand and every size of the
 * title — gives its photos. Each card is tried once; a card with a photo is shown by
 * catalog:hide-cards-awaiting-photos --publish.
 *
 *   php artisan supplier:chisty-bereg-photos --limit=20            # dry run: the chosen pages
 *   php artisan supplier:chisty-bereg-photos --limit=20 --apply
 *   php artisan supplier:chisty-bereg-photos --supplier=kaminproff --apply   # the hidden Fireway cards
 */
class ChistyBeregPhotosCommand extends Command
{
    protected $signature = 'supplier:chisty-bereg-photos {--limit=20}
        {--supplier=chisty_bereg : chisty_bereg, kaminproff (Fireway), akvateploservis (Galmet, Apamet), teplotop (LEMAX), vistar (BAXI) or stank (S-TANK): the cards made from its price list}
        {--reset-misses-since= : Put back to the queue the cards searched since then that got no photo (an AI outage)}
        {--apply : Save the photos}';

    protected $description = 'Find photos on the internet for the hidden Чистый берег cards';

    /** Pages read per card at most. */
    private const PAGES = 4;

    public function handle(WebProductSources $sources, ProductSourceEnricher $enricher): int
    {
        $sources->background = true;
        // Cards made from a price list keep its prefix in the SKU.
        [$supplierCode, $skuPrefixes, $ownSite] = match ($this->option('supplier')) {
            SyncKaminproffCommand::SUPPLIER_CODE => [SyncKaminproffCommand::SUPPLIER_CODE, ['FIREWAY-'], 'kaminproff.by'],
            SyncAkvateploservisCommand::SUPPLIER_CODE => [SyncAkvateploservisCommand::SUPPLIER_CODE, ['GALMET-', 'APAMET-'], 'galmet.by'],
            SyncTeplotopCommand::SUPPLIER_CODE => [SyncTeplotopCommand::SUPPLIER_CODE, ['TEPLOTOP-'], 'ttop.by'],
            SyncVistarCommand::SUPPLIER_CODE => [SyncVistarCommand::SUPPLIER_CODE, ['VISTAR-'], 'baxi.ru'],
            SyncSmolkomCommand::SUPPLIER_CODE => [SyncSmolkomCommand::SUPPLIER_CODE, ['SMOLKOM-'], 'smolcom.ru'],
            'stank' => ['stank', ['STANK-'], 's-tank.ru'],
            'prestizh_teplo' => ['prestizh_teplo', ['PRESTIZH-'], 'prestizh-teplo.by'],
            default => [SyncChistyBeregCommand::SUPPLIER_CODE, ['BEREG-'], 'bereg.by'],
        };
        $offers = SupplierProduct::query()
            ->whereHas('supplier', fn ($q) => $q->where('code', $supplierCode))
            ->whereHas('product', fn ($q) => $q->where(fn ($sku) => collect($skuPrefixes)->each(fn (string $prefix) => $sku->orWhere('sku', 'like', $prefix.'%')))
                ->where('is_archived', false)
                ->where('created_via', Product::CREATED_VIA_AWAITING_PHOTOS))
            ->with('product.brand:id,name')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => array_filter((array) $offer->product->images) === [])
            ->values();
        $reset = 0;
        if ($since = $this->option('reset-misses-since')) {
            foreach ($offers as $offer) {
                $searched = $offer->raw['photo_searched_at'] ?? null;
                if (filled($searched) && $searched >= $since) {
                    $reset++;
                    if ($this->option('apply')) {
                        $offer->forceFill(['raw' => array_merge((array) $offer->raw, ['photo_searched_at' => null])])->save();
                    }
                }
            }
            $this->line("metric.chisty_bereg_photos.reset={$reset}");
            if ($this->option('apply')) {
                $offers->each->refresh();
            }
        }
        $offers = $offers->filter(fn (SupplierProduct $offer): bool => ! filled($offer->raw['photo_searched_at'] ?? null))->values();
        $stats = ['due' => $offers->count(), 'searched' => 0, 'found' => 0, 'saved' => 0, 'not_found' => 0, 'errors' => 0];
        if (! $sources->isConfigured()) {
            $this->line('metric.chisty_bereg_photos.search_configured=0');

            return self::FAILURE;
        }

        // --limit=0 only counts the queue and today's AI searches: no search is made.
        foreach ($offers->take(max(0, (int) $this->option('limit'))) as $offer) {
            $product = $offer->product;
            $raw = (array) $offer->raw;
            $brand = in_array(mb_strtolower(trim((string) ($raw['brand'] ?? ''))), ['', 'без бренда'], true) ? '' : trim((string) $raw['brand']);
            $code = trim((string) ($raw['maker_code'] ?? ''));
            $stats['searched']++;
            try {
                $urls = [];
                // One AI search a card: each costs a web survey.
                $queries = [$code !== '' ? trim($brand.' '.$code) : $product->name];
                $failed = 0;
                foreach ($queries as $query) {
                    $urls += $sources->search($query);
                    $failed += (int) ($sources->lastError !== '');
                    // Which search answered: the AI search (DeepSeek), or Google where no AI key is set.
                    $stats['engine_'.$sources->engine] = ($stats['engine_'.$sources->engine] ?? 0) + 1;
                }
                // Every search failed (no AI balance): not «no photo», the card is tried again next run.
                if ($failed === count($queries)) {
                    $stats['errors']++;
                    $this->line('audit.chisty_bereg_photos.error_kind='.self::errorKind($sources->lastError));
                    $this->warn($offer->supplier_article.': '.$sources->lastError);

                    continue;
                }
                $urls = array_values(array_filter(array_keys($urls), fn (string $url): bool => ! str_contains($url, $ownSite) && ! preg_match('/\.(pdf|docx?|xlsx?)(\?|$)/i', $url)
                    && ! self::watermarked($url)));
                $found = null;
                $started = microtime(true);
                foreach (array_slice($urls, 0, self::PAGES) as $url) {
                    if (microtime(true) - $started > 60) {
                        break;
                    }
                    try {
                        $page = $enricher->preview($url, 15, 1);
                    } catch (\Throwable) {
                        continue;
                    }
                    if ($page['images'] !== [] && self::samePage($product->name, $brand, $code, $url, $page)) {
                        $found = [$url, $page];
                        break;
                    }
                }
                if ($found === null) {
                    $stats['not_found']++;
                    $this->line("audit.chisty_bereg_photos.card={$product->id}:{$product->slug}:none");
                } else {
                    [$url, $page] = $found;
                    $stats['found']++;
                    $this->line(sprintf('audit.chisty_bereg_photos.card=%d:%s:%s', $product->id, $product->slug, Str::slug((string) parse_url($url, PHP_URL_HOST))));
                    if ($this->option('apply')) {
                        $images = array_slice($page['images'], 0, 3);
                        $enricher->enrichFromParsed($product, $url, ['images' => [], 'specs' => [], 'description' => ''], [
                            'verified_images' => $images, 'update_images' => true, 'update_specs' => false, 'update_content' => false,
                            'update_service' => false, 'update_documents' => false, 'update_video' => false,
                        ]);
                        $stats['saved'] += (int) (array_filter((array) $product->fresh()->images) !== []);
                    }
                }
                if ($this->option('apply')) {
                    $offer->forceFill(['raw' => array_merge($raw, ['photo_searched_at' => now()->toDateTimeString()])])->save();
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $this->warn($offer->supplier_article.': '.$e->getMessage());
            }
        }
        $stats['daily_used'] = \App\Services\Market\MarketWebResearchService::backgroundSearchesToday();
        $stats['daily_limit'] = (int) config('services.ai.market_research_daily_background_limit');
        foreach ($stats as $key => $value) {
            $this->line("metric.chisty_bereg_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** Shops that put their logo on product photos: their pictures are never taken (21vek.by, 10.11.2026). */
    public const WATERMARKED_HOSTS = ['21vek.by'];

    public static function watermarked(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return collect(self::WATERMARKED_HOSTS)->contains(fn (string $h) => $host === $h || str_ends_with($host, '.'.$h));
    }

    /** A safe word for the log in place of the provider's message. */
    public static function errorKind(string $error): string
    {
        $error = mb_strtolower($error);

        return match (true) {
            str_contains($error, 'daily limit') => 'daily_limit',
            (bool) preg_match('/balance|insufficient|quota|credit|402|billing|payment/u', $error) => 'balance',
            (bool) preg_match('/401|403|unauthori|invalid api key|api-ключ/u', $error) => 'auth',
            (bool) preg_match('/429|rate limit|too many/u', $error) => 'rate_limit',
            (bool) preg_match('/timed? ?out|timeout|curl error 28/u', $error) => 'timeout',
            str_contains($error, 'уже выполняется') => 'busy',
            str_contains($error, 'структурированн') => 'bad_report',
            (bool) preg_match('/5\d\d|server error|overloaded/u', $error) => 'server_error',
            default => 'other',
        };
    }

    /**
     * The page is of this item: its maker code is on it, or its title has every size of ours
     * («20», «3,4», «1/2») and the brand — or, for an item without a brand, its marking
     * («15кч19п»). A title with neither is too vague to trust.
     */
    public static function samePage(string $name, string $brand, string $code, string $url, array $page): bool
    {
        $norm = fn (string $text): string => preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text)) ?? '';
        $specs = collect((array) ($page['specs'] ?? []))->map(fn ($spec) => is_array($spec) ? ($spec['value'] ?? '') : '')->implode(' ');
        $title = (string) ($page['title'] ?? '');
        $code = $norm($code);
        if (mb_strlen($code) >= 4 && str_contains($norm($title.' '.urldecode($url).' '.$specs), $code)) {
            return true;
        }
        $brand = $norm($brand);
        $sizes = preg_match_all('/\d+(?:[.,\/]\d+)?/u', preg_replace('/\s+(PN|Ру|Ду|DN)\s*/iu', ' ', $name) ?? $name, $m) ? array_unique($m[0]) : [];
        preg_match_all('/[\p{L}\p{N}-]+/u', mb_strtolower($name), $words);
        $markings = array_filter(array_map($norm, $words[0]), fn (string $w): bool => mb_strlen($w) >= 4
            && preg_match('/\p{L}/u', $w) && preg_match('/\d/u', $w) && ! preg_match('/^(pn|dn|ру|ду)\d+$/u', $w));
        $named = $brand !== ''
            ? str_contains($norm($title.' '.urldecode($url)), $brand)
            : $markings !== [] && array_filter($markings, fn (string $w): bool => str_contains($norm($title.' '.urldecode($url)), $w)) !== [];
        if ($sizes === [] || ! $named) {
            return false;
        }
        $titleSizes = preg_match_all('/\d+(?:[.,\/]\d+)?/u', $title, $t) ? array_map(fn ($s) => str_replace(',', '.', $s), $t[0]) : [];

        return array_diff(array_map(fn ($s) => str_replace(',', '.', $s), $sizes), $titleSizes) === [];
    }
}

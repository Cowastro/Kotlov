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
 */
class ChistyBeregPhotosCommand extends Command
{
    protected $signature = 'supplier:chisty-bereg-photos {--limit=20} {--apply : Save the photos}';

    protected $description = 'Find photos on the internet for the hidden Чистый берег cards';

    /** Pages read per card at most. */
    private const PAGES = 4;

    public function handle(WebProductSources $sources, ProductSourceEnricher $enricher): int
    {
        $offers = SupplierProduct::query()
            ->whereHas('supplier', fn ($q) => $q->where('code', SyncChistyBeregCommand::SUPPLIER_CODE))
            ->whereHas('product', fn ($q) => $q->where('sku', 'like', 'BEREG-%')->where('is_archived', false)
                ->where('created_via', Product::CREATED_VIA_AWAITING_PHOTOS))
            ->with('product.brand:id,name')->orderBy('id')->get()
            ->filter(fn (SupplierProduct $offer): bool => ! filled($offer->raw['photo_searched_at'] ?? null)
                && array_filter((array) $offer->product->images) === [])
            ->values();
        $stats = ['due' => $offers->count(), 'searched' => 0, 'found' => 0, 'saved' => 0, 'not_found' => 0, 'errors' => 0];
        if (! $sources->isConfigured()) {
            $this->line('metric.chisty_bereg_photos.search_configured=0');

            return self::FAILURE;
        }

        foreach ($offers->take(max(1, (int) $this->option('limit'))) as $offer) {
            $product = $offer->product;
            $raw = (array) $offer->raw;
            $brand = in_array(mb_strtolower(trim((string) ($raw['brand'] ?? ''))), ['', 'без бренда'], true) ? '' : trim((string) $raw['brand']);
            $code = trim((string) ($raw['maker_code'] ?? ''));
            $stats['searched']++;
            try {
                $urls = [];
                foreach (array_unique(array_filter([$code !== '' ? trim($brand.' '.$code) : null, $product->name])) as $query) {
                    $urls += $sources->search($query);
                }
                $urls = array_values(array_filter(array_keys($urls), fn (string $url): bool => ! str_contains($url, 'bereg.by') && ! preg_match('/\.(pdf|docx?|xlsx?)(\?|$)/i', $url)));
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
        foreach ($stats as $key => $value) {
            $this->line("metric.chisty_bereg_photos.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /**
     * The page is of this item: its maker code is on it, or its title has the brand and
     * every size of ours («20», «3,4», «1/2»). A title with neither is too vague to trust.
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
        if ($brand === '' || $sizes === [] || ! str_contains($norm($title.' '.urldecode($url)), $brand)) {
            return false;
        }
        $titleSizes = preg_match_all('/\d+(?:[.,\/]\d+)?/u', $title, $t) ? array_map(fn ($s) => str_replace(',', '.', $s), $t[0]) : [];

        return array_diff(array_map(fn ($s) => str_replace(',', '.', $s), $sizes), $titleSizes) === [];
    }
}

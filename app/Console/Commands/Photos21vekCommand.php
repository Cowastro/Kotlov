<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use App\Services\ProductSourceEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Exact photos of Теплов и Сухов chimney parts from 21vek.by, which lists every part per diameter
 * («Поворот для труб Теплов и Сухов 87 ОМ-Р 430-0.5 D120 (3S)»). A card gets the photos of the
 * 21vek item with the same parameters (LinkOneCByParametersCommand::signature). By default the
 * cards created from 1C whose photo was borrowed from a card of the series.
 *
 *   php artisan catalog:photos-21vek                 # list
 *   php artisan catalog:photos-21vek --ids=30196 --apply
 */
class Photos21vekCommand extends Command
{
    protected $signature = 'catalog:photos-21vek
        {--ids=* : Only these cards (any Теплов и Сухов card, borrowed photo or not)}
        {--source=onec : Integration source of the created cards}
        {--apply : Replace the photos}';

    protected $description = 'Give Теплов и Сухов chimney cards the exact photos of the same part on 21vek.by';

    private const LISTING = 'https://www.21vek.by/chimneys/all/teplov_syhov/';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    public function handle(ProductSourceEnricher $enricher): int
    {
        $catalog = $this->catalog();
        $stats = ['site_items' => count($catalog), 'cards' => 0, 'matched' => 0, 'replaced' => 0, 'not_found' => 0, 'no_photos' => 0];
        foreach ($this->targets() as $product) {
            $stats['cards']++;
            $url = $catalog[self::key((string) $product->name)] ?? null;
            if ($url === null) {
                $stats['not_found']++;
                $this->line(sprintf('audit.photos_21vek.card=%d:%s:none:0', $product->id, Str::limit((string) $product->slug, 70, '')));

                continue;
            }
            $photos = $this->photos($url);
            if ($photos === []) {
                $stats['no_photos']++;
                $this->line(sprintf('audit.photos_21vek.card=%d:%s:empty:0', $product->id, Str::limit((string) $product->slug, 70, '')));

                continue;
            }
            $stats['matched']++;
            $this->line(sprintf('audit.photos_21vek.card=%d:%s:found:%d', $product->id, Str::limit((string) $product->slug, 70, ''), count($photos)));
            if ($this->option('apply')) {
                $saved = (int) ($enricher->enrichFromParsed($product, $url, ['images' => []], [
                    'verified_images' => $photos, 'update_images' => true, 'replace_images' => true, 'update_specs' => false,
                    'update_service' => false, 'update_documents' => false, 'update_video' => false, 'update_content' => false,
                ])['images_saved'] ?? 0);
                $stats['replaced'] += (int) ($saved > 0);
            }
        }
        foreach ($stats as $key => $value) {
            $this->line("metric.photos_21vek.{$key}={$value}");
        }

        return self::SUCCESS;
    }

    /** 21vek names in our words; «моно» is left out, as half of either side does not say it. */
    public static function key(string $name): string
    {
        $name = (string) preg_replace('#\s/\s*TS\.[A-Z0-9.]+#u', '', $name);
        $name = (string) preg_replace(['/\bповорот\b/iu', '/\bколпак\b/iu', '/\bпереходник\b/iu'], ['Отвод', 'Зонт', 'Переход'], $name);
        $signature = LinkOneCByParametersCommand::signature($name);

        return $signature === '' ? '' : implode('|', array_diff(explode('|', $signature), ['моно']));
    }

    /** @return iterable<Product> */
    private function targets(): iterable
    {
        if ($ids = array_map('intval', (array) $this->option('ids'))) {
            return Product::query()->whereIn('id', $ids)->get();
        }
        $source = IntegrationSource::query()->where('code', $this->option('source'))->first();
        $ids = IntegrationProduct::query()->where('integration_source_id', $source?->id)->where('match_method', 'manual_created')
            ->whereNotNull('product_id')->pluck('product_id');

        return Product::query()->whereIn('id', $ids)->where('is_archived', false)->get()
            ->filter(fn (Product $product) => preg_match('/теплов|(?<!\pL)тис(?!\pL)/iu', (string) $product->name))
            ->reject(function (Product $product): bool {
                $own = Str::lower(Str::slug((string) $product->sku));

                return collect($product->imagePaths())->contains(fn ($path) => str_contains(Str::lower($path), $own));
            });
    }

    /** @return array<string,string> parameter key => 21vek page */
    private function catalog(): array
    {
        $catalog = [];
        for ($page = 1; $page <= 15; $page++) {
            $html = $this->get(self::LISTING.($page > 1 ? "page:{$page}/" : ''));
            $before = count($catalog);
            foreach (preg_split('/itemType="https:\/\/schema\.org\/Product"/', $html) ?: [] as $index => $block) {
                if ($index === 0 || ! preg_match('#href="(/chimneys/[^"]+\.html)"#', $block, $url)
                    || ! preg_match('/itemProp="name"[^>]*?(?:content="([^"]+)"|>([^<]+)<)/', $block, $name)) {
                    continue;
                }
                $key = self::key(html_entity_decode($name[1] !== '' ? $name[1] : ($name[2] ?? ''), ENT_QUOTES | ENT_HTML5));
                if ($key !== '') {
                    $catalog[$key] ??= 'https://www.21vek.by'.$url[1];
                }
            }
            if (count($catalog) === $before) {
                break;
            }
        }

        return $catalog;
    }

    /** @return list<string> the item's own gallery (other items' pictures on the page are left out) */
    private function photos(string $url): array
    {
        $slug = basename($url, '.html');
        preg_match_all('#https://cdn21vek\.by/img/galleries/[^"\\\\\s,]+\.(?:jpe?g|png|webp)#i', $this->get($url), $m);

        return collect($m[0])->filter(fn (string $image) => str_starts_with(basename($image), $slug))->unique()->take(4)->values()->all();
    }

    private function get(string $url): string
    {
        try {
            $response = Http::timeout(30)->withUserAgent(self::USER_AGENT)->retry(2, 500, throw: false)->get($url);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}

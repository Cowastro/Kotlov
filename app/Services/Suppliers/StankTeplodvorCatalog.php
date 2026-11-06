<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The S-TANK goods of teplodvor.by for the cards made from the S-TANK price list: a page is found by the same
 * model (StankPriceList::key of its title, «BER2-750», «SSHP-1000»). The brand listings are read besides the
 * sitemap (pages «/page2/»).
 */
class StankTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 's-tank';

    private const LISTINGS = [
        'https://www.teplodvor.by/shop/vodonagrevateli/kosvennye/s-tank/',
    ];

    protected static function matchKey(string $name): string
    {
        return StankPriceList::key($name) ?? '';
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }

    /** A page of another volume of the same series: its photos show the same tank. */
    public function findSeries(string $key): ?string
    {
        $this->byModel ??= $this->index();
        $series = StankPriceList::series($key);
        foreach ($this->byModel as $model => $url) {
            if (StankPriceList::series($model) === $series) {
                return $url;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    protected function index(): array
    {
        $index = parent::index();
        $urls = Cache::remember('teplodvor-brand:listings:s-tank', now()->addWeek(), function (): array {
            $urls = [];
            foreach (self::LISTINGS as $listing) {
                for ($page = 1; $page <= 8; $page++) {
                    $response = Http::timeout(30)->retry(2, 1000, throw: false)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])
                        ->get($listing.($page > 1 ? 'page'.$page.'/' : ''));
                    $before = count($urls);
                    if ($response->successful()) {
                        preg_match_all('~href="((?:https://www\.teplodvor\.by)?/shop/[^"]*s-tank[^"]*/)"~iu', $response->body(), $m);
                        $found = array_map(fn (string $url): string => str_starts_with($url, 'http') ? $url : 'https://www.teplodvor.by'.$url, $m[1]);
                        $urls = array_values(array_unique(array_merge($urls, $found)));
                    }
                    if (count($urls) === $before) {
                        break;
                    }
                }
            }

            return $urls;
        });
        $found = array_flip($index);
        foreach ($urls as $url) {
            if (isset($found[$url]) || preg_match('~/s-tank/(?:page\d+/)?$~', $url)) {
                continue;
            }
            $page = $this->page($url);
            if ($page && ($key = static::matchKey($page['name'])) !== '' && ! isset($index[$key])) {
                $index[$key] = $url;
            }
        }

        return $index;
    }
}

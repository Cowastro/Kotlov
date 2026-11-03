<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The BAXI, Viessmann, Viena and Reflex goods of teplodvor.by for the cards made from the VISTAR price list: a page
 * is found by the same model (VistarPriceList::modelKey of its title). The sitemap misses the newer models
 * (LUNA Century, Classic, Compact), so the brand listings of the catalogue are read too.
 */
class BaxiTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'baxi|viessmann|viena|reflex';

    private const LISTINGS = [
        'https://www.teplodvor.by/shop/kotly/gazovye/baxi/',
        'https://www.teplodvor.by/shop/kotly/gazovye/kondensatsionnye-gazovye-kotly/baxi/',
        'https://www.teplodvor.by/shop/kotly/elektricheskie/baxi/',
        'https://www.teplodvor.by/shop/komplektuyuschie-otopleniya/dymokhody/baxi/',
        'https://www.teplodvor.by/shop/komplektuyuschie-otopleniya/avtomatika-i-termoregulyatory/baxi/',
        'https://www.teplodvor.by/shop/komplektuyuschie-otopleniya/rasshiritelnye-baki/reflex/',
        'https://www.teplodvor.by/shop/vodonagrevateli/kosvennye/viena/',
    ];

    protected static function matchKey(string $name): string
    {
        return VistarPriceList::modelKey($name);
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }

    /** @return array<string, string> */
    protected function index(): array
    {
        $index = parent::index();
        $urls = Cache::remember('teplodvor-brand:listings:baxi', now()->addWeek(), function (): array {
            $urls = [];
            foreach (self::LISTINGS as $listing) {
                for ($page = 1; $page <= 6; $page++) {
                    $response = Http::timeout(30)->retry(2, 1000, throw: false)
                        ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)'])
                        ->get($listing.($page > 1 ? '?PAGEN_1='.$page : ''));
                    $before = count($urls);
                    if ($response->successful()) {
                        preg_match_all('~href="(https://www\.teplodvor\.by/shop/[^"]*(?:'.static::BRAND.')[^"]*/)"~iu', $response->body(), $m);
                        $urls = array_values(array_unique(array_merge($urls, $m[1])));
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
            if (isset($found[$url]) || preg_match('~/(?:'.static::BRAND.')/$~', $url)) {
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

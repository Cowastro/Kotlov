<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * smolcom.by, the Smolcom factory shop in Belarus: every colour of a portal (and the fireplaces it sells) is an offer
 * with the same article as the Смолком price list («GOVV4Ч», «KYOC6Бб») and photos of its own (1772×1772). The
 * offers sit in the JCCatalogElement JSON of a product page. The index (article => photo URLs) is cached for a week.
 */
class SmolcomSiteCatalog
{
    private const BASE = 'https://smolcom.by';

    /** Catalog sections with goods of the price list. */
    private const SECTIONS = ['portali', 'electrokamini'];

    /** @var array<string, list<string>>|null */
    private ?array $index = null;

    /** @return list<string> absolute photo URLs of the offer with this article */
    public function photos(string $article): array
    {
        $this->index ??= Cache::remember('smolcom-site:index:v1', now()->addWeek(), fn (): array => $this->build());

        return $this->index[self::articleKey($article)] ?? [];
    }

    public function size(): int
    {
        $this->index ??= Cache::remember('smolcom-site:index:v1', now()->addWeek(), fn (): array => $this->build());

        return count($this->index);
    }

    /** «GOVV4Бб» and «govv4бб» meet; spaces and dashes do not count. */
    public static function articleKey(string $article): string
    {
        return mb_strtolower((string) preg_replace('/[\s\-]+/u', '', $article));
    }

    /**
     * Offers of one product page: article => photos.
     *
     * @return array<string, list<string>>
     */
    public static function parseOffers(string $html): array
    {
        $result = [];
        $start = strpos($html, "'OFFERS':[");
        if ($start === false) {
            return [];
        }
        $html = substr($html, $start);
        // One chunk per offer: «{'ID':'6779','NAME':…» up to the next offer.
        $chunks = preg_split("/(?=\\{'ID':'\\d+','NAME':)/", $html) ?: [];
        foreach ($chunks as $chunk) {
            if (! preg_match("/'ARTICLE':\\{'NAME':'[^']*','VALUE':'([^']+)'/u", $chunk, $article)
                && ! preg_match('/Артикул: <\\\\\/span><span class=\\\\"value\\\\" itemprop=\\\\"value\\\\">([^<]+)</u', $chunk, $article)) {
                continue;
            }
            $slider = strpos($chunk, "'SLIDER':[");
            if ($slider === false) {
                continue;
            }
            $end = strpos($chunk, ']', $slider);
            preg_match_all("/'SRC':'(\\/upload\\/[^']+\\.(?:jpe?g|png|webp))'/i", substr($chunk, $slider, $end === false ? 20000 : $end - $slider), $m);
            $photos = array_values(array_unique(array_map(fn (string $src): string => self::BASE.$src,
                array_filter($m[1], fn (string $src): bool => ! str_contains($src, 'resize_cache')))));
            $key = self::articleKey(html_entity_decode($article[1]));
            if ($photos !== [] && ! isset($result[$key])) {
                $result[$key] = array_slice($photos, 0, 6);
            }
        }

        return $result;
    }

    /** @return array<string, list<string>> */
    private function build(): array
    {
        // Product pages: «/catalog/portali/6778/» and «/catalog/portali/portaly-iz-dereva-mdf/796/».
        $sitemap = $this->fetch(self::BASE.'/sitemap-iblock-23.xml').$this->fetch(self::BASE.'/sitemap-iblock-26.xml');
        preg_match_all('~<loc>(https://smolcom\.by/catalog/(?:'.implode('|', self::SECTIONS).')/(?:[a-z0-9_-]+/)?\d+/)</loc>~', $sitemap, $m);
        $index = [];
        foreach (array_unique($m[1]) as $url) {
            foreach (self::parseOffers($this->fetch($url)) as $article => $photos) {
                $index[$article] ??= $photos;
            }
            usleep(200_000);
        }

        return $index;
    }

    private function fetch(string $url): string
    {
        try {
            $response = Http::connectTimeout(10)->timeout(30)->retry(2, 1000, throw: false)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)', 'Accept-Language' => 'ru-RU,ru;q=0.9'])
                ->get($url);
        } catch (\Throwable) {
            // An unreachable site gives no photos, it does not stop the others.
            return '';
        }

        return $response->successful() ? $response->body() : '';
    }
}

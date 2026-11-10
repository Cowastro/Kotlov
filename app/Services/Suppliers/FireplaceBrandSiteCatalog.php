<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Russian brand sites of Dimplex (dimplex.ru) and Royal Flame (royalflame.ru): a product page shows «Артикул
 * 64920045», the same number as the Смолком price list, and photos of 1500 px in /upload/iblock/. The index
 * (article => photo URLs) is cached for a week.
 */
class FireplaceBrandSiteCatalog
{
    /** Site => sitemap of its catalog. */
    private const SITES = [
        'https://dimplex.ru' => 'https://dimplex.ru/sitemap/sitemap-iblock-1.xml',
        'https://royalflame.ru' => 'https://royalflame.ru/sitemap/sitemap_iblock_1.xml',
    ];

    /** @var array<string, list<string>>|null */
    private ?array $index = null;

    /** @return list<string> */
    public function photos(string $article): array
    {
        $this->index ??= Cache::remember('fireplace-brand-sites:index:v1', now()->addWeek(), fn (): array => $this->build());

        return $this->index[trim($article)] ?? [];
    }

    public function size(): int
    {
        $this->index ??= Cache::remember('fireplace-brand-sites:index:v1', now()->addWeek(), fn (): array => $this->build());

        return count($this->index);
    }

    /**
     * The article and photos of one product page.
     *
     * @return array{article:string,photos:list<string>}|null
     */
    public static function parsePage(string $html, string $base): ?array
    {
        $plain = (string) preg_replace(['~<script\b.*?</script>~is', '~<style\b.*?</style>~is', '~<[^>]+>~'], ' ', $html);
        $text = (string) preg_replace('/[\s\x{00A0}]+/u', ' ', html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // A product page names one article; a listing names many and is skipped.
        preg_match_all('/Артикул:?\s*(\d{5,10})\b/u', $text, $articles);
        if (count(array_unique($articles[1])) !== 1) {
            return null;
        }
        preg_match_all('~/upload/iblock/[^"\'\s)]+\.(?:jpe?g|png|webp)~i', $html, $m);
        $photos = array_values(array_unique(array_map(fn (string $src): string => $base.$src,
            array_filter($m[0], fn (string $src): bool => ! preg_match('/logo|icon|banner|sprite|accessor|resize_cache/i', $src)))));

        return $photos === [] ? null : ['article' => $articles[1][0], 'photos' => array_slice($photos, 0, 4)];
    }

    /** @return array<string, list<string>> */
    private function build(): array
    {
        $index = [];
        foreach (self::SITES as $base => $sitemap) {
            preg_match_all('~<loc>('.preg_quote($base, '~').'/catalog/[a-z0-9_-]+/)</loc>~i', $this->fetch($sitemap), $m);
            foreach (array_unique($m[1]) as $url) {
                $page = self::parsePage($this->fetch($url), $base);
                if ($page) {
                    $index[$page['article']] ??= $page['photos'];
                }
                usleep(200_000);
            }
        }

        return $index;
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(30)->retry(2, 1000, throw: false)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)', 'Accept-Language' => 'ru-RU,ru;q=0.9'])
            ->get($url);

        return $response->successful() ? $response->body() : '';
    }
}

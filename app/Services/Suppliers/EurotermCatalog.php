<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;

class EurotermCatalog
{
    public const BASE_URL = 'https://euroterm.by';

    /** @return array<int,array{title:string,url:string,image:string}> */
    public function crawl(string $categoryUrl, int $maxPages = 25): array
    {
        $queue = [$categoryUrl];
        $visited = [];
        $cards = [];

        while ($queue !== [] && count($visited) < $maxPages) {
            $url = array_shift($queue);
            if (isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;
            $html = $this->fetch($url);
            $page = $this->parseListingHtml($html, $url);

            foreach ($page['cards'] as $card) {
                $cards[$card['url']] = $card;
            }
            foreach ($page['pages'] as $pageUrl) {
                if (! isset($visited[$pageUrl]) && ! in_array($pageUrl, $queue, true)) {
                    $queue[] = $pageUrl;
                }
            }
        }

        return array_values($cards);
    }

    /**
     * @return array{cards:array<int,array{title:string,url:string,image:string}>,pages:array<int,string>}
     */
    public function parseListingHtml(string $html, string $pageUrl): array
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new \DOMXPath($dom);
        $cards = [];

        foreach ($xpath->query('//a[contains(concat(" ", normalize-space(@class), " "), " vm-product-media ")][@href]') ?: [] as $link) {
            $url = $this->absoluteUrl((string) $link->attributes?->getNamedItem('href')?->nodeValue, $pageUrl);
            $title = $this->clean((string) ($link->attributes?->getNamedItem('title')?->nodeValue ?: $link->textContent));
            $imageNode = $xpath->query('.//img', $link)?->item(0);
            $image = '';
            if ($imageNode) {
                $image = (string) ($imageNode->attributes?->getNamedItem('data-src')?->nodeValue
                    ?: $imageNode->attributes?->getNamedItem('src')?->nodeValue);
                $image = $this->absoluteUrl($image, $pageUrl);
            }
            if ($url !== '' && $title !== '') {
                $cards[$url] = compact('title', 'url', 'image');
            }
        }

        $pages = [];
        foreach ($xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " pagination ")]//a[@href]') ?: [] as $link) {
            $url = $this->absoluteUrl((string) $link->attributes?->getNamedItem('href')?->nodeValue, $pageUrl);
            if ($url !== '' && str_contains((string) parse_url($url, PHP_URL_PATH), '/results')) {
                $pages[$url] = $url;
            }
        }

        return ['cards' => array_values($cards), 'pages' => array_values($pages)];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,array{title:string,url:string,image:string}> $cards
     * @return array{card:?array,score:float,margin:float}
     */
    public function match(array $row, array $cards): array
    {
        $article = $this->compact((string) ($row['article'] ?? ''));
        $articleNoZeros = ltrim($article, '0');
        $rowTokens = $this->tokens((string) ($row['name'] ?? ''));
        $scored = [];

        foreach ($cards as $card) {
            $title = (string) ($card['title'] ?? '');
            $titleCompact = $this->compact($title);
            $score = 0.0;

            if (mb_strlen($article) >= 4 && str_contains($titleCompact, $article)) {
                $score = 1.0;
            } elseif (mb_strlen($articleNoZeros) >= 6 && str_contains($titleCompact, $articleNoZeros)) {
                $score = 0.98;
            } else {
                $titleTokens = $this->tokens($title);
                $common = array_intersect($rowTokens, $titleTokens);
                $coverage = count($rowTokens) > 0 ? count($common) / count($rowTokens) : 0;
                $jaccardBase = array_unique(array_merge($rowTokens, $titleTokens));
                $jaccard = count($jaccardBase) > 0 ? count($common) / count($jaccardBase) : 0;
                $score = round(($coverage * 0.7) + ($jaccard * 0.3), 4);
            }

            $scored[] = ['card' => $card, 'score' => $score];
        }

        usort($scored, fn (array $left, array $right): int => $right['score'] <=> $left['score']);
        $best = $scored[0] ?? null;
        $second = $scored[1]['score'] ?? 0.0;
        $margin = $best ? round($best['score'] - $second, 4) : 0.0;
        $accepted = $best && ($best['score'] >= 0.72 || ($best['score'] >= 0.58 && $margin >= 0.12));

        return [
            'card' => $accepted ? $best['card'] : null,
            'score' => (float) ($best['score'] ?? 0),
            'margin' => $margin,
        ];
    }

    public function categoryUrl(array $row): string
    {
        if (($row['category'] ?? '') === 'Распределительные коллекторы') {
            return self::BASE_URL.'/komplektuyushchie-dlya-sistem-otopleniya/kollektory-raspredelitelnye.html';
        }

        return trim((string) ($row['source_url'] ?? ''));
    }

    private function fetch(string $url): string
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
        ])
            ->connectTimeout(10)
            ->timeout(45)
            ->retry(2, 700)
            ->get($url)
            ->throw()
            ->body();
    }

    /** @return array<int,string> */
    private function tokens(string $value): array
    {
        $value = mb_strtolower(str_replace('ё', 'е', $value));
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $value) ?: [];
        $stop = [
            'котел', 'котел', 'газовый', 'электрический', 'настенный', 'одноконтурный', 'двухконтурный',
            'китай', 'корея', 'польша', 'словакия', 'рф', 'profline', 'navien', 'salus', 'controls',
            'комплект', 'для', 'с', 'на', 'и', 'в', 'шт', 'мм', 'д', 'd',
        ];

        return array_values(array_unique(array_filter($parts, fn (string $token): bool =>
            mb_strlen($token) >= 2 && ! in_array($token, $stop, true)
        )));
    }

    private function compact(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(str_replace('ё', 'е', $value))) ?? '';
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private function absoluteUrl(string $url, string $base): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $origin = (parse_url($base, PHP_URL_SCHEME) ?: 'https').'://'.parse_url($base, PHP_URL_HOST);

        return $origin.'/'.ltrim($url, '/');
    }
}

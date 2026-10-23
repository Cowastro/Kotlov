<?php

namespace App\Services\Suppliers;

use App\Services\Market\MarketWebResearchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Product pages on the internet for a model that is in no supplier price
 * (phase 2 of creating cards from a КП). Google results via serper.dev:
 * marketplaces, social networks and our own site are dropped, manufacturer
 * and known shop pages come first. The manager picks one page; its photos
 * and specs fill the card (SupplierOfferCards::createFromWeb).
 */
class WebProductSources
{
    /** Never a source: marketplaces, classifieds, video and social networks. */
    private const DENY = [
        'kotlov.by', 'wildberries', 'ozon.', 'aliexpress', 'market.yandex', 'megamarket', 'avito', 'kufar.by',
        'onliner.by', 'deal.by', 'prom.ua', 'rozetka', 'ebay.', 'amazon.', 'youtube', 'vk.com', 'ok.ru',
        'pinterest', 'instagram', 'facebook', 'tiktok', 't.me', 'dzen.ru', 'drive2', 'otzovik', 'irecommend',
    ];

    /** Shops whose product pages our enricher reads well. */
    private const TRUSTED = [
        'teplodvor.by', 'aqualider.by', 'bio-tep.ru', 'teplo.by', '100kotlov.by', 'santehsklad.by', 'ksk.by',
        'santeh-help.by', 'kaminbel.by', 'ochag.by', '100kaminov.by', 'ligmet.by', 'satro-paladin.com', 'rusklimat.ru',
        'vodopad.ru', 'santehnika-online.ru', 'teplodom.ru', 'ecokamin.ru',
    ];

    /** Which search answered: serper, ai or none. */
    public string $engine = 'none';

    /** HTTP status of the last Google request (0: not asked). */
    public int $lastStatus = 0;

    /** Error text of the last failed Google request. */
    public string $lastError = '';

    /** Google (serper.dev) or, without it, the AI web search of the market research. */
    public function isConfigured(): bool
    {
        return $this->key() !== '' || $this->aiConfigured();
    }

    /** @return array<string, string> url => «domain · title» */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if (mb_strlen($query) < 3 || ! $this->isConfigured()) {
            return [];
        }
        $this->engine = 'none';

        $cacheKey = 'web-product-sources:'.sha1(mb_strtolower($query));
        if (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }
        $found = (function () use ($query, $limit): array {
            $results = [];
            if ($this->key() === '') {
                return $this->aiSearch($query, $limit);
            }
            foreach ([['gl' => 'by', 'hl' => 'ru'], ['gl' => 'ru', 'hl' => 'ru']] as $locale) {
                $response = Http::timeout(20)->retry(2, 1000, throw: false)
                    ->withHeaders(['X-API-KEY' => $this->key(), 'Content-Type' => 'application/json'])
                    ->post('https://google.serper.dev/search', ['q' => $query, 'num' => 10] + $locale);
                $this->lastStatus = $response->status();
                $this->lastError = $response->successful() ? '' : (string) ($response->json('message') ?? Str::limit($response->body(), 120));
                foreach ((array) $response->json('organic', []) as $item) {
                    $url = (string) ($item['link'] ?? '');
                    $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
                    $host = preg_replace('/^www\./', '', $host) ?? $host;
                    if ($host === '' || isset($results[$url]) || $this->denied($host) || ! preg_match('#^https?://[^/]+/.+#', $url)) {
                        continue;
                    }
                    $results[$url] = ['label' => $host.' · '.Str::limit(trim((string) ($item['title'] ?? '')), 90), 'score' => $this->score($host, $url, $query)];
                }
                if (count($results) >= $limit) {
                    break;
                }
            }
            // No Google credits or a failed request: the AI web search finds the pages instead.
            if ($results === [] && $this->lastError !== '') {
                return $this->aiSearch($query, $limit);
            }
            $this->engine = 'serper';
            uasort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            return array_map(fn (array $r): string => $r['label'], array_slice($results, 0, $limit, true));
        })();
        // A failed or empty search is not remembered: the next click asks Google again.
        if ($found !== []) {
            Cache::put($cacheKey, $found, now()->addDay());
        }

        return $found;
    }

    /** @return array<string, string> url => label, from the market research web search */
    private function aiSearch(string $query, int $limit): array
    {
        if (! $this->aiConfigured()) {
            return [];
        }
        $research = app(MarketWebResearchService::class)->researchQuery($query);
        $pages = array_merge((array) ($research['offers'] ?? []), (array) ($research['sources'] ?? []));
        $results = [];
        foreach ($pages as $page) {
            $url = trim((string) ($page['url'] ?? ''));
            $host = preg_replace('/^www\./', '', Str::lower((string) parse_url($url, PHP_URL_HOST))) ?? '';
            if ($host === '' || isset($results[$url]) || $this->denied($host) || ! preg_match('#^https?://[^/]+/.+#', $url)) {
                continue;
            }
            $results[$url] = ['label' => $host.' · '.Str::limit(trim((string) ($page['title'] ?? $page['seller'] ?? '')), 90), 'score' => $this->score($host, $url, $query)];
        }
        if ($results !== []) {
            $this->engine = 'ai';
        }
        uasort($results, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(fn (array $r): string => $r['label'], array_slice($results, 0, $limit, true));
    }

    private function aiConfigured(): bool
    {
        $provider = trim((string) config('services.ai.market_research_provider', 'deepseek'));
        $key = match ($provider) {
            'deepseek' => config('services.ai.api_key'),
            'azure' => config('services.ai.azure_openai_key'),
            default => config('services.ai.openai_key'),
        };

        return trim((string) $key) !== '';
    }

    private function denied(string $host): bool
    {
        foreach (self::DENY as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** Trusted shops first, then pages naming the model, Belarusian sites before others. */
    private function score(string $host, string $url, string $query): int
    {
        $score = 0;
        foreach (self::TRUSTED as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                $score += 50;
            }
        }
        $path = Str::lower(urldecode((string) parse_url($url, PHP_URL_PATH)));
        foreach (preg_split('/[^\p{L}\p{N}]+/u', Str::lower($query)) ?: [] as $token) {
            if (mb_strlen($token) >= 2 && preg_match('/\d/', $token) && str_contains($path, $token)) {
                $score += 10;
            }
        }

        return $score + (str_ends_with($host, '.by') ? 5 : 0);
    }

    private function key(): string
    {
        return (string) (config('services.serper.key') ?: env('SERPER_API_KEY', ''));
    }
}

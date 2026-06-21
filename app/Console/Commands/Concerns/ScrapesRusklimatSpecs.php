<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Finds a product's page on rusklimat.ru (via Serper) and scrapes its real
 * characteristics table. Shared by spec/attribute backfill commands.
 *
 * Requires SERPER_API_KEY in the environment.
 */
trait ScrapesRusklimatSpecs
{
    /** Pages we trust for real specs. Order = preference. */
    private array $specDomains = [
        'teplodvor.by', 'rusklimat.ru', 'b2b.rusklimat.com', 'rusklimat.by',
        'satro-paladin.com', '7-kvt.ru', 'dc-electro.ru',
    ];

    /** Non-spec keys that table/gallery headers leak in. */
    private array $specKeyDeny = ['фото', 'наименование', 'код товара', 'артикул', 'цена', 'кол-во', 'количество', 'бренд', 'производитель', 'описание'];

    /** Find the best matching rusklimat product page URL, or null. */
    protected function findProductPage(string $article, string $brand, string $name): ?string
    {
        $collect = function (array $queries): array {
            $out = [];
            foreach ($queries as $q) {
                foreach ($this->serperOrganic($q) as $link) {
                    $host = mb_strtolower(parse_url($link, PHP_URL_HOST) ?: '');
                    foreach ($this->specDomains as $d) {
                        if (str_contains($host, $d)) {
                            $out[] = $link;
                            break;
                        }
                    }
                }
            }
            return $out;
        };

        $match = fn ($l) => $this->pageMatchesProduct($l, $brand, $name);

        $primary = array_values(array_filter([
            $name !== '' ? "{$name} site:teplodvor.by/shop" : '',
            $brand !== '' && $name !== '' ? "{$brand} {$name} site:teplodvor.by/shop" : '',
            $article !== '' ? "{$article} site:rusklimat.ru" : '',
            $name !== '' ? "{$name} site:rusklimat.ru" : '',
        ]));
        $candidates = array_values(array_filter($collect($primary), $match));
        $best = $this->bestProductPage($candidates);
        if ($best !== null) {
            return $best;
        }

        $fallback = array_values(array_unique(array_filter([
            $article,
            $brand !== '' && $article !== '' ? "{$brand} {$article}" : '',
            $brand !== '' && $name !== '' ? "{$brand} {$name}" : '',
            $name,
        ])));
        $candidates = array_merge($candidates, array_values(array_filter($collect($fallback), $match)));

        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $this->pageRank($a) <=> $this->pageRank($b));
        return $candidates[0];
    }

    private function pageMatchesProduct(string $url, string $brand, string $name): bool
    {
        $u = mb_strtolower($url);

        $brandOk = true;
        $bWord = preg_split('/\s+/', trim($this->translitLower($brand)))[0] ?? '';
        if ($bWord !== '' && mb_strlen($bWord) >= 3) {
            $brandOk = str_contains($u, $bWord);
        }

        $tokens = [];
        preg_match_all('/[a-z0-9]{2,}/', $this->translitLower($name), $mm);
        foreach ($mm[0] as $t) {
            if (mb_strlen($t) >= 3 && preg_match('/\d/', $t)) {
                $tokens[] = $t;
            }
        }
        $modelOk = $tokens === [];
        foreach ($tokens as $t) {
            if (str_contains($u, $t)) {
                $modelOk = true;
                break;
            }
        }

        return $brandOk && $modelOk;
    }

    private function translitLower(string $s): string
    {
        $map = [
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i',
            'й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t',
            'у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'',
            'э'=>'e','ю'=>'yu','я'=>'ya',
        ];
        return strtr(mb_strtolower($s), $map);
    }

    private function bestProductPage(array $candidates): ?string
    {
        $candidates = array_values(array_filter($candidates, fn ($l) => $this->pageRank($l) <= 2));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn ($a, $b) => $this->pageRank($a) <=> $this->pageRank($b));
        return $candidates[0];
    }

    private function pageRank(string $link): int
    {
        $host    = mb_strtolower(parse_url($link, PHP_URL_HOST) ?: '');
        $product = str_contains($link, '/product/') || str_contains($link, '/shop/');
        return match (true) {
            str_contains($host, 'teplodvor.by') && $product => 0,
            str_contains($host, 'rusklimat.ru') && $product => 1,
            str_contains($host, 'rusklimat.by') && $product => 2,
            $product                                        => 2,
            str_contains($host, 'rusklimat.ru')             => 3,
            default                                         => 5,
        };
    }

    /** @return string[] organic result links */
    private function serperOrganic(string $query): array
    {
        try {
            $r = Http::timeout(20)
                ->withHeaders(['X-API-KEY' => env('SERPER_API_KEY'), 'Content-Type' => 'application/json'])
                ->post('https://google.serper.dev/search', ['q' => $query, 'num' => 10, 'gl' => 'ru', 'hl' => 'ru']);
            return $r->successful()
                ? array_values(array_filter(array_map(fn ($it) => $it['link'] ?? null, $r->json('organic') ?? [])))
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    protected function fetchPage(string $url): ?string
    {
        try {
            $r = Http::timeout(25)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'ru-RU,ru;q=0.9',
                ])
                ->withOptions(['verify' => false])
                ->get($url);
            return $r->successful() ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string,string> [name => value] */
    protected function scrapeSpecs(string $html): array
    {
        $specs = [];

        if (preg_match_all('/<span[^>]+class="[^"]*param-name[^"]*"[^>]*>\s*(.*?)\s*<\/span>.*?<span[^>]+class="[^"]*param-value[^"]*"[^>]*>\s*(.*?)\s*<\/span>/isu', $html, $m)) {
            $this->collectSpecs($specs, $m[1], $m[2]);
        }
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/isu', $html, $rows)) {
            foreach ($rows[1] as $rowHtml) {
                if (preg_match_all('/<t[dh][^>]*>(.*?)<\/t[dh]>/isu', $rowHtml, $cells) && count($cells[1]) >= 2) {
                    $this->collectSpecCells($specs, $cells[1]);
                }
            }
        }
        if (preg_match_all('/<tr[^>]*>\s*<t[dh][^>]*>(.*?)<\/t[dh]>\s*<t[dh][^>]*>(.*?)<\/t[dh]>\s*<\/tr>/isu', $html, $m)) {
            $this->collectSpecs($specs, $m[1], $m[2]);
        }
        if (preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/isu', $html, $m)) {
            $this->collectSpecs($specs, $m[1], $m[2]);
        }
        if (preg_match_all('/<script[^>]+type="application\/ld\+json"[^>]*>(.*?)<\/script>/isu', $html, $blocks)) {
            foreach ($blocks[1] as $json) {
                foreach ($this->findAdditionalProperties(json_decode(trim($json), true)) as $prop) {
                    $k = trim((string) ($prop['name'] ?? ''));
                    $v = trim((string) ($prop['value'] ?? ''));
                    if ($k !== '' && $v !== '' && ! isset($specs[$k])) {
                        $specs[$k] = $v;
                    }
                }
            }
        }

        return $specs;
    }

    private function collectSpecs(array &$specs, array $keys, array $vals): void
    {
        for ($i = 0, $n = count($keys); $i < $n; $i++) {
            $k = trim(preg_replace('/\s+/u', ' ', strip_tags($keys[$i])) ?? '');
            $v = trim(preg_replace('/\s+/u', ' ', strip_tags($vals[$i] ?? '')) ?? '');
            if ($k === '' || $v === '' || $v === '—' || mb_strlen($k) > 50 || mb_strlen($v) > 80) {
                continue;
            }
            if (str_word_count(strip_tags($v), 0, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя') > 8) {
                continue;
            }
            $kl = mb_strtolower($k);
            foreach ($this->specKeyDeny as $deny) {
                if (str_starts_with($kl, $deny)) {
                    continue 2;
                }
            }
            if (! isset($specs[$k])) {
                $specs[$k] = $v;
            }
        }
    }

    private function collectSpecCells(array &$specs, array $cells): void
    {
        $texts = array_values(array_filter(array_map(
            fn ($cell): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $cell), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''),
            $cells
        ), fn (string $text): bool => $text !== ''));

        if (count($texts) < 2) {
            return;
        }

        $value = $this->bestRusklimatSpecValue(array_slice($texts, 1));
        if ($value === null) {
            return;
        }

        $this->collectSpecs($specs, [$texts[0]], [$value]);
    }

    private function bestRusklimatSpecValue(array $candidates): ?string
    {
        $best = null;
        $bestScore = -1;

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || $candidate === 'â€”') {
                continue;
            }

            $unitOnly = $this->isRusklimatUnitOnlyValue($candidate);
            $score = 0;
            $score += preg_match('/\d/u', $candidate) ? 100 : 0;
            $score += $unitOnly ? -100 : 25;
            $score += mb_strlen($candidate) > 2 ? 5 : 0;

            if ($score > $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        return $best !== null && ! $this->isRusklimatUnitOnlyValue($best) ? $best : null;
    }

    private function isRusklimatUnitOnlyValue(string $value): bool
    {
        $value = mb_strtolower(trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $value = str_replace(['°', '℃', '²', '³', 'кв.', 'кв '], ['', 'c', '2', '3', '', ''], $value);
        $value = preg_replace('/[.,;:(){}\[\]\/\\\\|]+/u', ' ', $value) ?? $value;
        $tokens = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($tokens === []) {
            return true;
        }

        $units = ['bar', 'c', 'cm', 'g', 'kg', 'kw', 'kvt', 'l', 'm', 'm2', 'm3', 'mm', 'mpa', 'pa', 'v', 'w', 'бар', 'в', 'вт', 'г', 'дюйм', 'квт', 'кг', 'л', 'литр', 'м', 'м2', 'м3', 'мес', 'месяц', 'мин', 'мм', 'мпа', 'па', 'см', 'час', 'шт'];

        foreach ($tokens as $token) {
            if (! in_array($token, $units, true)) {
                return false;
            }
        }

        return true;
    }

    private function findAdditionalProperties($data): array
    {
        if (! is_array($data)) {
            return [];
        }
        if (isset($data['additionalProperty']) && is_array($data['additionalProperty'])) {
            return $data['additionalProperty'];
        }
        $out = [];
        foreach ($data as $v) {
            if (is_array($v)) {
                $out = array_merge($out, $this->findAdditionalProperties($v));
            }
        }
        return $out;
    }

    protected function scrapeShort(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property="og:description"[^>]+content="([^"]+)"/i', $html, $m) ||
            preg_match('/<meta[^>]+content="([^"]+)"[^>]+property="og:description"/i', $html, $m)) {
            $t = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            return mb_strlen($t) > 15 ? $t : null;
        }
        return null;
    }
}

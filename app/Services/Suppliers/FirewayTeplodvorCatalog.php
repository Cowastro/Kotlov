<?php

namespace App\Services\Suppliers;

use App\Console\Commands\EnrichTeplodvorCommand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The Fireway goods of teplodvor.by: photos, specs and description for the cards made from the Камин-профессионал
 * price list. The sitemap gives the Fireway pages; a page is found by the same model as the price row
 * (KaminproffPriceList::modelKey of its title). Pages are cached for a week.
 */
class FirewayTeplodvorCatalog
{
    private const BASE = 'https://www.teplodvor.by';

    /** @var array<string, string>|null model key => page URL */
    private ?array $byModel = null;

    public function find(string $name): ?string
    {
        $this->byModel ??= $this->index();

        return $this->byModel[self::looseKey($name)] ?? null;
    }

    /**
     * The model key without what teplodvor.by adds or writes otherwise: «(стекло 8 мм, 3 петли)», «чёрный»,
     * «мат» for «матовая», «(302)» for «(К302)», «Дверь для бани стеклянная».
     */
    public static function looseKey(string $name): string
    {
        $name = (string) preg_replace('/\((?:стекло\s*)?\d\s*мм,?\s*\d\s*петл[а-я]*\)|\d\s*петл[а-я]*\s*\d\s*мм|\d\s*петл[а-я]*|стекло\s*\d\s*мм/iu', ' ', $name);
        $words = [];
        foreach (explode(' ', KaminproffPriceList::modelKey($name)) as $word) {
            $word = (string) preg_replace('/^k(\d{3})$/', '$1', $word);
            $word = match (true) {
                in_array($word, ['мат', 'матовая', 'матовый', 'матовое'], true) => 'мат',
                in_array($word, ['черный', 'стеклянная', 'стекло', 'сетка', 'сеткой', 'cеткой', 'c', 'стеклом'], true) => '',
                default => $word,
            };
            if ($word !== '') {
                $words[$word] = true;
            }
        }
        $words = array_keys($words);
        sort($words);

        return implode(' ', $words);
    }

    /** @return array{name:string,images:list<string>,specs:array<string,string>,serviceInfo:array<string,string>,desc:string}|null */
    public function page(string $url): ?array
    {
        $html = Cache::remember('fireway-teplodvor:page:'.md5($url), now()->addWeek(), fn () => $this->fetch($url));

        return $html !== '' ? EnrichTeplodvorCommand::parsePage($html) : null;
    }

    /** @return array<string, string> */
    private function index(): array
    {
        $urls = Cache::remember('fireway-teplodvor:urls', now()->addWeek(), function (): array {
            $urls = [];
            for ($page = 1; $page <= 12; $page++) {
                $xml = $this->fetch(self::BASE.'/map/sitemap/'.$page.'/');
                if ($xml === '') {
                    break;
                }
                preg_match_all('~<loc>(https://www\.teplodvor\.by/shop/[^<]*fireway[^<]*/)</loc>~iu', $xml, $m);
                $urls = array_merge($urls, $m[1]);
            }

            return array_values(array_unique($urls));
        });
        $index = [];
        $ambiguous = [];
        foreach ($urls as $url) {
            // Brand and category listings end in /fireway/.
            if (preg_match('~/fireway/$~', $url)) {
                continue;
            }
            $page = $this->page($url);
            // A patinated stove is the same model in another colour: its photos are not the card's.
            if (! $page || $page['name'] === '' || str_contains(mb_strtolower($page['name']), 'патина')) {
                continue;
            }
            foreach (KaminproffPriceList::variants(str_replace(['(левая/правая)', 'левая/правая'], 'левая/правая', $page['name'])) as $variant) {
                $key = self::looseKey($variant);
                if ($key === '') {
                    continue;
                }
                if (isset($index[$key]) && $index[$key] !== $url) {
                    $ambiguous[$key] = true;
                }
                $index[$key] = $url;
            }
        }

        return array_diff_key($index, $ambiguous);
    }

    private function fetch(string $url): string
    {
        $response = Http::timeout(30)->retry(2, 1000, throw: false)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)', 'Accept-Language' => 'ru-RU,ru;q=0.9'])
            ->get($url);

        return $response->successful() ? $response->body() : '';
    }
}

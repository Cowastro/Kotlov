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

    /** The brand in the teplodvor.by page addresses (a regex alternation). */
    protected const BRAND = 'fireway';

    /** @var array<string, string>|null model key => page URL */
    protected ?array $byModel = null;

    public function find(string $name): ?string
    {
        $this->byModel ??= $this->index();

        return $this->byModel[static::matchKey($name)] ?? null;
    }

    /** How many models the index holds (0 when the site gave nothing). */
    public function indexSize(): int
    {
        $this->byModel ??= $this->index();

        return count($this->byModel);
    }

    /** The key a price row and a page title meet by. */
    protected static function matchKey(string $name): string
    {
        return self::looseKey($name);
    }

    /**
     * The names a page title stands for: «SOFIA (левая/правая)» is two cards; a patinated stove is the same
     * model in another colour, its photos are not the card's.
     *
     * @return list<string>
     */
    protected function titleNames(string $title): array
    {
        if (str_contains(mb_strtolower($title), 'патина')) {
            return [];
        }

        return KaminproffPriceList::variants(str_replace(['(левая/правая)', 'левая/правая'], 'левая/правая', $title));
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
        $html = Cache::remember('teplodvor-brand:page:'.md5($url), now()->addWeek(), fn () => $this->fetch($url));

        return $html !== '' ? EnrichTeplodvorCommand::parsePage($html) : null;
    }

    /** @return array<string, string> */
    protected function index(): array
    {
        $urls = Cache::remember('teplodvor-brand:urls:'.static::BRAND, now()->addWeek(), function (): array {
            $urls = [];
            for ($page = 1; $page <= 12; $page++) {
                $xml = $this->fetch(self::BASE.'/map/sitemap/'.$page.'/');
                if ($xml === '') {
                    break;
                }
                preg_match_all('~<loc>(https://www\.teplodvor\.by/shop/[^<]*(?:'.static::BRAND.')[^<]*/)</loc>~iu', $xml, $m);
                $urls = array_merge($urls, $m[1]);
            }

            return array_values(array_unique($urls));
        });
        $index = [];
        $ambiguous = [];
        foreach ($urls as $url) {
            // Brand and category listings end in /fireway/.
            if (preg_match('~/(?:'.static::BRAND.')/$~', $url)) {
                continue;
            }
            $page = $this->page($url);
            if (! $page || $page['name'] === '') {
                continue;
            }
            foreach ($this->titleNames($page['name']) as $variant) {
                $key = static::matchKey($variant);
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

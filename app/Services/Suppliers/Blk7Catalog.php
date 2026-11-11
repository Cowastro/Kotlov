<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;

/**
 * ООО «БЛК7» (b2b.blk7.by, OpenCart): the brand pages are open without login and list every item with the warehouse
 * stock («Наличие на складе: 3 шт.» or «Под заказ»), the article «BLK0073707», the retail price (price-old) and
 * «Оптовая цена» in BYN. A product page has photos of 500 px and the specs as «Ключ: значение» lines.
 *
 * The site sends its certificate without the GlobalSign intermediate, so requests verify against the GlobalSign
 * chain kept in resources/certs (intermediate «GCC R6 AlphaSSL CA 2025» and «Root R6») — TLS stays verified.
 */
class Blk7Catalog
{
    public const BASE = 'https://b2b.blk7.by';

    /** Brand => its catalog page. */
    public const BRANDS = [
        'lavoro' => 'https://b2b.blk7.by/kotly-tverdotoplivnye/lavoro/',
    ];

    /**
     * @return list<array{article:string,name:string,url:string,image:string,stock:?int,preorder:bool,retail:float,wholesale:float}>
     */
    public function items(string $brand): array
    {
        $url = self::BRANDS[$brand] ?? null;

        return $url ? self::parseList($this->fetch($url.'?limit=200')) : [];
    }

    /** @return list<array{article:string,name:string,url:string,image:string,stock:?int,preorder:bool,retail:float,wholesale:float}> */
    public static function parseList(string $html): array
    {
        $rows = [];
        foreach (array_slice(explode('<div class="product-layout', $html), 1) as $block) {
            if (! preg_match('~<h4><a href="([^"]+)">(.*?)</a>~s', $block, $title) || ! preg_match('/Артикул:\s*<b>\s*([^<\s]+)/u', $block, $article)) {
                continue;
            }
            $stock = preg_match('/Наличие на складе:\s*(\d+)/u', $block, $s) ? (int) $s[1] : null;
            preg_match('/class="price-old">\s*([\d.,\s]+)/u', $block, $retail);
            preg_match('/Оптовая цена:\s*([\d.,\s]+)/u', $block, $wholesale);
            preg_match('~<img[^>]+src="([^"]+)"~', $block, $image);
            $rows[] = [
                'article' => trim($article[1]),
                'name' => trim(html_entity_decode(strip_tags($title[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'url' => $title[1],
                'image' => isset($image[1]) ? (string) preg_replace('/-\d+x\d+\.(png|jpe?g|webp)$/i', '-500x500.$1', $image[1]) : '',
                'stock' => $stock,
                'preorder' => $stock === null && str_contains($block, 'Под заказ'),
                'retail' => self::number($retail[1] ?? ''),
                'wholesale' => self::number($wholesale[1] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Photos (500 px, in page order) and «Ключ: значение» specs of a product page.
     *
     * @return array{images:list<string>,specs:array<string,string>}
     */
    public function page(string $url): array
    {
        return self::parsePage($this->fetch($url), $url);
    }

    /** @return array{images:list<string>,specs:array<string,string>} */
    public static function parsePage(string $html, string $url = ''): array
    {
        preg_match_all('~https://b2b\.blk7\.by/image/cache/[^"\'\s]+-500x500\.(?:png|jpe?g|webp)~i', $html, $m);
        // 500 px pictures are the item's gallery only (related items show 228 px thumbs); a series shares photos,
        // so a gallery photo may be named after a sibling model.
        $images = array_slice(array_values(array_unique($m[0])), 0, 6);
        $specs = [];
        $start = strpos($html, 'id="tab-description"');
        if ($start !== false) {
            $text = html_entity_decode(strip_tags((string) preg_replace('~<br\s*/?>|</p>|</li>|</tr>~i', "\n", substr($html, $start, 20000))), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            foreach (preg_split('/\R/u', $text) ?: [] as $line) {
                if (preg_match('/^\s*([^:\n]{3,60}?)\s*:\s*(.{1,120}?)\s*$/u', $line, $pair) && ! str_contains($pair[1], 'http')) {
                    $specs[trim($pair[1])] ??= trim($pair[2]);
                }
            }
        }

        return ['images' => $images, 'specs' => $specs];
    }

    /**
     * The model of a name: «Твердотопливный котел Lavoro Eco L-12 (без автоматики)» → «l 12 bez»; «Lavoro Eco M-25
     * (двухконтурный)» → «m 25 dk»; «Lavoro Carbone LC Mega-12» → «lcmega 12»; the Cyrillic «К-12» is «k 12».
     */
    public static function modelKey(string $name): string
    {
        $n = mb_strtolower(str_replace('ё', 'е', $name));
        $n = strtr($n, ['к-' => 'k-', 'к ' => 'k ']);
        if (! preg_match('/\b(lc\s*mega|lc|lf|lp|xl|l|m|k)\s*-?\s*(\d{2,3})\b/u', $n, $m)) {
            return '';
        }
        $flags = array_filter([
            str_contains($n, 'без автоматики') ? 'bez' : null,
            str_contains($n, 'двухконтур') ? 'dk' : null,
        ]);

        return trim(str_replace(' ', '', $m[1]).' '.$m[2].' '.implode(' ', $flags));
    }

    public function fetch(string $url): string
    {
        try {
            $response = Http::withOptions(['verify' => resource_path('certs/blk7-globalsign-chain.pem')])
                ->connectTimeout(10)->timeout(40)->retry(2, 1000, throw: false)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KotlovBot/1.0)', 'Accept-Language' => 'ru-RU,ru;q=0.9'])
                ->get($url);
        } catch (\Throwable) {
            return '';
        }

        return $response->successful() ? $response->body() : '';
    }

    private static function number(string $value): float
    {
        $value = str_replace([' ', "\u{00A0}", ','], ['', '', '.'], trim($value));

        return is_numeric($value) ? round((float) $value, 2) : 0.0;
    }
}

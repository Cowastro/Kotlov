<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * banipechi.by (ЧТУП «Бани и печи», OpenCart): the ProMetall dealer. The brand page lists the ProMetall goods;
 * a product page names the ProMetall article (p0369) in its info list, the photos as data-zoom-image, the
 * description in #tab-description and the specs in the #tab-specification table. The article → page index
 * is built once a day.
 */
class BanipechiCatalog
{
    private const BRAND_PAGE = 'https://banipechi.by/prometall?limit=1000';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /** The product page of a ProMetall article («p0130;132» is looked up by its first part). */
    public function find(string $article): ?string
    {
        $key = self::key($article);

        return $this->index()[$key] ?? null;
    }

    /** @return array<string, string> article => page */
    public function index(): array
    {
        return Cache::remember('banipechi:prometall-index:v1', now()->addDay(), function (): array {
            $brand = $this->get(self::BRAND_PAGE);
            $links = [];
            foreach (array_slice(explode('class="product-thumb', $brand), 1) as $block) {
                if (preg_match('#href="(https://banipechi\.by/[^"?\#]+)"#', $block, $m)) {
                    $links[$m[1]] = true;
                }
            }
            $index = [];
            foreach (array_keys($links) as $url) {
                $html = $this->get($url);
                foreach (preg_match_all('#<li>\s*(p\d{3,4})(?:\s*[;,]\s*\d+)?\s*</li>#i', $html, $m) ? $m[1] : [] as $article) {
                    $index[self::key($article)] ??= $url;
                }
            }

            return $index;
        });
    }

    /**
     * @return array{title:string,description:string,specs:list<array{key:string,value:string,unit:string}>,images:list<string>}|null
     */
    public function page(string $url): ?array
    {
        $html = $this->get($url);
        if ($html === '') {
            return null;
        }
        $text = fn (string $value): string => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));
        $title = preg_match('/<h1[^>]*>(.*?)<\/h1>/su', $html, $m) ? $text($m[1]) : '';
        $specs = [];
        if (preg_match('/id="tab-specification"(.*?)<\/table>/su', $html, $table)
            && preg_match_all('/<tr>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<\/tr>/su', $table[1], $rows, PREG_SET_ORDER)) {
            foreach ($rows as $row) {
                $key = $text($row[1]);
                $value = $text($row[2]);
                if ($key !== '' && $value !== '') {
                    $specs[$key] = ['key' => $key, 'value' => $value, 'unit' => ''];
                }
            }
        }
        // The description tab; links to other goods of the dealer become plain text.
        $description = preg_match('/id="tab-description"[^>]*>(.*?)<\/div>\s*<div class="tab-pane"/su', $html, $m)
            ? trim(strip_tags($m[1], '<p><br><ul><ol><li><b><strong>'))
            : '';
        $images = array_values(array_unique(preg_match_all('#data-zoom-image="(https://banipechi\.by/image/cache/[^"]+\.(?:jpe?g|png|webp))"#i', $html, $m) ? $m[1] : []));

        return ['title' => $title, 'description' => $description, 'specs' => array_values($specs), 'images' => $images];
    }

    public static function key(string $article): string
    {
        return mb_strtolower((string) preg_replace('/[\s;,].*$/u', '', trim($article)));
    }

    private function get(string $url): string
    {
        try {
            $response = Http::timeout(40)->withUserAgent(self::USER_AGENT)->retry(2, 500, throw: false)->get($url);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}

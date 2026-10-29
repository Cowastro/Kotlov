<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;

/**
 * bereg.by (Чистый берег, Bitrix): a product page shows the supplier's 8-digit code as «Артикул»,
 * the full specs (div.newchar → product-page__char_row), the description (div.newdesc) and the photos
 * named «image_N_<code>.webp». The site search finds the page by that code.
 */
class BeregSiteCatalog
{
    private const SITE = 'https://www.bereg.by';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

    /** The product page of the code, checked to carry it as «Артикул». */
    public function find(string $code): ?string
    {
        $search = $this->get(self::SITE.'/search/?q='.urlencode($code));
        foreach (array_unique(preg_match_all('#href="(/product/[^"]+/)"#', $search, $m) ? $m[1] : []) as $path) {
            $html = $this->get(self::SITE.$path);
            if (preg_match('/product-page__char_prop">\s*Артикул\s*<\/p>\s*<p[^>]*>\s*'.preg_quote($code, '/').'\s*</u', $html)
                || str_contains($html, '_'.$code.'.')) {
                return self::SITE.$path;
            }
        }

        return null;
    }

    /**
     * @return array{title:string,description:string,specs:list<array{key:string,value:string,unit:string}>,images:list<string>}|null
     */
    public function page(string $url, string $code): ?array
    {
        $html = $this->get($url);
        if ($html === '') {
            return null;
        }
        $title = preg_match('/<h1[^>]*>(.*?)<\/h1>/su', $html, $m) ? trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5)) : '';
        // The full list sits in the description tab («newchar»); the short one above repeats part of it.
        $block = preg_match('/<div class="newchar">(.*?)<div class="newdesc">/su', $html, $m) ? $m[1] : $html;
        $specs = [];
        if (preg_match_all('/product-page__char_prop">(.*?)<\/p>\s*<p class="product-page__char_descr">(.*?)<\/p>/su', $block, $rows, PREG_SET_ORDER)) {
            foreach ($rows as $row) {
                $key = trim(html_entity_decode(strip_tags($row[1]), ENT_QUOTES | ENT_HTML5));
                $value = trim(html_entity_decode(strip_tags($row[2]), ENT_QUOTES | ENT_HTML5));
                if ($key === '' || $value === '' || in_array($key, ['Артикул', 'Код'], true)) {
                    continue;
                }
                $specs[$key] = ['key' => $key, 'value' => $value, 'unit' => ''];
            }
        }
        $description = preg_match('/<div class="newdesc">(.*?)<\/div>/su', $html, $m) ? trim($m[1]) : '';
        $images = array_values(array_unique(preg_match_all('#/upload/[^"\'\s]*?/image_\d+_'.preg_quote($code, '#').'\.(?:webp|jpe?g|png)#', $html, $m) ? $m[0] : []));
        // The gallery is resized to 500×500; smaller copies of the same picture are left out.
        $images = array_values(array_filter($images, fn (string $path) => ! preg_match('#/w_(?:[1-4]?\d{1,2})_h_#', $path)));

        return [
            'title' => $title,
            'description' => strip_tags($description, '<p><br><ul><ol><li><b><strong>'),
            'specs' => array_values($specs),
            'images' => array_map(fn (string $path) => self::SITE.$path, $images),
        ];
    }

    private function get(string $url): string
    {
        try {
            $response = Http::timeout(30)->withUserAgent(self::USER_AGENT)->retry(2, 500, throw: false)->get($url);

            return $response->successful() ? $response->body() : '';
        } catch (\Throwable) {
            return '';
        }
    }
}

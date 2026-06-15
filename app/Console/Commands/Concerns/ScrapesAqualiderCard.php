<?php

namespace App\Console\Commands\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Fetches and parses a single aqualider.by product card (Bitrix) — name, brand,
 * article, price, photo, description, characteristics and stock — and writes the
 * photo / specs onto a product. Shared by supplier:scrape-aqualider (catalogue
 * crawl) and supplier:enrich-tsk-nasosy (exact card per price-book link).
 */
trait ScrapesAqualiderCard
{
    /** Download a page; null on any failure. */
    protected function fetchCard(string $url): ?string
    {
        try {
            $r = Http::timeout(25)->withOptions(['verify' => false])
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
                               'Accept-Language' => 'ru-RU,ru;q=0.9'])->get($url);
            return $r->successful() ? $r->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse a product card into a structured array.
     *
     * @return array{name:string,brand:string,article:string,price:?float,specs:array<string,string>,image:?string,breadcrumb:string,desc:string,stockText:string,stockStatus:string,inStock:bool}
     */
    protected function parseCard(string $html, string $url): array
    {
        $name = $this->metaTag($html, 'og:title');
        $name = trim(preg_replace('/\s*купить.*$/iu', '', $name) ?? $name);

        $specs = [];
        preg_match_all(
            '/properties-group__name[^>]*>(?:\s*<[^>]+>)*\s*([^<]+).*?properties-group__value[^>]*>(?:\s*<[^>]+>)*\s*([^<]+)/su',
            $html, $m
        );
        for ($i = 0, $n = count($m[1]); $i < $n; $i++) {
            $k = trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[1][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            $v = trim(preg_replace('/\s+/u', ' ', html_entity_decode($m[2][$i], ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if ($k !== '' && $v !== '' && mb_strlen($k) <= 80 && ! isset($specs[$k])) {
                $specs[$k] = $v;
            }
        }

        $brand = $specs['Бренд'] ?? '';
        unset($specs['Бренд']);

        $article = '';
        if (preg_match('/Артикул:\s*<\/span>\s*<[^>]*>\s*([0-9A-Za-zА-Яа-я\-\/.]+)/u', $html, $a)
            || preg_match('/Артикул:[^0-9A-Za-z]*([0-9]{4,})/u', $html, $a)) {
            $article = trim($a[1]);
        }
        if ($article === '' && preg_match('#/(\d+)/?$#', $url, $idm)) {
            $article = 'AQ-' . $idm[1];
        }

        $price = null;
        if (preg_match('/itemprop="price"\s+content="([0-9.]+)"/', $html, $pm)) {
            $price = (float) $pm[1];
        }

        $image = $this->metaTag($html, 'og:image') ?: null;

        $crumbs = [];
        if (preg_match_all('/breadcrumbs__item-name[^>]*>([^<]+)</u', $html, $bm)) {
            $crumbs = array_map('trim', $bm[1]);
        }
        $breadcrumb = implode(' / ', $crumbs);

        $desc = $this->metaTag($html, 'og:description');

        // Availability from the site (Достаточно/Мало/Нет/Под заказ).
        $stockText = ''; $stockStatus = 'unknown'; $inStock = false;
        if (preg_match('/(Нет в наличии|Под заказ|Достаточно|Мало|В наличии)/u', $html, $sm)) {
            $stockText = $sm[1];
            $l = mb_strtolower($sm[1]);
            if (str_contains($l, 'нет')) {
                $stockStatus = 'out_of_stock';
            } elseif (str_contains($l, 'заказ')) {
                $stockStatus = 'preorder';
            } elseif (str_contains($l, 'мало')) {
                $stockStatus = 'low_stock'; $inStock = true;
            } else { // Достаточно / В наличии
                $stockStatus = 'in_stock'; $inStock = true;
            }
        }

        return compact('name', 'brand', 'article', 'price', 'specs', 'image', 'breadcrumb', 'desc',
            'stockText', 'stockStatus', 'inStock');
    }

    /** Read an og/meta property value (handles either attribute order). */
    protected function metaTag(string $html, string $prop): string
    {
        if (preg_match('/<meta[^>]+property="' . preg_quote($prop, '/') . '"[^>]+content="([^"]*)"/i', $html, $m)
            || preg_match('/<meta[^>]+content="([^"]*)"[^>]+property="' . preg_quote($prop, '/') . '"/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }

    /**
     * Write characteristics into product_attribute_values for the product's
     * category (creating attributes on demand). Returns the number written.
     */
    protected function writeCardSpecs(int $productId, int $catId, array $specs): int
    {
        $written = 0;
        foreach ($specs as $rawName => $rawVal) {
            // "Максимальная мощность, Вт" → name + suffix
            $suffix = '';
            $name = trim($rawName);
            if (preg_match('/^(.*),\s*([^,]{1,12})$/u', $name, $mm)) {
                $name = trim($mm[1]);
                $suffix = trim($mm[2]);
            }
            $low = mb_strtolower($rawVal);
            $isCheck = in_array($low, ['да', 'нет', 'есть'], true);
            $value = $isCheck ? null : trim($rawVal);
            if (! $isCheck && $suffix !== '' && preg_match('/-?\d+(?:[.,]\d+)?/u', $rawVal, $vm)) {
                $value = str_replace(',', '.', $vm[0]);
            } elseif (! $isCheck && $suffix !== '') {
                continue; // unit attr without a number — skip
            }

            $attr = DB::table('attributes')->where('category_id', $catId)->where('name', $name)
                ->first(['id', 'type', 'suffix']);
            if (! $attr) {
                $attrId = (int) DB::table('attributes')->insertGetId([
                    'category_id' => $catId, 'type' => $isCheck ? 'check' : 'value', 'name' => $name,
                    'suffix' => $suffix ?: null, 'in_product' => true, 'in_filter' => false,
                    'in_brief' => false, 'in_sort' => false, 'is_comparable' => false,
                    'sort_order' => (int) DB::table('attributes')->where('category_id', $catId)->max('sort_order') + 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $type = $isCheck ? 'check' : 'value';
            } else {
                $attrId = (int) $attr->id;
                $type = $attr->type;
            }

            if (DB::table('product_attribute_values')->where('product_id', $productId)->where('attribute_id', $attrId)->exists()) {
                continue;
            }
            DB::table('product_attribute_values')->insert([
                'product_id' => $productId, 'attribute_id' => $attrId, 'option_id' => null,
                'is_checked' => $type === 'check' ? ($low === 'да' || $low === 'есть' ? 1 : 0) : null,
                'value' => $type === 'check' ? null : $value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $written++;
        }
        return $written;
    }

    /**
     * Download the card photo into $imageDir and attach it to the product.
     * Skips products that already have a photo unless $overwrite. Returns true
     * when a new image was stored.
     */
    protected function downloadCardImage(int $productId, string $url, string $imageDir, bool $overwrite = false): bool
    {
        if (! $overwrite) {
            $cur = DB::table('products')->where('id', $productId)->value('images');
            $arr = json_decode((string) $cur, true);
            if (is_array($arr) && array_filter($arr, fn ($x) => is_string($x) && trim($x) !== '' && $x !== '[]')) {
                return false; // already has a photo
            }
        }
        try {
            $resp = Http::timeout(25)->withOptions(['verify' => false])->withHeaders(['User-Agent' => 'Mozilla/5.0'])->get($url);
            if (! $resp->successful() || ! str_starts_with((string) $resp->header('Content-Type'), 'image/')) {
                return false;
            }
            $ext = match (true) {
                str_contains($url, '.png') => 'png',
                str_contains($url, '.webp') => 'webp',
                default => 'jpg',
            };
            $dir = public_path($imageDir);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file = 'aq-' . $productId . '.' . $ext;
            file_put_contents($dir . DIRECTORY_SEPARATOR . $file, $resp->body());
            DB::table('products')->where('id', $productId)->update([
                'images' => json_encode([$imageDir . '/' . $file], JSON_UNESCAPED_UNICODE), 'updated_at' => now(),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Normalise an article for cross-tab matching: uppercase, drop a trailing
     * «.0» (numeric cells export as 61656.0), and fold Cyrillic look-alikes to
     * Latin so «СС02428» (sheet) == «CC02428» (site).
     */
    protected function foldArticle(string $s): string
    {
        $s = mb_strtoupper(trim($s));
        $s = preg_replace('/\.0$/', '', $s) ?? $s;
        $s = strtr($s, ['А'=>'A','В'=>'B','Е'=>'E','К'=>'K','М'=>'M','Н'=>'H','О'=>'O','Р'=>'P','С'=>'C','Т'=>'T','У'=>'Y','Х'=>'X']);
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }
}

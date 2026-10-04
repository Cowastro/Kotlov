<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TeplodvorElectricHeaterScraper
{
    public const BASE_URL = 'https://www.teplodvor.by';

    public function discoverUrls(): array
    {
        $urls = [];

        for ($page = 1; $page <= 6; $page++) {
            $response = Http::timeout(30)
                ->retry(2, 400)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KOTLOV catalog audit)'])
                ->get(self::BASE_URL."/map/sitemap/{$page}/");

            if (! $response->successful()) {
                continue;
            }

            preg_match_all('/<loc>(.*?)<\/loc>/u', $response->body(), $matches);
            foreach ($matches[1] ?? [] as $url) {
                $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
                $segments = array_values(array_filter(explode('/', $path)));

                if (count($segments) !== 3
                    || $segments[0] !== 'shop'
                    || $segments[1] !== 'elektricheskie-pechi'
                    || in_array($segments[2], ['harvia', 'karina', 'narvi', 'sawo', 'teplodar', 'termofor'], true)
                ) {
                    continue;
                }

                $urls[$segments[2]] = rtrim($url, '/').'/';
            }
        }

        ksort($urls);

        return $urls;
    }

    public function scrape(string $url): ?array
    {
        try {
            $response = Http::timeout(30)
                ->retry(2, 500)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; KOTLOV catalog sync)'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->parse($response->body(), $url);
    }

    public function parse(string $html, string $url): ?array
    {
        preg_match('/<h1[^>]*>(.*?)<\/h1>/si', $html, $heading);
        $name = $this->cleanText($heading[1] ?? '');
        if ($name === '') {
            return null;
        }

        $status = 'unknown';
        if (preg_match('/class=["\'][^"\']*status\s+yes[^"\']*["\'][^>]*>\s*Есть в наличии/iu', $html)) {
            $status = 'in_stock';
        } elseif (preg_match('/Снят с производства/iu', $html)) {
            $status = 'discontinued';
        } elseif (preg_match('/class=["\'][^"\']*status\s+no[^"\']*["\'][^>]*>\s*Под заказ/iu', $html)) {
            $status = 'on_order';
        } elseif (preg_match('/Нет в наличии|outofstock|нет на складе/iu', $html)) {
            $status = 'out_of_stock';
        }

        $price = 0.0;
        if (preg_match('/itemprop=["\']price["\'][^>]+content=["\']([0-9.,]+)["\']/iu', $html, $priceMatch)) {
            $price = (float) str_replace(',', '.', $priceMatch[1]);
        } elseif (preg_match('/<strong[^>]+itemprop=["\']price["\'][^>]*>([0-9.,]+)/iu', $html, $priceMatch)) {
            $price = (float) str_replace(',', '.', $priceMatch[1]);
        }

        $specs = [];
        preg_match_all('/<table[^>]*>(.*?)<\/table>/si', $html, $tables);
        foreach ($tables[1] ?? [] as $table) {
            preg_match_all('/<tr[^>]*>\s*<td[^>]*>(.*?)<\/td>\s*<td[^>]*>(.*?)<\/td>/si', $table, $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                $key = $this->cleanText($row[1] ?? '');
                $value = $this->cleanText($row[2] ?? '');
                if ($key === '' || $value === '' || mb_strlen($key) > 120 || mb_strlen($value) > 500) {
                    continue;
                }
                $specs[$key] = $value;
            }
        }

        preg_match_all('#(?:https?:)?//www\.teplodvor\.by/(userfls/shop/large/[^"\']+\.(?:jpe?g|png|webp))|(?:^|["\'])/?(userfls/shop/large/[^"\']+\.(?:jpe?g|png|webp))#iu', $html, $images, PREG_SET_ORDER);
        $imageUrls = [];
        foreach ($images as $image) {
            $path = $image[1] ?: ($image[2] ?? '');
            if ($path !== '') {
                $imageUrls[] = self::BASE_URL.'/'.ltrim($path, '/');
            }
        }

        return [
            'article' => basename(trim((string) parse_url($url, PHP_URL_PATH), '/')),
            'url' => $url,
            'name' => $name,
            'brand' => $this->brand($name, $url),
            'status' => $status,
            'price' => $price,
            'specs' => $specs,
            'images' => array_values(array_unique($imageUrls)),
        ];
    }

    public function canonicalModel(string $name, ?string $brand = null): string
    {
        $value = mb_strtolower(str_replace('ё', 'е', $name));
        $value = preg_replace('/\b(?:электрическая\s+печь|электропечь|электрокаменка|печь\s+для\s+бани)\b/ui', ' ', $value) ?? $value;
        if ($brand) {
            $value = str_ireplace($brand, ' ', $value);
        }

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value) ?? '';
    }

    private function brand(string $name, string $url): ?string
    {
        $haystack = mb_strtolower($name.' '.$url);
        $brands = [
            'sawo' => 'SAWO',
            'karina' => 'KARINA',
            'harvia' => 'Harvia',
            'cilindro' => 'Harvia',
            'born' => 'BORN',
            'teplodar' => 'Теплодар',
            'steamgross' => 'Теплодар',
            'steamline' => 'Теплодар',
            'steamfit' => 'Теплодар',
            'steamsib' => 'Теплодар',
            'steamcity' => 'Теплодар',
            'vezuviy' => 'Везувий',
            'везувий' => 'Везувий',
            'termofor' => 'Термофор',
            'primavolta' => 'Термофор',
            'meri' => 'Термофор',
            'ermak' => 'Ермак',
            'henki' => 'HENKI',
        ];

        foreach ($brands as $needle => $brand) {
            if (str_contains($haystack, $needle)) {
                return $brand;
            }
        }

        return null;
    }

    private function cleanText(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }
}

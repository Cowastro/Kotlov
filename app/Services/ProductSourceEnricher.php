<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductSourceEnricher
{
    private const IMAGE_DIR = 'img/products/source-enrichment';

    public function enrich(Product $product, string $sourceUrl, array $options = []): array
    {
        $sourceUrl = trim($sourceUrl);
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid source URL.');
        }

        $html = $this->fetchHtml($sourceUrl);
        $parsed = $this->parsePage($html, $sourceUrl);

        $updates = [];
        $stats = [
            'images_found' => count($parsed['images']),
            'images_saved' => 0,
            'specs_found' => count($parsed['specs']),
            'content_found' => $parsed['description'] !== '' ? 1 : 0,
        ];

        if (($options['update_images'] ?? true) === true && $parsed['images'] !== []) {
            $downloaded = $this->downloadImages($parsed['images'], $product);
            $stats['images_saved'] = count($downloaded);

            if ($downloaded !== []) {
                $existing = $this->decodeArray($product->images);
                $updates['images'] = ($options['replace_images'] ?? true)
                    ? $downloaded
                    : array_values(array_unique(array_merge($existing, $downloaded)));
            }
        }

        if (($options['update_specs'] ?? true) === true && $parsed['specs'] !== []) {
            $updates['specs'] = $parsed['specs'];
        }

        if (($options['update_content'] ?? true) === true && $parsed['description'] !== '') {
            $description = Str::limit(trim(strip_tags($parsed['description'])), 1800, '');
            $updates['content'] = '<p>' . e($description) . '</p>';
            $updates['short_description'] = Str::limit($description, 240, '');
            $updates['meta_description'] = Str::limit($description, 250, '');
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            $product->forceFill($updates)->save();
        }

        $supplierProductId = DB::table('supplier_products')
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->value('id');

        if ($supplierProductId) {
            DB::table('supplier_products')->where('id', $supplierProductId)->update([
                'source_url' => $sourceUrl,
                'updated_at' => now(),
            ]);
        }

        return $stats + ['updated_fields' => array_keys($updates)];
    }

    private function fetchHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; KOTLOV source enrichment)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->timeout(25)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Source page returned HTTP ' . $response->status());
        }

        return (string) $response->body();
    }

    private function parsePage(string $html, string $url): array
    {
        return [
            'description' => $this->extractDescription($html),
            'specs' => $this->extractSpecs($html),
            'images' => $this->extractImages($html, $url),
        ];
    }

    private function extractDescription(string $html): string
    {
        foreach ([
            '~<div[^>]+class=["\'][^"\']*(?:product-description|description|desc|tab-description)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+class=["\'][^"\']*(?:product-description|description|desc)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
            '~<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
            '~<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $text = $this->cleanText(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (mb_strlen($text) >= 40) {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractSpecs(string $html): array
    {
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);
        $specs = [];

        foreach ($xpath->query('//tr') ?: [] as $row) {
            $cells = [];
            foreach ($xpath->query('.//th|.//td', $row) ?: [] as $cell) {
                $cells[] = $this->cleanText($cell->textContent);
            }
            if (count($cells) >= 2) {
                $this->addSpec($specs, $cells[0], $cells[1]);
            }
        }

        foreach ($xpath->query('//dl') ?: [] as $dl) {
            $children = iterator_to_array($dl->childNodes);
            for ($i = 0; $i < count($children) - 1; $i++) {
                if (mb_strtolower($children[$i]->nodeName) !== 'dt') {
                    continue;
                }
                for ($j = $i + 1; $j < count($children); $j++) {
                    if (mb_strtolower($children[$j]->nodeName) === 'dd') {
                        $this->addSpec($specs, $children[$i]->textContent, $children[$j]->textContent);
                        break;
                    }
                }
            }
        }

        return array_slice(array_values($specs), 0, 80);
    }

    private function addSpec(array &$specs, string $key, string $value): void
    {
        $key = $this->cleanText($key);
        $value = $this->cleanText($value);

        if ($key === '' || $value === '' || mb_strlen($key) > 120 || mb_strlen($value) > 240) {
            return;
        }

        $normalized = mb_strtolower($key);
        if (isset($specs[$normalized])) {
            return;
        }

        $specs[$normalized] = [
            'key' => $key,
            'value' => $value,
            'unit' => '',
        ];
    }

    private function extractImages(string $html, string $pageUrl): array
    {
        $images = [];

        if (preg_match_all('~<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $images[] = $this->absoluteUrl($src, $pageUrl);
            }
        }

        if (preg_match_all('~<img[^>]+(?:src|data-src|data-large|data-original)=["\']([^"\']+)["\']~iu', $html, $matches)) {
            foreach ($matches[1] as $src) {
                $images[] = $this->absoluteUrl($src, $pageUrl);
            }
        }

        return array_values(array_slice(array_filter(array_unique($images), fn ($url) => $this->isProductImage($url)), 0, 12));
    }

    private function downloadImages(array $urls, Product $product): array
    {
        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = [];
        foreach ($urls as $index => $url) {
            try {
                $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0'])
                    ->timeout(25)
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $contentType = strtolower((string) $response->header('Content-Type'));
                if (! str_contains($contentType, 'image/')) {
                    continue;
                }

                $extension = $this->imageExtension($contentType, $url);
                $filename = Str::slug($product->sku ?: $product->slug ?: 'product') . '-' . ($index + 1) . '-' . substr(md5($url), 0, 8) . '.' . $extension;
                file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $response->body());
                $saved[] = self::IMAGE_DIR . '/' . $filename;
            } catch (\Throwable) {
                continue;
            }
        }

        return $saved;
    }

    private function imageExtension(string $contentType, string $url): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => in_array(strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
                ? strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))
                : 'jpg',
        };
    }

    private function isProductImage(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (! preg_match('~\.(?:jpe?g|png|webp|gif)(?:$|\?)~i', $path)) {
            return false;
        }

        if (preg_match('~(?:logo|icon|sprite|placeholder|noimage|nophoto|payment|social|banner|watermark|telegram|viber|whatsapp)~i', $path)) {
            return false;
        }

        if (preg_match('~[-_](\d{1,3})x(\d{1,3})(?:\.|$)~', $path, $size)) {
            return (int) $size[1] >= 120 && (int) $size[2] >= 120;
        }

        return true;
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, '//')) {
            return (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . ':' . $url;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '/')) {
            return (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($baseUrl, PHP_URL_HOST) . $url;
        }

        return rtrim(dirname($baseUrl), '/') . '/' . ltrim($url, '/');
    }

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return $dom;
    }

    private function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values(array_filter($decoded)) : [];
        }

        return [];
    }
}

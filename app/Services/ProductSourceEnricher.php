<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        return $this->enrichFromParsed($product, $sourceUrl, $parsed, $options);
    }

    public function enrichFromParsed(Product $product, string $sourceUrl, array $parsed, array $options = []): array
    {
        $sourceUrl = trim($sourceUrl);
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid source URL.');
        }

        $parsed = $this->normalizeParsedData($parsed);
        $updates = [];
        $stats = [
            'images_found' => count($parsed['images']),
            'images_saved' => 0,
            'specs_found' => count($parsed['specs']),
            'attribute_values_saved' => 0,
            'service_found' => count($parsed['service_info']),
            'content_found' => $parsed['description'] !== '' ? 1 : 0,
            'short_description_found' => $parsed['short_description'] !== '' ? 1 : 0,
            'errors' => [],
        ];

        $preview = [
            'images' => array_slice($parsed['images'], 0, 3),
            'description' => Str::limit(trim(strip_tags($parsed['description'] ?: $parsed['short_description'])), 700),
            'specs' => array_slice($parsed['specs'], 0, 8),
            'service_info' => array_slice($parsed['service_info'], 0, 5),
        ];

        if (($options['preview_only'] ?? false) === true) {
            return $stats + [
                'updated_fields' => [],
                'preview_only' => true,
                'preview' => $preview,
                'parsed' => $parsed,
            ];
        }

        try {
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
        } catch (\Throwable $e) {
            $stats['errors'][] = 'images: ' . $e->getMessage();
            Log::warning('Product source image enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        try {
            if (($options['update_specs'] ?? true) === true && $parsed['specs'] !== []) {
                $updates['specs'] = $this->sanitizeJsonArray($parsed['specs']);
                $stats['attribute_values_saved'] = $this->syncAttributeValues($product, $parsed['specs']);
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = 'attributes: ' . $e->getMessage();
            Log::warning('Product source attribute enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        try {
            if (($options['update_service'] ?? false) === true && $parsed['service_info'] !== []) {
                $updates['service_info'] = $this->sanitizeJsonArray($parsed['service_info']);
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = 'service: ' . $e->getMessage();
            Log::warning('Product source service enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        try {
            if (($options['update_content'] ?? true) === true && $parsed['description'] !== '') {
                $description = Str::limit(trim(strip_tags($parsed['description'])), 1800, '');
                $updates['content'] = '<p>' . e($description) . '</p>';
                $updates['short_description'] = Str::limit($parsed['short_description'] ?: $description, 240, '');
                $updates['meta_description'] = Str::limit($description, 250, '');
            } elseif (($options['update_content'] ?? true) === true && $parsed['short_description'] !== '') {
                $updates['short_description'] = Str::limit($parsed['short_description'], 240, '');
                $updates['meta_description'] = Str::limit($parsed['short_description'], 250, '');
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = 'content: ' . $e->getMessage();
            Log::warning('Product source content enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
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

    private function syncAttributeValues(Product $product, array $specs): int
    {
        $categoryId = (int) $product->category_id;
        if ($categoryId <= 0) {
            return 0;
        }

        $saved = 0;
        foreach ($specs as $spec) {
            $name = $this->cleanAttributeName((string) ($spec['key'] ?? ''));
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            if ($name === '' || $value === '' || $this->isTechnicalOrJunkAttribute($name, $value)) {
                continue;
            }

            [$value, $unit] = $this->splitValueAndUnit($value, (string) ($spec['unit'] ?? ''));
            $attributeId = $this->ensureAttribute($categoryId, $name, $unit);
            if ($attributeId <= 0) {
                continue;
            }

            DB::table('product_attribute_values')->updateOrInsert(
                [
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                ],
                [
                    'option_id' => null,
                    'is_checked' => null,
                    'value' => $value,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $saved++;
        }

        return $saved;
    }

    private function ensureAttribute(int $categoryId, string $name, string $unit): int
    {
        $normalized = $this->normalizeAttributeName($name);
        $existing = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->get(['id', 'name', 'suffix'])
            ->first(fn ($attribute) => $this->normalizeAttributeName((string) $attribute->name) === $normalized);

        if ($existing) {
            if ($unit !== '' && trim((string) $existing->suffix) === '') {
                DB::table('attributes')->where('id', $existing->id)->update([
                    'suffix' => $unit,
                    'updated_at' => now(),
                ]);
            }

            return (int) $existing->id;
        }

        $sortOrder = (int) DB::table('attributes')->where('category_id', $categoryId)->max('sort_order') + 10;

        return (int) DB::table('attributes')->insertGetId([
            'category_id' => $categoryId,
            'group_id' => 0,
            'sort_order' => $sortOrder,
            'type' => 'value',
            'name' => $name,
            'suffix' => $unit !== '' ? $unit : null,
            'in_filter' => false,
            'in_sort' => false,
            'in_product' => true,
            'in_brief' => false,
            'is_comparable' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function fetchHtml(string $url): string
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; KOTLOV source enrichment)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->timeout(15)->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Source page returned HTTP ' . $response->status());
        }

        return (string) $response->body();
    }

    private function parsePage(string $html, string $url): array
    {
        return $this->normalizeParsedData([
            'description' => $this->extractDescription($html),
            'short_description' => $this->extractShortDescription($html),
            'specs' => $this->extractSpecs($html),
            'service_info' => $this->extractServiceInfo($html),
            'images' => $this->extractImages($html, $url),
        ]);
    }

    private function normalizeParsedData(array $parsed): array
    {
        return [
            'description' => $this->cleanText((string) ($parsed['description'] ?? '')),
            'short_description' => $this->cleanText((string) ($parsed['short_description'] ?? '')),
            'specs' => $this->sanitizeJsonArray((array) ($parsed['specs'] ?? [])),
            'service_info' => $this->sanitizeJsonArray((array) ($parsed['service_info'] ?? [])),
            'images' => array_values(array_slice(array_filter(array_map(
                fn ($url) => filter_var($url, FILTER_VALIDATE_URL) ? (string) $url : '',
                (array) ($parsed['images'] ?? [])
            )), 0, 4)),
        ];
    }

    private function extractDescription(string $html): string
    {
        foreach ([
            '~<div[^>]+class=["\'][^"\']*(?:product-description|description|desc|tab-description)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+class=["\'][^"\']*(?:product-description|description|desc)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
            '~<div[^>]+id=["\'][^"\']*(?:product-description|description|desc|tab-description|content)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+id=["\'][^"\']*(?:product-description|description|desc|tab-description|content)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
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

        return $this->extractLongestDescriptionBlock($html);
    }

    private function extractShortDescription(string $html): string
    {
        foreach ([
            '~<div[^>]+class=["\'][^"\']*(?:short-description|short_description|intro|summary|product-short)[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu',
            '~<section[^>]+class=["\'][^"\']*(?:short-description|short_description|intro|summary|product-short)[^"\']*["\'][^>]*>([\s\S]*?)</section>~iu',
            '~<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
            '~<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $text = $this->cleanText(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (mb_strlen($text) >= 25) {
                    return $text;
                }
            }
        }

        return '';
    }

    private function extractLongestDescriptionBlock(string $html): string
    {
        $dom = $this->dom($html);
        $xpath = new \DOMXPath($dom);
        $best = '';

        foreach ($xpath->query('//*[self::div or self::section or self::article]') ?: [] as $node) {
            $marker = mb_strtolower(trim(($node->attributes?->getNamedItem('class')?->nodeValue ?? '') . ' ' . ($node->attributes?->getNamedItem('id')?->nodeValue ?? '')));
            if (! preg_match('/description|desc|content|detail|text|opis|tabs?/iu', $marker)) {
                continue;
            }

            $text = $this->cleanText($node->textContent);
            $length = mb_strlen($text);
            if ($length >= 80 && $length <= 5000 && $length > mb_strlen($best)) {
                $best = $text;
            }
        }

        return $best;
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

    private function extractServiceInfo(string $html): array
    {
        $info = [];
        $specs = $this->extractSpecs($html);

        foreach ($specs as $spec) {
            $key = (string) ($spec['key'] ?? '');
            $value = (string) ($spec['value'] ?? '');
            $normalized = mb_strtolower($key);

            if (preg_match('/гарант|сервис|производител|страна|импортер|импортёр|срок службы|сертификат/u', $normalized)) {
                $info[$key] = $value;
            }
        }

        $text = $this->cleanText(strip_tags($html));
        if (! isset($info['Гарантия']) && preg_match('/гаранти[яи]\s*[:\-]?\s*([0-9]+\s*(?:мес|месяц|год|года|лет))/iu', $text, $match)) {
            $info['Гарантия'] = $this->cleanText($match[1]);
        }

        return array_slice($info, 0, 20, true);
    }

    private function addSpec(array &$specs, string $key, string $value): void
    {
        $key = $this->cleanAttributeName($key);
        $value = $this->cleanAttributeValue($value);

        if ($key === '' || $value === '' || mb_strlen($key) > 120 || mb_strlen($value) > 240 || $this->isTechnicalOrJunkAttribute($key, $value)) {
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

        return array_values(array_slice(array_filter(array_unique($images), fn ($url) => $this->isProductImage($url)), 0, 4));
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
                    ->timeout(5)
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
        $text = $this->sanitizeUtf8($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    private function sanitizeJsonArray(array $value): array
    {
        $clean = $this->sanitizeUtf8Recursive($value);

        if (json_encode($clean, JSON_UNESCAPED_UNICODE) === false) {
            $clean = json_decode(json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE), true) ?: [];
        }

        return is_array($clean) ? $clean : [];
    }

    private function sanitizeUtf8Recursive(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->sanitizeUtf8($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $cleanKey = is_string($key) ? $this->sanitizeUtf8($key) : $key;
            $clean[$cleanKey] = $this->sanitizeUtf8Recursive($item);
        }

        return $clean;
    }

    private function sanitizeUtf8(string $value): string
    {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1251, CP1251, ISO-8859-1');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return iconv('UTF-8', 'UTF-8//IGNORE', $value) ?: '';
    }

    private function cleanAttributeName(string $name): string
    {
        $name = $this->cleanText($name);
        $name = trim($name, " \t\n\r\0\x0B:;•—-");

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function cleanAttributeValue(string $value): string
    {
        $value = $this->cleanText($value);
        $value = trim($value, " \t\n\r\0\x0B:;•—-");

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function normalizeAttributeName(string $name): string
    {
        $name = mb_strtolower($this->cleanAttributeName($name));
        $name = str_replace('ё', 'е', $name);
        $name = preg_replace('/[^a-zа-я0-9]+/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function isTechnicalOrJunkAttribute(string $name, string $value): bool
    {
        $normalizedName = $this->normalizeAttributeName($name);
        $normalizedValue = mb_strtolower($value);

        if ($normalizedName === '' || mb_strlen($normalizedName) < 2) {
            return true;
        }

        if (preg_match('/^(buy|price|delivery|payment|cart|sku|code|reviews|description|купить|цена|наличие|доставка|оплата|корзина|артикул|код товара|похожие товары|отзывы|описание)$/u', $normalizedName)) {
            return true;
        }

        return str_contains($normalizedValue, 'javascript:')
            || str_contains($normalizedValue, 'cookie')
            || mb_strlen($normalizedValue) > 240;
    }

    private function splitValueAndUnit(string $value, string $fallbackUnit = ''): array
    {
        $unit = trim($fallbackUnit);

        if ($unit === '' && preg_match('/^\s*([0-9]+(?:[,.][0-9]+)?)\s*(kw|w|watt|квт|вт|mm|cm|мм|см|м|l|л|kg|кг|g|г|m2|м2|м²|%|°c|c)\s*$/iu', $value, $match)) {
            $value = str_replace(',', '.', $match[1]);
            $unit = $match[2];
        }

        return [$value, $unit];
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

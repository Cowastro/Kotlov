<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncGorodkotlovVaillantCommand extends SyncThermostudioAristonCommand
{
    protected $signature = 'supplier:sync-gorodkotlov-vaillant
        {--apply : Write changes to the database}
        {--dry-run : Preview without writing changes}
        {--limit= : Limit number of products for testing}
        {--no-images : Skip product images}
        {--enrich : Generate unique SEO descriptions via AI}
        {--sleep=500 : Delay between requests in milliseconds}';

    protected $description = 'Scrape Vaillant gas boilers from gorodkotlov.by and sync prices, service info, documents and attributes.';

    protected const SUPPLIER_CODE = 'gorodkotlov';
    protected const SYNC_KEY = 'gorodkotlov_vaillant_gas_boilers';
    protected const SOURCE_URL = 'https://gorodkotlov.by/catalog/gazovye-kotly/vaillant/';
    protected const SOURCE_SITE_NAME = 'gorodkotlov.by';
    protected const BASE_URL = 'https://gorodkotlov.by';
    protected const CATEGORY_SLUG = 'gazovye';
    protected const BRAND_NAME = 'Vaillant';
    protected const BRAND_SLUG = 'vaillant';
    protected const BRAND_COUNTRY = 'Германия';
    protected const PRODUCT_URL_HINTS = ['vaillant'];
    protected const IMAGE_DISK_PATH = 'img/products/gorodkotlov/vaillant';

    private const SERVICE_ATTRIBUTE_MAP = [
        'Гарантия, мес' => 'Гарантия',
        'Страна производства' => 'Страна изготовления',
        'Импортер' => 'Импортер',
        'Изготовитель' => 'Завод изготовитель',
        'Cервисные центры' => 'Сервисный центр',
        'Сервисные центры' => 'Сервисный центр',
    ];

    protected function scrapeCatalog(int $sleepMs): array
    {
        $html = $this->fetch(static::SOURCE_URL);
        preg_match_all('/href="([^"]+)"/u', $html, $matches);

        $items = [];
        foreach ($matches[1] ?? [] as $href) {
            $url = $this->absoluteUrl(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $path = parse_url($url, PHP_URL_PATH) ?: '';

            if (! preg_match('#^/catalog/gazovye-kotly/[^/]+/$#u', $path)) {
                continue;
            }

            if (str_ends_with($path, '/vaillant/') || ! str_contains($path, 'vaillant')) {
                continue;
            }

            $items[$url] = [
                'url' => $url,
                'source_key' => trim($path, '/'),
            ];
        }

        return array_values($items);
    }

    protected function scrapeProduct(string $url): array
    {
        $html = $this->fetch($url);
        $body = str_contains($html, '<body') ? substr($html, strpos($html, '<body')) : $html;

        $name = $this->cleanText($this->match('/<h1[^>]*>([\s\S]*?)<\/h1>/u', $html) ?? '');
        $attributes = $this->extractTableAttributes($body);
        $serviceInfo = $this->extractServiceInfo($attributes);
        $price = $this->extractPrice($body);

        return [
            'name' => $name,
            'h1' => $name,
            'price_byn' => $price,
            'source_wp_id' => md5($url),
            'in_stock' => true,
            'availability_status' => 'check',
            'content' => $this->extractDescription($body),
            'attributes' => $attributes,
            'service_info' => $serviceInfo,
            'documents' => $this->extractDocuments($body),
            'promo_flags' => $this->extractPromoFlags($body, $url, $name),
            'images' => $this->extractImages($html),
            'video_url' => $this->extractVideoUrl($body),
        ];
    }

    protected function extractDocuments(string $body): array
    {
        preg_match_all('/<a[^>]+href="([^"]+\.pdf[^"]*)"[^>]*>([\s\S]*?)<\/a>/iu', $body, $matches, PREG_SET_ORDER);

        $documents = [];
        foreach ($matches as $match) {
            $url = $this->absoluteUrl(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $label = $this->cleanText($match[2]) ?: basename(parse_url($url, PHP_URL_PATH) ?: 'Документ');

            if (! str_contains($url, '/upload/')) {
                continue;
            }

            $documentUrl = (bool) $this->option('apply')
                ? $this->downloadDocument($url, $label)
                : $url;

            $documents[$url] = [
                'label' => $label,
                'url' => $documentUrl,
            ];
        }

        return array_values($documents);
    }

    protected function extractPromoFlags(string $body, string $url, string $name): array
    {
        $plain = mb_strtolower($this->cleanText($body . ' ' . $url . ' ' . $name));
        $flags = [];

        if (str_contains($plain, 'рассрочк')) {
            $flags[] = ['key' => 'installment', 'label' => 'Рассрочка'];
        }

        if (str_contains($plain, 'гарантия')) {
            $flags[] = ['key' => 'warranty', 'label' => 'Гарантия'];
        }

        return $flags;
    }

    protected function extractImages(string $html): array
    {
        preg_match_all('/(?:src|href|data-src|data-big|data-lazy)="([^"]+\.(?:jpg|jpeg|png|webp)(?:\?[^"]*)?)"/iu', $html, $matches);

        $images = [];
        foreach ($matches[1] ?? [] as $src) {
            $url = $this->absoluteUrl(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $path = parse_url($url, PHP_URL_PATH) ?: '';

            if (! preg_match('#/upload/iblock/.*\.(?:jpg|jpeg|png|webp)$#iu', $path)) {
                continue;
            }

            if (str_contains($path, '/resize_cache/')) {
                continue;
            }

            $images[$url] = $url;
        }

        return array_values($images);
    }

    protected function extractDescription(string $body): ?string
    {
        if (preg_match('/<div[^>]+class="[^"]*tabs-container-v2__item[^"]*"[^>]*>\s*([\s\S]*?)<div[^>]+class="[^"]*tabs-container-v2__item[^"]*"/iu', $body, $m)) {
            $content = $this->sanitizeHtml($m[1]);
            return $content !== '' ? $content : null;
        }

        if (preg_match('/<meta\s+name="description"\s+content="([^"]+)"/iu', $body, $m)) {
            return '<p>' . e($this->cleanText($m[1])) . '</p>';
        }

        return null;
    }

    protected function extractVideoUrl(string $body): ?string
    {
        if (preg_match('/(?:data-src|src)="(\/\/www\.youtube\.com\/embed\/[^"]+)"/iu', $body, $m)) {
            return 'https:' . html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/https?:\/\/(?:www\.)?(?:youtube\.com|youtu\.be)\/[^"\s<]+/iu', $body, $m)) {
            return html_entity_decode($m[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    protected function ensureSupplier($now): int
    {
        $existing = DB::table('suppliers')->where('code', static::SUPPLIER_CODE)->first();
        if ($existing) {
            DB::table('suppliers')->where('id', $existing->id)->update([
                'name' => 'Город Котлов',
                'contact' => static::SOURCE_URL,
                'is_active' => true,
                'updated_at' => $now,
            ]);
            return (int) $existing->id;
        }

        return (int) DB::table('suppliers')->insertGetId([
            'code' => static::SUPPLIER_CODE,
            'name' => 'Город Котлов',
            'currency' => 'BYN',
            'currency_rate' => 1,
            'contact' => static::SOURCE_URL,
            'notes' => 'Газовые котлы Vaillant с gorodkotlov.by. Цены BYN.',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function ensureSupplierSync($now): ?int
    {
        DB::table('supplier_syncs')->updateOrInsert(
            ['key' => static::SYNC_KEY],
            [
                'name' => 'Город Котлов Vaillant',
                'code' => static::SUPPLIER_CODE,
                'title' => 'Город Котлов: газовые котлы Vaillant',
                'description' => 'Скрапит газовые котлы Vaillant с gorodkotlov.by: цены BYN, характеристики, сервис, документы, фото и промо-флаги.',
                'command' => $this->getName(),
                'source_url' => static::SOURCE_URL,
                'image_disk_path' => static::IMAGE_DISK_PATH,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return DB::table('supplier_syncs')->where('key', static::SYNC_KEY)->value('id');
    }

    protected function normalizeName(string $name): string
    {
        $name = mb_strtoupper($name);
        $name = preg_replace('/\b(ГАЗОВЫЙ|КОТЕЛ|КОТЁЛ|КОНДЕНСАЦИОННЫЙ|VAILLANT|ВАЙЛАНТ)\b/u', '', $name) ?? $name;
        $name = preg_replace('/[^А-ЯЁA-Z0-9().+\/\- ]+/u', ' ', $name) ?? $name;
        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private function extractTableAttributes(string $body): array
    {
        preg_match_all(
            '/<td[^>]+class="cell_name"[^>]*>\s*<span[^>]*>([\s\S]*?)<\/span>\s*<\/td>\s*<td[^>]+class="cell_value"[^>]*>([\s\S]*?)<\/td>/iu',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $attributes = [];
        foreach ($matches as $match) {
            $name = $this->cleanText($match[1]);
            $value = $this->cleanText($match[2]);

            if ($name !== '' && $value !== '') {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    private function extractServiceInfo(array $attributes): array
    {
        $service = [];

        foreach (self::SERVICE_ATTRIBUTE_MAP as $source => $target) {
            if (! empty($attributes[$source])) {
                $value = $attributes[$source];
                if ($source === 'Гарантия, мес' && ! str_contains($value, 'мес')) {
                    $value .= ' мес.';
                }

                $service[$target] = $value;
            }
        }

        return $service;
    }

    private function extractPrice(string $body): ?float
    {
        if (! preg_match('/buy-block__price-new[^>]*>\s*([^<]+)/iu', $body, $m)) {
            return null;
        }

        $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        $raw = preg_replace('/[^0-9,.]/u', '', $raw) ?? '';
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }

    private function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim(static::BASE_URL, '/') . '/' . ltrim($url, '/');
    }

    private function downloadDocument(string $url, string $label): string
    {
        $dir = public_path('docs/suppliers/gorodkotlov/vaillant');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'pdf';
        $base = Str::slug($label) ?: md5($url);
        $filename = $base . '-' . substr(md5($url), 0, 8) . '.' . $extension;
        $target = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (! file_exists($target)) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (compatible; KotlovBot/1.0)\r\n",
                    'timeout' => 30,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            if ($body === false || $body === '') {
                return $url;
            }

            file_put_contents($target, $body);
        }

        return '/docs/suppliers/gorodkotlov/vaillant/' . $filename;
    }
}

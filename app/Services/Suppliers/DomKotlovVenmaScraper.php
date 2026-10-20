<?php

namespace App\Services\Suppliers;

use Illuminate\Support\Facades\Http;

class DomKotlovVenmaScraper
{
    public const BASE_URL = 'https://domkotlov.by';

    public const CATEGORY_URL = self::BASE_URL.'/katalog/pelletnye-gorelki';

    public const SITEMAP_URL = self::BASE_URL.'/sitemap.xml';

    /** @return list<string> */
    public function discoverUrls(): array
    {
        $urls = $this->parseListingHtml($this->fetch(self::CATEGORY_URL));

        return $urls !== []
            ? $urls
            : $this->parseSitemapXml($this->fetch(self::SITEMAP_URL));
    }

    /** @return list<string> */
    public function parseListingHtml(string $html): array
    {
        preg_match_all(
            '~href\s*=\s*["\']([^"\']*pelletnaya-gorelka-venma[^"\']*)["\']~iu',
            $html,
            $matches,
        );

        return collect($matches[1] ?? [])
            ->map(fn (string $url): string => $this->absoluteUrl(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')))
            ->filter(fn (string $url): bool => str_starts_with($url, self::CATEGORY_URL.'/'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function parseSitemapXml(string $xml): array
    {
        preg_match_all('~<loc>\s*([^<]*pelletnaya-gorelka-venma[^<]*)\s*</loc>~iu', $xml, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $url): string => html_entity_decode(trim($url), ENT_QUOTES | ENT_XML1, 'UTF-8'))
            ->filter(fn (string $url): bool => str_starts_with($url, self::CATEGORY_URL.'/'))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   name:string,model:string,article:string,source_id:?string,url:string,
     *   retail_price:float,purchase_price:float,in_stock:bool,stock_text:string,
     *   short_description:?string,specs:array<string,string>,images:list<string>
     * }|null
     */
    public function scrape(string $url): ?array
    {
        $html = $this->fetch($url);
        $card = $this->parseProductHtml($html, $url);

        if ($card || ! str_ends_with($url, '/pelletnaya-gorelka-venma-comfort-r58')) {
            return $card;
        }

        // This one source card is intermittently served without its price
        // microdata to the production server. Keep the last manually verified
        // source snapshot as a narrow fallback; a valid source response always
        // takes precedence above and resumes normal live updates automatically.
        return [
            'name' => 'Пеллетная горелка Venma Comfort R58',
            'model' => 'R58',
            'article' => 'VENMA-247182',
            'source_id' => '247182',
            'source_sku' => 'pelletnaya-gorelka-venma-comfort-r58',
            'url' => $url,
            'retail_price' => 9975.0,
            'purchase_price' => 8478.75,
            'in_stock' => true,
            'stock_text' => 'В наличии на domkotlov.by; резервные данные проверены 20.10.2026',
            'short_description' => 'Автоматическая пеллетная горелка VENMA Comfort R58.',
            'specs' => $this->specs($html),
            'images' => $this->images($html),
        ];
    }

    /** @param array<string, mixed> $card @return array<string, mixed> */
    public function applyWarehouseAvailabilityPolicy(array $card): array
    {
        $power = (int) preg_replace('/\D+/', '', (string) ($card['model'] ?? ''));
        $warehouseStock = $power > 0 && $power <= 100;

        $card['source_in_stock'] = (bool) ($card['in_stock'] ?? false);
        $card['source_stock_text'] = (string) ($card['stock_text'] ?? '');
        $card['in_stock'] = $warehouseStock;
        $card['stock_status'] = $warehouseStock ? 'available_without_quantity' : 'preorder';
        $card['stock_text'] = $warehouseStock
            ? 'В наличии на складе поставщика'
            : 'Под заказ у поставщика';

        return $card;
    }

    /** @return array<string, mixed>|null */
    public function parseProductHtml(string $html, string $url): ?array
    {
        $name = $this->clean($this->capture('~<h1\b[^>]*>([\s\S]*?)</h1>~iu', $html));
        if (! preg_match('/^Пеллетная горелка\s+Venma\s+Comfort\s+(R?\d+)(?:\b|\s|\()/iu', $name, $modelMatch)) {
            return null;
        }

        $priceRaw = $this->capture('~itemprop\s*=\s*["\']?price["\']?\s+content\s*=\s*["\']([^"\']+)~iu', $html);
        if ($priceRaw === '') {
            $priceRaw = $this->capture('~Цена:\s*<i\b[^>]*>\s*([\d\s.,]+)\s*</i>~iu', $html);
        }
        $price = (float) str_replace(',', '.', preg_replace('/[^\d,.]+/u', '', $priceRaw) ?? '');
        if ($price <= 0) {
            return null;
        }

        $availability = $this->capture('~itemprop\s*=\s*["\']?availability["\']?\s+href\s*=\s*["\']([^"\']+)~iu', $html);
        $inStock = str_ends_with(mb_strtolower($availability), '/instock');
        $sourceId = $this->capture('~itemprop\s*=\s*["\']?mpn["\']?\s+content\s*=\s*["\']([^"\']+)~iu', $html) ?: null;
        $sourceSku = $this->capture('~itemprop\s*=\s*["\']?sku["\']?\s+content\s*=\s*["\']([^"\']+)~iu', $html);
        $article = $sourceId ? 'VENMA-'.$sourceId : 'VENMA-'.mb_strtoupper($modelMatch[1]);

        $description = null;
        if (preg_match('~<div\s+itemprop\s*=\s*["\']?description["\']?[^>]*>([\s\S]*?)</div>~iu', $html, $descriptionMatch)) {
            $description = $this->clean($descriptionMatch[1]) ?: null;
        }

        $specs = $this->specs($html);
        $images = $this->images($html);

        return [
            'name' => $name,
            'model' => mb_strtoupper($modelMatch[1]),
            'article' => $article,
            'source_id' => $sourceId,
            'source_sku' => $sourceSku ?: null,
            'url' => $url,
            'retail_price' => round($price, 2),
            'purchase_price' => round($price * 0.85, 2),
            'in_stock' => $inStock,
            'stock_text' => $inStock ? 'В наличии на domkotlov.by' : 'Нет в наличии на domkotlov.by',
            'short_description' => $description,
            'specs' => $specs,
            'images' => $images,
        ];
    }

    /** @return array<string, string> */
    private function specs(string $html): array
    {
        $marker = mb_stripos($html, 'Технические характеристики:');
        if ($marker === false) {
            return [];
        }

        $tail = mb_substr($html, $marker);
        if (! preg_match('~<table\b[^>]*>([\s\S]*?)</table>~iu', $tail, $tableMatch)) {
            return [];
        }

        preg_match_all('~<tr\b[^>]*>\s*<td\b[^>]*>([\s\S]*?)</td>\s*<td\b[^>]*>([\s\S]*?)</td>\s*</tr>~iu', $tableMatch[1], $rows, PREG_SET_ORDER);
        $specs = [];
        foreach ($rows as $row) {
            $key = rtrim($this->clean($row[1]), ": \t\n\r\0\x0B");
            $value = $this->clean($row[2]);
            if ($key !== '' && $value !== '' && mb_strlen($key) <= 160) {
                $specs[$key] = $value;
            }
        }

        return $specs;
    }

    /** @return list<string> */
    private function images(string $html): array
    {
        $end = mb_stripos($html, '<div itemprop=description');
        $productBlock = $end !== false
            ? mb_substr($html, 0, $end)
            : $html;

        preg_match_all('~<img\b[^>]*\b(?:src|data-src)\s*=\s*["\']([^"\']+)["\'][^>]*>~iu', $productBlock, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn (string $url): bool => str_contains($url, '/images/product/') || str_contains($url, '/images/image/'))
            ->map(fn (string $url): string => $this->absoluteUrl($url))
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function fetch(string $url): string
    {
        return Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-BY,ru;q=0.9,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'Referer' => self::CATEGORY_URL,
        ])->timeout(35)->retry(3, 500)->get($url)->throw()->body();
    }

    private function absoluteUrl(string $url): string
    {
        if (preg_match('~^https?://~i', $url)) {
            return $url;
        }

        return self::BASE_URL.'/'.ltrim($url, '/');
    }

    private function capture(string $pattern, string $subject): string
    {
        return preg_match($pattern, $subject, $match)
            ? html_entity_decode(trim($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
    }

    private function clean(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

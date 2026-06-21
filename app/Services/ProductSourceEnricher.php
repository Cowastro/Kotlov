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
    private const SERVICE_LABELS = [
        'Производитель' => ['производитель', 'manufacturer'],
        'Импортер' => ['импортер', 'импортёр', 'импортер в рб', 'импортёр в рб', 'importer'],
        'Сервисный центр' => ['сервисный центр', 'сервисные центры', 'cервисные центры', 'service center'],
        'Страна происхождения' => ['страна происхождения', 'страна производства', 'страна', 'country of origin'],
        'Гарантия' => ['гарантия', 'гарантийный срок', 'warranty'],
    ];

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
            'images_replaced' => 0,
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
                $downloaded = $this->downloadImages($parsed['images'], $product, $sourceUrl);
                $stats['images_saved'] = count($downloaded);

                if ($downloaded !== []) {
                    $replaceImages = (bool) ($options['replace_images'] ?? true);

                    if ($replaceImages) {
                        $updates['images'] = array_values($downloaded);
                        $stats['images_replaced'] = 1;
                    } else {
                        $existing = $this->decodeArray($product->images);
                        $updates['images'] = array_values(array_unique(array_merge($existing, $downloaded)));
                    }
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
                $ai = new AiContentEnricher();

                if ($ai->isAvailable()) {
                    $brandName = (string) ($product->brand?->name ?? '');
                    $aiContent = $ai->enrich($product->name, $brandName, $parsed['description'], $parsed['specs']);

                    if ($aiContent !== null && trim(strip_tags($aiContent)) !== '') {
                        $updates['content'] = $this->sanitizeAiHtml($aiContent);

                        $shortDescription = $ai->shortDescription($product->name, $brandName, $parsed['specs'])
                            ?: $parsed['short_description']
                            ?: trim(strip_tags($aiContent));

                        $updates['short_description'] = Str::limit($this->cleanText($shortDescription), 240, '');
                        $updates['meta_description'] = Str::limit($this->cleanText($shortDescription), 250, '');
                    } else {
                        $stats['errors'][] = 'content: AI returned empty SEO text';
                    }
                } else {
                    $stats['errors'][] = 'content: AI provider is not configured; raw supplier text was not saved';
                }
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = 'content: ' . $e->getMessage();
            Log::warning('Product source content enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
        }

        if ($updates !== []) {
            $updates['updated_at'] = now();
            $product->forceFill($updates)->save();
        }

        $this->rememberSourceUrl($product, $sourceUrl);

        return $stats + ['updated_fields' => array_keys($updates)];
    }

    private function rememberSourceUrl(Product $product, string $sourceUrl): void
    {
        $supplierProductId = DB::table('supplier_products')
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->value('id');

        if ($supplierProductId) {
            DB::table('supplier_products')->where('id', $supplierProductId)->update([
                'source_url' => $sourceUrl,
                'updated_at' => now(),
            ]);

            return;
        }

        $supplierId = $this->manualSourceSupplierId();
        if ($supplierId <= 0) {
            return;
        }

        $article = 'manual-source-' . $product->id;

        DB::table('supplier_products')->insert([
            'supplier_id' => $supplierId,
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'supplier_article' => $article,
            'supplier_article_normalized' => Str::lower($article),
            'supplier_name' => $product->name,
            'source_url' => $sourceUrl,
            'match_status' => 'manual',
            'match_confidence' => 'source-url-edit',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function manualSourceSupplierId(): int
    {
        DB::table('suppliers')->updateOrInsert(
            ['code' => 'manual-source'],
            [
                'name' => 'Manual source URL',
                'currency' => 'BYN',
                'currency_rate' => 1,
                'contact' => null,
                'notes' => 'Системный поставщик для ссылок, добавленных вручную из карточки товара.',
                'is_active' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return (int) DB::table('suppliers')->where('code', 'manual-source')->value('id');
    }

    private function syncAttributeValues(Product $product, array $specs): int
    {
        $categoryId = (int) $product->category_id;
        if ($categoryId <= 0) {
            return 0;
        }

        $targetAttributeNames = [];
        foreach ($specs as $spec) {
            $name = $this->cleanAttributeName((string) ($spec['key'] ?? ''));
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            if ($name === '' || $value === '' || $this->isTechnicalOrJunkAttribute($name, $value)) {
                continue;
            }

            $targetAttributeNames[] = $this->normalizeAttributeName($name);
        }

        $this->deleteExistingAttributeValuesForNames($product, $categoryId, array_values(array_unique(array_filter($targetAttributeNames))));

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

    /**
     * Re-importing source specs must replace previous values, not append near-duplicates
     * like "Диаметр дымохода, мм" beside "Диаметр дымохода".
     *
     * @param array<int, string> $normalizedNames
     */
    private function deleteExistingAttributeValuesForNames(Product $product, int $categoryId, array $normalizedNames): void
    {
        if ($normalizedNames === []) {
            return;
        }

        $attributeIds = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->get(['id', 'name'])
            ->filter(fn ($attribute): bool => in_array($this->normalizeAttributeName((string) $attribute->name), $normalizedNames, true))
            ->pluck('id')
            ->all();

        if ($attributeIds === []) {
            return;
        }

        DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->whereIn('attribute_id', $attributeIds)
            ->delete();
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

        return $this->normalizeHtmlEncoding((string) $response->body(), (string) $response->header('Content-Type'));
    }

    private function normalizeHtmlEncoding(string $html, string $contentType = ''): string
    {
        $encoding = null;

        if (preg_match('/charset=([a-z0-9_\-]+)/iu', $contentType, $match)) {
            $encoding = strtoupper($match[1]);
        } elseif (preg_match('/<meta[^>]+charset=["\']?\s*([a-z0-9_\-]+)/iu', $html, $match)) {
            $encoding = strtoupper($match[1]);
        } elseif (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([a-z0-9_\-]+)/iu', $html, $match)) {
            $encoding = strtoupper($match[1]);
        }

        if ($encoding && ! in_array($encoding, ['UTF-8', 'UTF8'], true)) {
            $converted = @mb_convert_encoding($html, 'UTF-8', $encoding);
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        if (! mb_check_encoding($html, 'UTF-8')) {
            $converted = @mb_convert_encoding($html, 'UTF-8', 'Windows-1251, CP1251, ISO-8859-1');
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        $candidate = @mb_convert_encoding($html, 'UTF-8', 'Windows-1251');
        if (is_string($candidate)
            && mb_check_encoding($candidate, 'UTF-8')
            && $this->mojibakeScore($candidate) < $this->mojibakeScore($html)) {
            return $candidate;
        }

        return $this->repairMojibake($html);
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
        $containers = $this->specContainers($xpath);

        foreach ($containers as $container) {
            foreach ($xpath->query('.//tr', $container) ?: [] as $row) {
                $this->addSpecFromTableRow($xpath, $specs, $row);
            }

            foreach ($xpath->query('.//dl', $container) ?: [] as $dl) {
                $this->addSpecsFromDefinitionList($specs, $dl);
            }
        }

        return array_slice(array_values($specs), 0, 80);
    }

    /**
     * @return array<int, \DOMNode>
     */
    private function specContainers(\DOMXPath $xpath): array
    {
        $containers = [];
        foreach ($xpath->query('//*[self::div or self::section or self::article or self::table]') ?: [] as $node) {
            $marker = mb_strtolower(trim(implode(' ', [
                $node->attributes?->getNamedItem('class')?->nodeValue ?? '',
                $node->attributes?->getNamedItem('id')?->nodeValue ?? '',
                $node->attributes?->getNamedItem('aria-labelledby')?->nodeValue ?? '',
                $node->attributes?->getNamedItem('data-tab')?->nodeValue ?? '',
                $node->attributes?->getNamedItem('data-target')?->nodeValue ?? '',
            ])));

            if (! preg_match('/character|spec|param|property|feature|harakter|kharakter|характер|параметр|свойств/iu', $marker)) {
                continue;
            }

            $rowsOrLists = $xpath->query('.//tr|.//dl', $node);
            if ($rowsOrLists !== false && $rowsOrLists->length > 0) {
                $containers[] = $node;
            }
        }

        if ($containers !== []) {
            return $this->removeNestedContainers($containers);
        }

        $document = $xpath->document;

        return $document ? [$document] : [];
    }

    /**
     * @param array<int, \DOMNode> $containers
     * @return array<int, \DOMNode>
     */
    private function removeNestedContainers(array $containers): array
    {
        return array_values(array_filter($containers, function (\DOMNode $candidate) use ($containers): bool {
            foreach ($containers as $container) {
                if ($container === $candidate) {
                    continue;
                }

                if ($this->containsDomNode($container, $candidate)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function containsDomNode(\DOMNode $parent, \DOMNode $child): bool
    {
        for ($node = $child->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node === $parent) {
                return true;
            }
        }

        return false;
    }

    private function addSpecFromTableRow(\DOMXPath $xpath, array &$specs, \DOMNode $row): void
    {
        $cells = [];
        foreach ($xpath->query('.//th|.//td', $row) ?: [] as $cell) {
            $cells[] = $this->cleanText($cell->textContent);
        }
        if (count($cells) >= 2) {
            $this->addSpec($specs, $cells[0], $cells[1]);
        }
    }

    private function addSpecsFromDefinitionList(array &$specs, \DOMNode $dl): void
    {
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

    private function extractServiceInfo(string $html): array
    {
        $info = [];
        $specs = $this->extractSpecs($html);

        foreach ($specs as $spec) {
            $key = (string) ($spec['key'] ?? '');
            $value = (string) ($spec['value'] ?? '');
            $label = $this->serviceLabel($key);

            if ($label) {
                $value = $this->normalizeServiceValue($label, $value);
            }

            if ($label && $this->isUsefulServiceValue($value)) {
                $info[$label] = $value;
            }
        }

        foreach ($this->serviceTextBlocks($html) as $text) {
            foreach (self::SERVICE_LABELS as $label => $aliases) {
                foreach ($aliases as $alias) {
                    $pattern = '/^' . preg_quote($alias, '/') . '(?:\s*(?:в\s*рб|рб))?\s*[:\-]?\s+(.+)$/iu';
                    if (! preg_match($pattern, $text, $match)) {
                        continue;
                    }

                    if (isset($info[$label])) {
                        continue 3;
                    }

                    $value = $this->normalizeServiceValue($label, $match[1]);
                    if ($this->isUsefulServiceValue($value)) {
                        $info[$label] = $value;
                    }

                    continue 3;
                }
            }
        }

        $text = $this->cleanText($html);
        if (! isset($info['Гарантия']) && preg_match('/гаранти[яи]\s*[:\-]?\s*([0-9]+\s*(?:мес|месяц|год|года|лет))/iu', $text, $match)) {
            $info['Гарантия'] = $this->cleanText($match[1]);
        }

        return array_intersect_key($info, self::SERVICE_LABELS);
    }

    private function serviceLabel(string $key): ?string
    {
        $normalized = mb_strtolower($this->cleanText($key));

        foreach (self::SERVICE_LABELS as $label => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalized === $alias || str_starts_with($normalized, $alias . ' ')) {
                    return $label;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function serviceTextBlocks(string $html): array
    {
        $html = preg_replace('~<(br|/p|/div|/li|/tr)\b[^>]*>~iu', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return collect(preg_split('/\R+/u', $text) ?: [])
            ->map(fn (string $line): string => $this->cleanText($line))
            ->filter(fn (string $line): bool => $line !== '' && mb_strlen($line) <= 700)
            ->values()
            ->all();
    }

    private function cleanServiceValue(string $value): string
    {
        $value = $this->cleanText($value);
        $value = preg_replace('/\s*(Производитель|Импортер|Импортёр|Сервисный центр|Сервисные центры|Страна происхождения|Гарантия)\s*[:\-].*$/iu', '', $value) ?? $value;

        return trim($value);
    }

    private function normalizeServiceValue(string $label, string $value): string
    {
        $value = $this->cleanServiceValue($value);

        if ($label === 'Гарантия') {
            if (preg_match('/(?:мес\.?|месяц(?:ев|а)?|months?)\s*([0-9]{1,3})/iu', $value, $match)) {
                return $match[1] . ' мес';
            }

            if (preg_match('/([0-9]{1,3})\s*(?:мес\.?|месяц(?:ев|а)?|months?)/iu', $value, $match)) {
                return $match[1] . ' мес';
            }

            if (preg_match('/^\s*([0-9]{1,3})\s*$/u', $value, $match)) {
                return $match[1] . ' мес';
            }
        }

        return $value;
    }

    private function isUsefulServiceValue(string $value): bool
    {
        $value = trim($value);

        return $value !== ''
            && mb_strlen($value) >= 2
            && mb_strlen($value) <= 500
            && $this->serviceLabel($value) === null
            && ! preg_match('/^(да|нет|есть|подробнее|смотреть)$/iu', $value);
    }

    private function addSpec(array &$specs, string $key, string $value): void
    {
        $key = $this->cleanAttributeName($key);
        $value = $this->cleanAttributeValue($value);

        if ($key === '' || $value === '' || mb_strlen($key) > 120 || mb_strlen($value) > 240 || $this->looksLikeMojibake($key) || $this->isTechnicalOrJunkAttribute($key, $value)) {
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

        foreach (['img', 'source', 'a'] as $tag) {
            if (! preg_match_all('~<' . $tag . '\b[^>]*>~iu', $html, $tagMatches)) {
                continue;
            }

            foreach ($tagMatches[0] as $tagHtml) {
                foreach ($this->imageUrlsFromTag($tagHtml, $pageUrl) as $url) {
                    $images[] = $url;
                }
            }
        }

        foreach ($this->imageUrlsFromEmbeddedData($html, $pageUrl) as $url) {
            $images[] = $url;
        }

        $images = $this->expandedImageCandidates($images);
        usort($images, fn (string $left, string $right): int => $this->imageQualityScore($right) <=> $this->imageQualityScore($left));

        return array_values(array_slice($images, 0, 12));
    }

    /**
     * @param array<int, string> $urls
     * @return array<int, string>
     */
    private function expandedImageCandidates(array $urls): array
    {
        $expanded = [];

        foreach ($urls as $url) {
            $expanded[] = $url;

            foreach ($this->highResolutionImageVariants($url) as $variant) {
                $expanded[] = $variant;
            }
        }

        return array_values(array_filter(array_unique($expanded), fn ($url) => $this->isProductImage($url)));
    }

    /**
     * @return array<int, string>
     */
    private function highResolutionImageVariants(string $url): array
    {
        $variants = [];
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (str_contains($path, '/media/catalog/product/thumbnail/')) {
            $variants[] = preg_replace('~/image/(\d+)/\d{2,4}x\d{2,4}/~', '/image/$1/1000x1000/', $url) ?? $url;
            $variants[] = preg_replace('~/image/(\d+)/\d{2,4}x\d{2,4}/~', '/image/$1/1600x1600/', $url) ?? $url;
        }

        if (preg_match('~(?:^|[/_-])\d{2,4}x\d{2,4}(?:[/_\.-]|$)~', $path)) {
            $variants[] = preg_replace('~(?<=/)\d{2,4}x\d{2,4}(?=/)~', '1000x1000', $url) ?? $url;
            $variants[] = preg_replace('~(?<=[_-])\d{2,4}x\d{2,4}(?=[_\.-])~', '1000x1000', $url) ?? $url;
        }

        return array_values(array_filter(array_unique($variants), fn ($variant) => $variant !== $url));
    }

    /**
     * @return array<int, string>
     */
    private function imageUrlsFromTag(string $tagHtml, string $pageUrl): array
    {
        $urls = [];
        $attributes = [
            'data-full',
            'data-full-image',
            'data-zoom-image',
            'data-large',
            'data-original',
            'data-image',
            'data-lazy-src',
            'data-src-big',
            'data-big',
            'data-fancybox-href',
            'href',
            'data-src',
            'src',
        ];

        foreach ($attributes as $attribute) {
            if (preg_match('~\b' . preg_quote($attribute, '~') . '\s*=\s*["\']([^"\']+)["\']~iu', $tagHtml, $match)) {
                $urls[] = $this->absoluteUrl($match[1], $pageUrl);
            }
        }

        foreach (['srcset', 'data-srcset'] as $attribute) {
            if (! preg_match('~\b' . preg_quote($attribute, '~') . '\s*=\s*["\']([^"\']+)["\']~iu', $tagHtml, $match)) {
                continue;
            }

            foreach ($this->imageUrlsFromSrcset($match[1], $pageUrl) as $url) {
                $urls[] = $url;
            }
        }

        return array_values(array_filter(array_unique($urls)));
    }

    /**
     * @return array<int, string>
     */
    private function imageUrlsFromSrcset(string $srcset, string $pageUrl): array
    {
        $urls = [];
        foreach (explode(',', html_entity_decode($srcset, ENT_QUOTES | ENT_HTML5, 'UTF-8')) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate));
            $url = $parts[0] ?? '';
            if ($url !== '') {
                $urls[] = $this->absoluteUrl($url, $pageUrl);
            }
        }

        usort($urls, fn (string $left, string $right): int => $this->imageQualityScore($right) <=> $this->imageQualityScore($left));

        return $urls;
    }

    /**
     * @return array<int, string>
     */
    private function imageUrlsFromEmbeddedData(string $html, string $pageUrl): array
    {
        $urls = [];
        $decoded = html_entity_decode(str_replace('\/', '/', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match_all('~https?://[^"\'\s<>\\\\]+?\.(?:jpe?g|png|webp|gif|avif)(?:\?[^"\'\s<>\\\\]*)?~iu', $decoded, $matches)) {
            foreach ($matches[0] as $url) {
                $urls[] = $url;
            }
        }

        if (preg_match_all('~/(?:media|storage|img|images|upload|uploads|catalog|product)[^"\'\s<>\\\\]+?\.(?:jpe?g|png|webp|gif|avif)(?:\?[^"\'\s<>\\\\]*)?~iu', $decoded, $matches)) {
            foreach ($matches[0] as $url) {
                $urls[] = $this->absoluteUrl($url, $pageUrl);
            }
        }

        return array_values(array_filter(array_unique($urls)));
    }

    private function downloadImages(array $urls, Product $product, string $sourceUrl = ''): array
    {
        $dir = public_path(self::IMAGE_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = [];
        $candidateUrls = $this->expandedImageCandidates($urls);
        usort($candidateUrls, fn (string $left, string $right): int => $this->imageQualityScore($right) <=> $this->imageQualityScore($left));

        foreach ($candidateUrls as $url) {
            try {
                $response = Http::withHeaders($this->imageRequestHeaders($sourceUrl ?: $url))
                    ->connectTimeout(8)
                    ->timeout(20)
                    ->retry(2, 250)
                    ->get($url);

                if (! $response->successful()) {
                    continue;
                }

                $contentType = strtolower((string) $response->header('Content-Type'));
                $body = $response->body();
                $imageInfo = $this->imageInfo($body);
                if (! str_contains($contentType, 'image/') && $imageInfo === null) {
                    continue;
                }

                if (! $this->isLargeEnoughImage($body, $imageInfo)) {
                    continue;
                }

                $extension = $this->imageExtension($contentType, $url, $imageInfo);
                $filename = Str::slug($product->sku ?: $product->slug ?: 'product') . '-' . (count($saved) + 1) . '-' . substr(md5($url), 0, 8) . '.' . $extension;
                file_put_contents($dir . DIRECTORY_SEPARATOR . $filename, $body);
                $saved[] = self::IMAGE_DIR . '/' . $filename;

                if (count($saved) >= 4) {
                    break;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $saved;
    }

    /**
     * @return array<string, string>
     */
    private function imageRequestHeaders(string $referer): array
    {
        return [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Referer' => $referer,
        ];
    }

    private function imageExtension(string $contentType, string $url, ?array $imageInfo = null): string
    {
        $mime = strtolower((string) ($imageInfo['mime'] ?? ''));

        return match (true) {
            str_contains($contentType, 'png') || str_contains($mime, 'png') => 'png',
            str_contains($contentType, 'webp') || str_contains($mime, 'webp') => 'webp',
            str_contains($contentType, 'gif') || str_contains($mime, 'gif') => 'gif',
            str_contains($contentType, 'avif') || str_contains($mime, 'avif') => 'avif',
            default => in_array(strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)
                ? strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION))
                : 'jpg',
        };
    }

    private function imageInfo(string $body): ?array
    {
        $info = @getimagesizefromstring($body);
        if (! is_array($info)) {
            return null;
        }

        return $info;
    }

    private function isLargeEnoughImage(string $body, ?array $info = null): bool
    {
        $info ??= $this->imageInfo($body);
        if ($info === null) {
            return false;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);

        return $width >= 220
            && $height >= 220
            && max($width, $height) >= 420
            && ($width * $height) >= 90000;
    }

    private function isProductImage(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        if (! preg_match('~\.(?:jpe?g|png|webp|gif|avif)(?:$|\?)~i', $path)) {
            return false;
        }

        if (preg_match('~(?:logo|icon|sprite|placeholder|noimage|nophoto|payment|social|banner|watermark|telegram|viber|whatsapp|star|rating|loader|loading|close|cart|wishlist|compare|flag|flags|avatar)~i', $path)) {
            return false;
        }

        if (preg_match('~[-_](\d{1,3})x(\d{1,3})(?:\.|$)~', $path, $size)) {
            return (int) $size[1] >= 420 && (int) $size[2] >= 420;
        }

        return true;
    }

    private function imageQualityScore(string $url): int
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));
        $score = 0;

        if (preg_match('~/(?:media|catalog|product|products|upload|uploads)/~', $path)) {
            $score += 80;
        }

        if (preg_match('~(?:logo|icon|sprite|placeholder|noimage|nophoto|payment|social|banner|watermark|telegram|viber|whatsapp|star|rating|loader|loading|close|cart|wishlist|compare|flag|flags|avatar)~i', $path)) {
            $score -= 500;
        }

        if (str_contains($path, '/thumbnail/')) {
            $score -= 20;
        }

        if (str_contains($path, '/resize_cache/')) {
            $score -= 90;
        }

        if (preg_match('~/upload/iblock/[^/]+/[^/]+\.(?:jpe?g|png|webp|gif|avif)$~i', $path)) {
            $score += 140;
        }

        if (preg_match_all('~(?:^|[/_-])(\d{2,4})x(\d{2,4})(?:[/_\.-]|$)~', $path, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $width = (int) $match[1];
                $height = (int) $match[2];
                $score += min(500, (int) floor(($width * $height) / 2500));
                if ($width >= 800 && $height >= 800) {
                    $score += 180;
                } elseif ($width < 250 || $height < 250) {
                    $score -= 120;
                }
            }
        }

        if (preg_match('~/(?:\d{1,3})_(?:\d{1,3})(?:_\d+)?/~', $path)) {
            $score -= 160;
        }

        if (preg_match('~(?:/image/|/cache/|/resize/|/large/|/original/)~', $path)) {
            $score += 40;
        }

        return $score;
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
        $text = $this->repairMojibake($text);
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

    private function sanitizeAiHtml(string $html): string
    {
        $html = $this->repairMojibake($this->sanitizeUtf8($html));
        $html = strip_tags($html, '<p><ul><li><strong>');
        $html = preg_replace('/<(p|ul|li|strong)\b[^>]*>/iu', '<$1>', $html) ?? $html;
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;
        $html = str_replace(['> <', '</p> <p>', '</li> <li>', '</ul> <p>'], ['><', "</p>\n<p>", "</li>\n<li>", "</ul>\n<p>"], $html);

        return trim($html);
    }

    private function sanitizeUtf8Recursive(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->repairMojibake($this->sanitizeUtf8($value));
        }

        if (! is_array($value)) {
            return $value;
        }

        $clean = [];
        foreach ($value as $key => $item) {
            $cleanKey = is_string($key) ? $this->repairMojibake($this->sanitizeUtf8($key)) : $key;
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

    private function repairMojibake(string $value): string
    {
        if ($this->mojibakeScore($value) > 0) {
            $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $value);
            if (is_string($candidate)
                && mb_check_encoding($candidate, 'UTF-8')
                && $this->mojibakeScore($candidate) < $this->mojibakeScore($value)) {
                return $candidate;
            }
        }

        if (! preg_match('/(?:Р[\\x{0400}-\\x{04FF}]|С[\\x{0400}-\\x{04FF}]|Ð.|Ñ.)/u', $value)) {
            return $value;
        }

        $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $value);
        if (! is_string($candidate) || ! mb_check_encoding($candidate, 'UTF-8')) {
            return $value;
        }

        $badScore = preg_match_all('/(?:Р[\\x{0400}-\\x{04FF}]|С[\\x{0400}-\\x{04FF}]|Ð.|Ñ.)/u', $value);
        $candidateBadScore = preg_match_all('/(?:Р[\\x{0400}-\\x{04FF}]|С[\\x{0400}-\\x{04FF}]|Ð.|Ñ.)/u', $candidate);

        return $candidateBadScore < $badScore ? $candidate : $value;
    }

    private function mojibakeScore(string $value): int
    {
        $score = 0;
        $score += (int) preg_match_all('/(?:Р[’Ѓ“”•–—˜™љ›њќћџ ЎўЈ¤Ґ¦§Ё©Є«¬®Ї°±Ііґµ¶·ё№є»јЅѕї]|С[Ѓ‚ѓ„…†‡€‰Љ‹ЊЌЋЏђ‘’“”•–—˜™љ›њќћџ])+/u', $value);
        $score += (int) preg_match_all('/(?:Ð.|Ñ.|Đ.|Ă.)/u', $value);

        return $score;
    }

    private function looksLikeMojibake(string $value): bool
    {
        return $this->mojibakeScore($value) > 0;
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
        $value = $this->normalizeBooleanGlyphValue($value);
        $value = trim($value, " \t\n\r\0\x0B:;•—-");

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function normalizeBooleanGlyphValue(string $value): string
    {
        $normalized = trim($value);
        $compact = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        $yesValues = [
            '✔',
            '✔️',
            '✓',
            '✅',
            '☑',
            'âœ”',
            'âœ”ï¸',
            'âœ“',
            'âœ…',
            'ђ”',
            'ђ”пёџ',
            'ђ”пёЦ',
        ];

        $noValues = [
            '❌',
            '✘',
            '✕',
            '✖',
            '×',
            '☒',
            'âŒ',
            'âœ˜',
            'âœ•',
            'âœ–',
            'ќњ',
        ];

        if (in_array($compact, $yesValues, true)) {
            return 'да';
        }

        if (in_array($compact, $noValues, true)) {
            return 'нет';
        }

        if (preg_match('/^(?:[^\p{L}\p{N}]*)?(?:✔|✓|✅|☑)(?:[^\p{L}\p{N}]*)?$/u', $compact)) {
            return 'да';
        }

        if (preg_match('/^(?:[^\p{L}\p{N}]*)?(?:❌|✘|✕|✖|×|☒)(?:[^\p{L}\p{N}]*)?$/u', $compact)) {
            return 'нет';
        }

        return $value;
    }

    private function normalizeAttributeName(string $name): string
    {
        $name = mb_strtolower($this->cleanAttributeName($name));
        $name = str_replace('ё', 'е', $name);
        $name = preg_replace('/[^a-zа-я0-9]+/u', ' ', $name) ?? $name;
        $name = preg_replace('/\b(?:мм|см|м|м2|м3|м²|м³|мкв|мкуб|квт|вт|кг|г|л|литр|литров|час|часов|мес|месяц|месяцев|проц|percent)\b\s*$/u', '', $name) ?? $name;

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

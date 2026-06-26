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

    public function preview(string $sourceUrl): array
    {
        $sourceUrl = trim($sourceUrl);
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid source URL.');
        }

        return $this->parsePage($this->fetchHtml($sourceUrl), $sourceUrl);
    }

    public function enrichFromParsed(Product $product, string $sourceUrl, array $parsed, array $options = []): array
    {
        $sourceUrl = trim($sourceUrl);
        if (! filter_var($sourceUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Invalid source URL.');
        }

        $parsed = $this->normalizeParsedData($parsed);
        $parsed = $this->adaptParsedDataForProduct($parsed, $product, $sourceUrl);
        $updates = [];
        $stats = [
            'images_found' => count($parsed['images']),
            'images_saved' => 0,
            'images_replaced' => 0,
            'specs_found' => count($parsed['specs']),
            'specs_skipped' => 0,
            'attribute_values_saved' => 0,
            'service_found' => count($parsed['service_info']),
            'documents_found' => count($parsed['documents']),
            'video_found' => $parsed['video_url'] !== '' ? 1 : 0,
            'content_found' => $parsed['description'] !== '' ? 1 : 0,
            'short_description_found' => $parsed['short_description'] !== '' ? 1 : 0,
            'errors' => [],
        ];

        $preview = [
            'images' => array_slice($parsed['images'], 0, 3),
            'description' => Str::limit(trim(strip_tags($parsed['description'] ?: $parsed['short_description'])), 700),
            'specs' => array_slice($parsed['specs'], 0, 8),
            'service_info' => array_slice($parsed['service_info'], 0, 5),
            'documents' => array_slice($parsed['documents'], 0, 5),
            'video_url' => $parsed['video_url'],
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
                $replaceSpecs = (bool) ($options['replace_specs'] ?? false);

                if ($replaceSpecs) {
                    $sanitizedSpecs = $this->sanitizeJsonArray($parsed['specs']);
                    $stats['attribute_values_saved'] = DB::transaction(function () use ($product, $parsed): int {
                        $this->deleteExistingAttributeValues($product);

                        return $this->syncAttributeValues($product, $parsed['specs']);
                    });
                    $updates['specs'] = $sanitizedSpecs;
                } elseif ($this->productHasExistingSpecs($product)) {
                    $stats['specs_skipped'] = 1;
                } elseif ($this->decodeArray($product->specs) !== []) {
                    $stats['attribute_values_saved'] = $this->syncSpecsToAttributeValues($product);
                    $stats['specs_skipped'] = 1;
                } else {
                    $updates['specs'] = $this->sanitizeJsonArray($parsed['specs']);
                    $stats['attribute_values_saved'] = $this->syncAttributeValues($product, $parsed['specs']);
                }
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
            if (($options['update_documents'] ?? true) === true && $parsed['documents'] !== []) {
                $updates['documents'] = $this->sanitizeJsonArray($parsed['documents']);
            }

            if (($options['update_video'] ?? true) === true && $parsed['video_url'] !== '') {
                $updates['video_url'] = $parsed['video_url'];
            }
        } catch (\Throwable $e) {
            $stats['errors'][] = 'media: ' . $e->getMessage();
            Log::warning('Product source media enrichment failed', ['product_id' => $product->id, 'error' => $e->getMessage()]);
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

    public function syncSpecsToAttributeValues(Product $product, ?array $specs = null): int
    {
        $specs ??= $this->decodeArray($product->specs);

        return $this->syncAttributeValues($product, $specs);
    }

    public function filterUsableSpecs(array $specs): array
    {
        return array_values(array_filter($specs, function (array $spec): bool {
            $name = $this->cleanAttributeName((string) ($spec['key'] ?? ''));
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            $unit = (string) ($spec['unit'] ?? '');

            return $name !== ''
                && $value !== ''
                && ! $this->isTechnicalOrJunkAttribute($name, $value)
                && ! $this->isUnitOnlyAttributeValue($value, $unit);
        }));
    }

    public function normalizeSpecsForStorage(array $specs): array
    {
        return $this->normalizeParsedSpecs($specs);
    }

    public function deleteUnitOnlyAttributeValues(Product $product, bool $apply = true): int
    {
        $rows = DB::table('product_attribute_values')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_attribute_values.product_id', $product->id)
            ->get([
                'product_attribute_values.id',
                'product_attribute_values.value',
                'attributes.suffix',
            ]);

        $ids = $rows
            ->filter(fn ($row): bool => $this->isUnitOnlyAttributeValue((string) $row->value, (string) $row->suffix))
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        if (! $apply) {
            return count($ids);
        }

        return DB::table('product_attribute_values')
            ->whereIn('id', $ids)
            ->delete();
    }

    public function deleteEmptyAttributeValues(Product $product, bool $apply = true): int
    {
        $rows = DB::table('product_attribute_values')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_attribute_values.product_id', $product->id)
            ->whereNull('product_attribute_values.option_id')
            ->where('attributes.type', 'value')
            ->get([
                'product_attribute_values.id',
                'product_attribute_values.value',
            ]);

        $ids = $rows
            ->filter(fn ($row): bool => trim((string) $row->value) === '')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return 0;
        }

        if (! $apply) {
            return count($ids);
        }

        return DB::table('product_attribute_values')
            ->whereIn('id', $ids)
            ->delete();
    }

    public function repairMojibakeAttributeValues(Product $product, bool $apply = true): int
    {
        $rows = DB::table('product_attribute_values')
            ->leftJoin('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_id', $product->id)
            ->get([
                'product_attribute_values.id',
                'product_attribute_values.value',
                'product_attribute_values.attribute_id',
                'attributes.name as attribute_name',
            ]);

        $changed = 0;
        $changedAttributeIds = [];

        foreach ($rows as $row) {
            $value = (string) $row->value;
            $clean = $this->cleanAttributeValue($value);

            if ($clean !== '' && $clean !== $value) {
                $changed++;

                if ($apply) {
                    DB::table('product_attribute_values')
                        ->where('id', $row->id)
                        ->update([
                            'value' => $this->cleanDatabaseText($clean),
                            'updated_at' => now(),
                        ]);
                }
            }

            $attributeId = (int) ($row->attribute_id ?? 0);
            if ($attributeId <= 0 || isset($changedAttributeIds[$attributeId])) {
                continue;
            }

            $attributeName = (string) ($row->attribute_name ?? '');
            $cleanAttributeName = $this->cleanAttributeName($attributeName);
            if ($cleanAttributeName === '' || $cleanAttributeName === $attributeName) {
                continue;
            }

            $changedAttributeIds[$attributeId] = true;
            $changed++;

            if ($apply) {
                DB::table('attributes')
                    ->where('id', $attributeId)
                    ->update([
                        'name' => $this->cleanDatabaseText($cleanAttributeName),
                        'updated_at' => now(),
                    ]);
            }
        }

        return $changed;
    }

    public function repairLeadingUnitAttributeNames(Product $product, bool $apply = true): int
    {
        $rows = DB::table('product_attribute_values')
            ->join('attributes', 'attributes.id', '=', 'product_attribute_values.attribute_id')
            ->where('product_attribute_values.product_id', $product->id)
            ->where('attributes.name', 'like', '?%')
            ->get([
                'product_attribute_values.id',
                'product_attribute_values.attribute_id',
                'attributes.category_id',
                'attributes.name as attribute_name',
                'attributes.suffix',
            ]);

        $changed = 0;

        foreach ($rows as $row) {
            [$cleanName, $cleanUnit] = $this->cleanAttributeNameAndUnit(
                (string) $row->attribute_name,
                (string) $row->suffix
            );

            if ($cleanName === '' || $cleanName === (string) $row->attribute_name) {
                continue;
            }

            $changed++;

            if (! $apply) {
                continue;
            }

            DB::transaction(function () use ($row, $cleanName, $cleanUnit): void {
                $targetAttributeId = DB::table('attributes')
                    ->where('category_id', (int) $row->category_id)
                    ->where('name', $cleanName)
                    ->when($cleanUnit !== '', function ($query) use ($cleanUnit): void {
                        $query->where(function ($query) use ($cleanUnit): void {
                            $query->where('suffix', $cleanUnit)
                                ->orWhereNull('suffix')
                                ->orWhere('suffix', '');
                        });
                    })
                    ->orderByRaw('CASE WHEN suffix = ? THEN 0 WHEN suffix IS NULL OR suffix = "" THEN 1 ELSE 2 END', [$cleanUnit])
                    ->value('id');

                if ($targetAttributeId) {
                    DB::table('product_attribute_values')
                        ->where('id', (int) $row->id)
                        ->update([
                            'attribute_id' => (int) $targetAttributeId,
                            'updated_at' => now(),
                        ]);

                    if ($cleanUnit !== '') {
                        DB::table('attributes')
                            ->where('id', (int) $targetAttributeId)
                            ->where(function ($query): void {
                                $query->whereNull('suffix')->orWhere('suffix', '');
                            })
                            ->update([
                                'suffix' => $cleanUnit,
                                'updated_at' => now(),
                            ]);
                    }

                    if (! DB::table('product_attribute_values')->where('attribute_id', (int) $row->attribute_id)->exists()) {
                        DB::table('attributes')->where('id', (int) $row->attribute_id)->delete();
                    }

                    return;
                }

                DB::table('attributes')
                    ->where('id', (int) $row->attribute_id)
                    ->update([
                        'name' => $this->cleanDatabaseText($cleanName),
                        'suffix' => $cleanUnit !== '' ? $this->cleanDatabaseText($cleanUnit) : (string) $row->suffix,
                        'updated_at' => now(),
                    ]);
            });
        }

        return $changed;
    }

    private function productHasExistingSpecs(Product $product): bool
    {
        return $product->allAttributeValues()->exists();
    }

    private function deleteExistingAttributeValues(Product $product): void
    {
        DB::table('product_attribute_values')
            ->where('product_id', $product->id)
            ->delete();
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
            $unit = (string) ($spec['unit'] ?? '');
            [$name, $unit] = $this->cleanAttributeNameAndUnit((string) ($spec['key'] ?? ''), $unit);
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            if ($name === '' || $value === '' || $this->isTechnicalOrJunkAttribute($name, $value) || $this->isUnitOnlyAttributeValue($value, $unit)) {
                continue;
            }

            $targetAttributeNames[] = $this->normalizeAttributeName($name);
        }

        $this->deleteExistingAttributeValuesForNames($product, $categoryId, array_values(array_unique(array_filter($targetAttributeNames))));

        $saved = 0;
        foreach ($specs as $spec) {
            $unit = (string) ($spec['unit'] ?? '');
            [$name, $unit] = $this->cleanAttributeNameAndUnit((string) ($spec['key'] ?? ''), $unit);
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            if ($name === '' || $value === '' || $this->isTechnicalOrJunkAttribute($name, $value) || $this->isUnitOnlyAttributeValue($value, $unit)) {
                continue;
            }

            [$value, $unit] = $this->splitValueAndUnit($value, $unit);
            $attributeId = $this->ensureAttribute($categoryId, $name, $unit);
            if ($attributeId <= 0) {
                continue;
            }

            $attributeSuffix = (string) DB::table('attributes')->where('id', $attributeId)->value('suffix');
            if ($attributeSuffix !== '') {
                $value = $this->stripTrailingUnit($value, $attributeSuffix);
            }

            DB::table('product_attribute_values')->updateOrInsert(
                [
                    'product_id' => $product->id,
                    'attribute_id' => $attributeId,
                ],
                [
                    'option_id' => null,
                    'is_checked' => null,
                    'value' => $this->cleanDatabaseText($value),
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
        $name = $this->cleanDatabaseText($name);
        $unit = $this->cleanDatabaseText($unit);

        if ($name === '') {
            return 0;
        }

        $normalized = $this->normalizeAttributeName($name);
        $attributes = DB::table('attributes')
            ->where('category_id', $categoryId)
            ->get(['id', 'name', 'suffix']);

        $existing = $attributes->first(fn ($attribute): bool => (string) $attribute->name === $name)
            ?: $attributes->first(fn ($attribute): bool => $this->normalizeAttributeName((string) $attribute->name) === $normalized
                && ! str_starts_with(trim((string) $attribute->name), '?'));

        $dirtyExisting = $attributes->first(fn ($attribute): bool => $this->normalizeAttributeName((string) $attribute->name) === $normalized
            && str_starts_with(trim((string) $attribute->name), '?'));

        if ($existing) {
            if ($unit !== '' && trim((string) $existing->suffix) === '') {
                DB::table('attributes')->where('id', $existing->id)->update([
                    'suffix' => $unit,
                    'updated_at' => now(),
                ]);
            }

            return (int) $existing->id;
        }

        if ($dirtyExisting) {
            DB::table('attributes')->where('id', $dirtyExisting->id)->update([
                'name' => $name,
                'suffix' => $unit !== '' ? $unit : (string) $dirtyExisting->suffix,
                'updated_at' => now(),
            ]);

            return (int) $dirtyExisting->id;
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
        $response = Http::withHeaders($this->sourcePageRequestHeaders($url))
            ->connectTimeout(10)
            ->timeout(45)
            ->retry(2, 750)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('Source page returned HTTP ' . $response->status());
        }

        return $this->normalizeHtmlEncoding((string) $response->body(), (string) $response->header('Content-Type'));
    }

    /**
     * @return array<string, string>
     */
    private function sourcePageRequestHeaders(string $url): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Upgrade-Insecure-Requests' => '1',
        ];

        if (str_contains($host, 'ozon.')) {
            $headers += [
                'Referer' => 'https://www.ozon.ru/',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'same-origin',
                'Sec-Fetch-User' => '?1',
            ];
        }

        return $headers;
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
        $parsed = [
            'title' => $this->extractTitle($html),
            'description' => $this->extractDescription($html),
            'short_description' => $this->extractShortDescription($html),
            'specs' => $this->extractSpecs($html),
            'service_info' => $this->extractServiceInfo($html),
            'images' => $this->extractImages($html, $url),
            'documents' => $this->extractDocuments($html, $url),
            'video_url' => $this->extractVideoUrl($html, $url),
        ];

        if ($this->isOzonUrl($url)) {
            $ozon = $this->extractOzonData($html, $url);
            $parsed['description'] = $parsed['description'] ?: $ozon['description'];
            $parsed['short_description'] = $parsed['short_description'] ?: $ozon['short_description'];
            $parsed['specs'] = array_values(array_merge($parsed['specs'], $ozon['specs']));
            $parsed['images'] = array_values(array_unique(array_merge($parsed['images'], $ozon['images'])));
        }

        if ($this->isSanteh24Url($url)) {
            $santeh24 = $this->extractSanteh24Data($html, $url);
            if ($santeh24['description'] !== '') {
                $parsed['description'] = $santeh24['description'];
                $parsed['short_description'] = $santeh24['description'];
            }
            if ($santeh24['specs'] !== []) {
                $parsed['specs'] = $santeh24['specs'];
            }
        }

        if ($this->isGreolitUrl($url)) {
            $greolit = $this->extractGreolitData($html);
            if ($greolit['specs'] !== []) {
                $parsed['specs'] = $greolit['specs'];
            }
            if ($greolit['images'] !== []) {
                $parsed['images'] = array_values(array_unique(array_merge($greolit['images'], $parsed['images'])));
            }
        }

        return $this->normalizeParsedData($parsed);
    }

    private function normalizeParsedData(array $parsed): array
    {
        return [
            'title' => $this->cleanText((string) ($parsed['title'] ?? '')),
            'description' => $this->cleanText((string) ($parsed['description'] ?? '')),
            'short_description' => $this->cleanText((string) ($parsed['short_description'] ?? '')),
            'specs' => $this->sanitizeJsonArray($this->normalizeParsedSpecs((array) ($parsed['specs'] ?? []))),
            'service_info' => $this->sanitizeJsonArray((array) ($parsed['service_info'] ?? [])),
            'documents' => array_values(array_slice(array_filter(array_map(function ($document): array {
                $document = is_array($document) ? $document : [];
                $url = filter_var((string) ($document['url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $document['url'] : '';
                $label = $this->cleanText((string) ($document['label'] ?? ''));

                return $url !== '' ? ['label' => $label !== '' ? $label : basename((string) parse_url($url, PHP_URL_PATH)), 'url' => $url] : [];
            }, (array) ($parsed['documents'] ?? []))), 0, 12)),
            'video_url' => filter_var((string) ($parsed['video_url'] ?? ''), FILTER_VALIDATE_URL) ? (string) $parsed['video_url'] : '',
            'images' => array_values(array_slice(array_filter(array_map(
                fn ($url) => filter_var($url, FILTER_VALIDATE_URL) ? (string) $url : '',
                (array) ($parsed['images'] ?? [])
            )), 0, 4)),
        ];
    }

    private function adaptParsedDataForProduct(array $parsed, Product $product, string $sourceUrl): array
    {
        if (! $this->isGreolitUrl($sourceUrl)) {
            return $parsed;
        }

        $power = $this->extractPowerFromProductName((string) $product->name);
        if ($power !== '') {
            foreach ($parsed['specs'] as &$spec) {
                $key = mb_strtolower((string) ($spec['key'] ?? ''));
                if (str_contains($key, 'мощн')) {
                    $spec['value'] = $power;
                    $spec['unit'] = '';
                    break;
                }
            }
            unset($spec);
        }

        $name = mb_strtolower((string) $product->name);
        if (str_contains($name, 'с автоматик')) {
            $parsed['specs'][] = ['key' => 'Автоматика', 'value' => 'есть', 'unit' => ''];
        } elseif (str_contains($name, 'без автоматик')) {
            $parsed['specs'][] = ['key' => 'Автоматика', 'value' => 'нет', 'unit' => ''];
        }

        $parsed['specs'] = $this->normalizeParsedSpecs($parsed['specs']);

        return $parsed;
    }

    private function extractPowerFromProductName(string $name): string
    {
        if (preg_match('/\b(\d{2,3})\s*(?:квт|kw|kvt)\b/iu', $name, $match)) {
            return (int) $match[1] . ' кВт';
        }

        return '';
    }

    private function normalizeParsedSpecs(array $specs): array
    {
        return array_values(array_filter(array_map(function ($spec): array {
            $spec = is_array($spec) ? $spec : [];
            $unit = (string) ($spec['unit'] ?? '');
            [$key, $unit] = $this->cleanAttributeNameAndUnit((string) ($spec['key'] ?? $spec['name'] ?? ''), $unit);
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));

            if ($key === '' || $value === '' || $this->isTechnicalOrJunkAttribute($key, $value)) {
                return [];
            }

            return [
                'key' => $key,
                'value' => $value,
                'unit' => $this->cleanText($unit),
            ];
        }, $specs)));
    }

    private function isOzonUrl(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'ozon.');
    }

    private function isSanteh24Url(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'santeh24.by');
    }

    private function isGreolitUrl(string $url): bool
    {
        return str_contains((string) parse_url($url, PHP_URL_HOST), 'greolit.by');
    }

    /**
     * @return array{specs: array<int, array<string, string>>, images: array<int, string>}
     */
    private function extractGreolitData(string $html): array
    {
        $specs = [];
        $images = [];

        if (! preg_match('/data-product_variations=["\']([^"\']+)["\']/iu', $html, $match)) {
            return ['specs' => [], 'images' => []];
        }

        $json = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $variations = json_decode($json, true);
        if (! is_array($variations)) {
            return ['specs' => [], 'images' => []];
        }

        $powers = [];
        foreach ($variations as $variation) {
            if (! is_array($variation)) {
                continue;
            }

            $power = (string) data_get($variation, 'attributes.attribute_pa_moshhnost', '');
            $power = trim(str_replace(['-kvt', '-'], [' кВт', ' '], $power));
            if ($power !== '') {
                $powers[] = $power;
            }

            foreach (['image.full_src', 'image.url', 'image.src'] as $path) {
                $image = (string) data_get($variation, $path, '');
                if (filter_var($image, FILTER_VALIDATE_URL)) {
                    $images[] = $image;
                }
            }
        }

        $powers = array_values(array_unique($powers));
        if ($powers !== []) {
            $specs[] = [
                'key' => 'Мощность',
                'value' => implode(', ', $powers),
                'unit' => '',
            ];
        }

        return [
            'specs' => $specs,
            'images' => array_values(array_unique($images)),
        ];
    }

    private function extractMetaDescription(string $html): string
    {
        foreach ([
            '~<meta[^>]+name=["\']description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
            '~<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
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

    /**
     * @return array{description: string, specs: array<int, array<string, string>>}
     */
    private function extractSanteh24Data(string $html, string $url): array
    {
        $data = [
            'description' => $this->extractMetaDescription($html),
            'specs' => [],
        ];

        if (preg_match('~<meta[^>]+property=["\']og:description["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu', $html, $match)) {
            $description = $this->cleanText(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($description) >= 25) {
                $data['description'] = $description;
            }
        }

        $listingHtml = $this->fetchSanteh24ListingHtml($url);
        if ($listingHtml === '') {
            return $data;
        }

        $productId = $this->santeh24ProductId($url);
        if ($productId === '') {
            return $data;
        }

        $block = $this->santeh24ProductBlock($listingHtml, $productId);
        if ($block === '') {
            return $data;
        }

        if (preg_match('~<div[^>]+class=["\'][^"\']*preview_text[^"\']*["\'][^>]*>([\s\S]*?)</div>~iu', $block, $match)) {
            $description = $this->cleanText(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($description) >= 25) {
                $data['description'] = $description;
            }
        }

        $specs = [];
        if (preg_match_all('~<tr>\s*<td>\s*<span[^>]+class=["\']char_name["\'][^>]*>\s*(.*?)\s*</span>\s*</td>\s*<td>\s*<span[^>]*>\s*(.*?)\s*</span>~isu', $block, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $this->addSpec(
                    $specs,
                    html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')
                );
            }
        }

        $data['specs'] = array_values($specs);

        return $data;
    }

    private function fetchSanteh24ListingHtml(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (! preg_match('~^(.*/)\d+/?$~', $path, $match)) {
            return '';
        }

        $listingUrl = (parse_url($url, PHP_URL_SCHEME) ?: 'https') . '://' . parse_url($url, PHP_URL_HOST) . $match[1];

        try {
            return $this->fetchHtml($listingUrl);
        } catch (\Throwable) {
            return '';
        }
    }

    private function santeh24ProductId(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('~/(\d+)/?$~', $path, $match) ? $match[1] : '';
    }

    private function santeh24ProductBlock(string $html, string $productId): string
    {
        if (! preg_match('~id=["\'][^"\']*_' . preg_quote($productId, '~') . '["\'][\s\S]*?(?=<div[^>]+class=["\'][^"\']*list_item_wrapp|\z)~iu', $html, $match)) {
            return '';
        }

        return $match[0];
    }

    /**
     * @return array{description: string, short_description: string, specs: array<int, array<string, string>>, images: array<int, string>}
     */
    private function extractOzonData(string $html, string $pageUrl): array
    {
        $data = [
            'description' => '',
            'short_description' => '',
            'specs' => [],
            'images' => [],
        ];

        foreach ($this->jsonObjectsFromHtml($html) as $json) {
            if ($data['description'] === '') {
                $data['description'] = $this->firstStringByKeys($json, ['description', 'seoDescription', 'annotation']);
            }

            if ($data['short_description'] === '') {
                $data['short_description'] = $this->firstStringByKeys($json, ['name', 'title']);
            }

            $data['specs'] = array_merge($data['specs'], $this->specsFromNestedJson($json));
            $data['images'] = array_merge($data['images'], $this->imagesFromNestedJson($json, $pageUrl));
        }

        foreach ($this->ozonImageUrlsFromHtml($html) as $url) {
            $data['images'][] = $this->absoluteUrl($url, $pageUrl);
        }

        $data['specs'] = array_slice($this->uniqueSpecs($data['specs']), 0, 80);
        $data['images'] = array_values(array_unique(array_filter($data['images'])));

        return $data;
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonObjectsFromHtml(string $html): array
    {
        $objects = [];

        if (preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>~iu', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                if (is_array($decoded)) {
                    $objects[] = $decoded;
                }
            }
        }

        if (preg_match_all('~<script[^>]*>([\s\S]*?)</script>~iu', $html, $matches)) {
            foreach ($matches[1] as $script) {
                if (! preg_match('/(?:webCharacteristics|characteristics|attributes|shortCharacteristics|ir\.ozone\.ru|cdn\d*\.ozone\.ru)/iu', $script)) {
                    continue;
                }

                $decoded = html_entity_decode(str_replace(['\\"', '\\/'], ['"', '/'], $script), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $json = json_decode(trim($decoded), true);
                if (is_array($json)) {
                    $objects[] = $json;
                    continue;
                }

                foreach ($this->jsonFragmentsFromText($decoded) as $fragment) {
                    $objects[] = $fragment;
                }
            }
        }

        return $objects;
    }

    /**
     * @return array<int, mixed>
     */
    private function jsonFragmentsFromText(string $text): array
    {
        $fragments = [];
        if (! preg_match_all('/\{(?=[^{}]*(?:webCharacteristics|characteristics|attributes|shortCharacteristics|description|image))[\s\S]{0,120000}\}/iu', $text, $matches)) {
            return [];
        }

        foreach ($matches[0] as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                $fragments[] = $decoded;
            }
        }

        return $fragments;
    }

    /**
     * @param array<int, array<string, string>> $specs
     * @return array<int, array<string, string>>
     */
    private function uniqueSpecs(array $specs): array
    {
        $unique = [];
        foreach ($specs as $spec) {
            $key = $this->cleanAttributeName((string) ($spec['key'] ?? ''));
            $value = $this->cleanAttributeValue((string) ($spec['value'] ?? ''));
            if ($key === '' || $value === '') {
                continue;
            }

            $unique[mb_strtolower($key)] = ['key' => $key, 'value' => $value, 'unit' => ''];
        }

        return array_values($unique);
    }

    private function firstStringByKeys(mixed $value, array $keys): string
    {
        if (! is_array($value)) {
            return '';
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array($key, $keys, true) && is_string($item) && mb_strlen($this->cleanText($item)) >= 10) {
                return $this->cleanText($item);
            }

            $nested = $this->firstStringByKeys($item, $keys);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function specsFromNestedJson(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $specs = [];
        $key = $this->stringFromAny($value['key'] ?? $value['name'] ?? $value['title'] ?? $value['label'] ?? null);
        $specValue = $this->stringFromAny($value['value'] ?? $value['values'] ?? $value['text'] ?? null);

        if ($key !== '' && $specValue !== '' && $this->looksLikeSpecPair($key, $specValue)) {
            $specs[] = ['key' => $key, 'value' => $specValue, 'unit' => ''];
        }

        foreach (['attributes', 'characteristics', 'webCharacteristics', 'shortCharacteristics', 'properties', 'items'] as $listKey) {
            if (isset($value[$listKey]) && is_array($value[$listKey])) {
                foreach ($value[$listKey] as $item) {
                    $specs = array_merge($specs, $this->specsFromNestedJson($item));
                }
            }
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                $specs = array_merge($specs, $this->specsFromNestedJson($item));
            }
        }

        return $specs;
    }

    private function stringFromAny(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return $this->cleanText((string) $value);
        }

        if (is_array($value)) {
            $parts = [];
            foreach ($value as $item) {
                $part = $this->stringFromAny($item);
                if ($part !== '') {
                    $parts[] = $part;
                }
            }

            return $this->cleanText(implode(', ', array_unique($parts)));
        }

        return '';
    }

    private function looksLikeSpecPair(string $key, string $value): bool
    {
        return mb_strlen($key) >= 2
            && mb_strlen($key) <= 120
            && mb_strlen($value) <= 240
            && ! $this->isTechnicalOrJunkAttribute($key, $value)
            && ! preg_match('/^(url|link|image|images|name|title|description|sku|id|price)$/iu', $key);
    }

    /**
     * @return array<int, string>
     */
    private function imagesFromNestedJson(mixed $value, string $pageUrl): array
    {
        if (is_string($value)) {
            return $this->looksLikeOzonImageUrl($value) ? [$this->absoluteUrl($value, $pageUrl)] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $urls = [];
        foreach ($value as $item) {
            $urls = array_merge($urls, $this->imagesFromNestedJson($item, $pageUrl));
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array<int, string>
     */
    private function ozonImageUrlsFromHtml(string $html): array
    {
        $decoded = html_entity_decode(str_replace('\/', '/', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (! preg_match_all('~https?://(?:ir|cdn\d*)\.ozone\.ru/s3/[^"\'\s<>\\\\]+~iu', $decoded, $matches)) {
            return [];
        }

        return array_values(array_filter(array_unique($matches[0]), fn (string $url): bool => $this->looksLikeOzonImageUrl($url)));
    }

    private function looksLikeOzonImageUrl(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = (string) parse_url($url, PHP_URL_PATH);

        return preg_match('/(?:^|\.)ozone\.ru$/iu', $host)
            && str_contains($path, '/s3/')
            && ! preg_match('~/(?:icons?|flags?|sprite|logo)/~iu', $path);
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

    private function extractTitle(string $html): string
    {
        foreach ([
            '~<h1[^>]*>([\s\S]*?)</h1>~iu',
            '~<meta[^>]+property=["\']og:title["\'][^>]+content=["\'](.*?)["\'][^>]*>~iu',
            '~<title[^>]*>([\s\S]*?)</title>~iu',
        ] as $pattern) {
            if (preg_match($pattern, $html, $match)) {
                $title = $this->cleanText(html_entity_decode(strip_tags($match[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $title = preg_replace('/\s*[|–-]\s*(?:teplodvor\.by|RN-Profi|РН-Профи).*$/iu', '', $title) ?? $title;
                if (mb_strlen($title) >= 3) {
                    return trim($title);
                }
            }
        }

        return '';
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

        $this->addSpecsFromAsproProperties($xpath, $specs);
        $this->addSpecsFromCharacteristicLists($xpath, $specs);
        $this->addSpecsFromDescriptionText($html, $specs);
        $this->addSpecsFromMetaDescription($html, $specs);

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
        if ($dl instanceof \DOMElement) {
            $xpath = new \DOMXPath($dl->ownerDocument);
            foreach ($xpath->query('.//*[dt and dd]', $dl) ?: [] as $item) {
                $dt = $xpath->query('./dt', $item)?->item(0);
                $dd = $xpath->query('./dd', $item)?->item(0);
                if ($dt && $dd) {
                    $this->addSpec($specs, $dt->textContent, $dd->textContent);
                }
            }
        }

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

    private function addSpecsFromAsproProperties(\DOMXPath $xpath, array &$specs): void
    {
        $items = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " properties__item ")]');
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " js-prop-title ")]', $item)?->item(0);
            $valueNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " js-prop-value ")]', $item)?->item(0);

            if (! $titleNode || ! $valueNode) {
                continue;
            }

            $this->addSpec($specs, $titleNode->textContent, $valueNode->textContent);
        }
    }

    private function addSpecsFromCharacteristicLists(\DOMXPath $xpath, array &$specs): void
    {
        $lists = $xpath->query('//dl[contains(concat(" ", normalize-space(@class), " "), " characteristics ")]');
        if ($lists === false) {
            return;
        }

        foreach ($lists as $list) {
            $this->addSpecsFromDefinitionList($specs, $list);
        }
    }

    private function addSpecsFromDescriptionText(string $html, array &$specs): void
    {
        $text = html_entity_decode(
            preg_replace('~<br\s*/?>~iu', "\n", $html) ?? $html,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $text = strip_tags($text);

        if (! preg_match('/Характеристики\s*:\s*(.+?)(?:\n\s*\n|Документация|Отзывы|С этим товаром|$)/isu', $text, $match)) {
            $this->addSpecsFromColonParagraphs($html, $specs);

            return;
        }

        $this->addSpecsFromColonLines($match[1], $specs);
    }

    private function addSpecsFromColonParagraphs(string $html, array &$specs): void
    {
        if (! preg_match_all('~<p\b[^>]*>([\s\S]*?<br\s*/?>[\s\S]*?)</p>~iu', $html, $matches)) {
            return;
        }

        foreach ($matches[1] as $block) {
            if (substr_count($block, ':') < 3 || ! preg_match('/(?:Kvs|PN|DN|VM\d|&deg;|°)/iu', $block)) {
                continue;
            }

            $text = html_entity_decode(
                preg_replace('~<br\s*/?>~iu', "\n", $block) ?? $block,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );

            $this->addSpecsFromColonLines(strip_tags($text), $specs);
        }
    }

    private function addSpecsFromColonLines(string $text, array &$specs): void
    {
        $lines = preg_split('/\R+/u', trim($text)) ?: [];
        foreach (array_slice($lines, 0, 40) as $line) {
            $line = trim($line);
            if (! preg_match('/^([^:]{2,90}):\s*(.{1,180})$/u', $line, $parts)) {
                continue;
            }

            $this->addSpec($specs, $parts[1], $parts[2]);
        }
    }

    private function addSpecsFromMetaDescription(string $html, array &$specs): void
    {
        $description = $this->extractMetaDescription($html);
        if ($description === '' || substr_count($description, ':') < 2) {
            return;
        }

        if (! preg_match_all('/([А-ЯЁA-Z][А-ЯЁа-яёA-Za-z0-9 ()\\-.,\\/]{1,70}):\\s*([^:]{1,120}?)(?=\\s+[А-ЯЁA-Z][А-ЯЁа-яёA-Za-z0-9 ()\\-.,\\/]{1,70}:|$)/u', $description, $matches, PREG_SET_ORDER)) {
            return;
        }

        foreach (array_slice($matches, 0, 30) as $match) {
            $this->addSpec($specs, $match[1], $match[2]);
        }
    }

    private function extractDocuments(string $html, string $pageUrl): array
    {
        $documents = [];

        if (! preg_match_all('~<a\b[^>]*href=["\']([^"\']+\.(?:pdf|docx?|xlsx?|pptx?|zip|rar))(?:\?[^"\']*)?["\'][^>]*>([\s\S]*?)</a>~iu', $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($matches as $match) {
            $url = $this->absoluteUrl($match[1], $pageUrl);
            $path = (string) parse_url($url, PHP_URL_PATH);
            if ($url === '' || str_contains($path, '/bitrix/') || str_contains($path, '/upload/resize_cache/')) {
                continue;
            }

            $label = $this->cleanText(strip_tags($match[2]));
            if ($label === '' || mb_strlen($label) < 2) {
                $label = basename($path);
            }

            $documents[$url] = ['label' => Str::limit($label, 120, ''), 'url' => $url];
        }

        return array_values(array_slice($documents, 0, 12));
    }

    private function extractVideoUrl(string $html, string $pageUrl): string
    {
        $decoded = html_entity_decode(str_replace('\/', '/', $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ([
            '~<iframe\b[^>]*src=["\']([^"\']+)["\']~iu',
            '~<video\b[^>]*src=["\']([^"\']+)["\']~iu',
            '~<source\b[^>]*src=["\']([^"\']+\.(?:mp4|webm|mov)(?:\?[^"\']*)?)["\']~iu',
            '~https?://(?:www\.)?(?:youtube\.com|youtu\.be|rutube\.ru|vk\.com|vimeo\.com)/[^"\'\s<>]+~iu',
        ] as $pattern) {
            if (! preg_match_all($pattern, $decoded, $matches)) {
                continue;
            }

            foreach ($matches[1] ?? $matches[0] as $url) {
                $url = $this->absoluteUrl((string) $url, $pageUrl);
                if (filter_var($url, FILTER_VALIDATE_URL) && ! str_contains($url, '/bitrix/')) {
                    return $url;
                }
            }
        }

        return '';
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
        $relevantImages = array_values(array_filter($images, fn (string $url): bool => $this->imagePageRelevanceScore($url, $pageUrl) > 0));
        if ($relevantImages !== []) {
            $images = $relevantImages;
        }

        usort($images, fn (string $left, string $right): int => $this->imageScore($right, $pageUrl) <=> $this->imageScore($left, $pageUrl));

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
            $url = $this->canonicalWordPressImageUrl($url);
            $expanded[] = $url;

            foreach ($this->highResolutionImageVariants($url) as $variant) {
                $expanded[] = $variant;
            }
        }

        return array_values(array_filter(array_unique($expanded), fn ($url) => $this->isProductImage($url)));
    }

    private function canonicalWordPressImageUrl(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === 'teplo.by' && str_starts_with($path, '/uploads/')) {
            $url = str_replace('://teplo.by/uploads/', '://teplo.by/wp-content/uploads/', $url);
            $path = (string) parse_url($url, PHP_URL_PATH);
        }

        if (! str_contains($path, '/wp-content/uploads/')) {
            return $url;
        }

        return preg_replace('~-\d{2,4}x\d{2,4}(?=\.(?:jpe?g|png|webp|gif|avif)(?:\.webp)?(?:$|\?))~i', '', $url) ?? $url;
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
            $variants[] = preg_replace('~-\d{2,4}x\d{2,4}(?=\.(?:jpe?g|png|webp|gif|avif)(?:\.webp)?(?:$|\?))~i', '', $url) ?? $url;
            $variants[] = preg_replace('~(?<=/)\d{2,4}x\d{2,4}(?=/)~', '1000x1000', $url) ?? $url;
            $variants[] = preg_replace('~(?<=[_-])\d{2,4}x\d{2,4}(?=[_\.-])~', '1000x1000', $url) ?? $url;
        }

        if (preg_match('~/upload/resize_cache/iblock/([^/]+)/[^/]+/(.+)$~i', $path, $match)) {
            $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
            $host = parse_url($url, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $variants[] = $scheme . '://' . $host . '/upload/iblock/' . $match[1] . '/' . $match[2];
            }
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

        foreach ($this->ozonImageUrlsFromHtml($decoded) as $url) {
            $urls[] = $url;
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
        $imageContextUrl = $sourceUrl ?: (string) ($candidateUrls[0] ?? '');
        usort($candidateUrls, fn (string $left, string $right): int => $this->imageScore($right, $imageContextUrl) <=> $this->imageScore($left, $imageContextUrl));

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

        if ($this->looksLikeOzonImageUrl($url)) {
            return true;
        }

        if (! preg_match('~\.(?:jpe?g|png|webp|gif|avif)(?:$|\?)~i', $path)) {
            return false;
        }

        if (preg_match('~(?:logo|icon|sprite|placeholder|noimage|nophoto|payment|social|banner|watermark|telegram|viber|whatsapp|star|rating|loader|loading|close|cart|wishlist|compare|flag|flags|avatar|rass?roch|halva|karta-pokupok)~i', $path)) {
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

        if (preg_match('~(?:logo|icon|sprite|placeholder|noimage|nophoto|payment|social|banner|watermark|telegram|viber|whatsapp|star|rating|loader|loading|close|cart|wishlist|compare|flag|flags|avatar|rass?roch|halva|karta-pokupok)~i', $path)) {
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

    private function imageScore(string $url, string $pageUrl): int
    {
        return $this->imageQualityScore($url) + $this->imagePageRelevanceScore($url, $pageUrl);
    }

    private function imagePageRelevanceScore(string $url, string $pageUrl): int
    {
        $imagePath = Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
        $pageSlug = Str::slug(basename(trim((string) parse_url($pageUrl, PHP_URL_PATH), '/')));

        if ($imagePath === '' || $pageSlug === '') {
            return 0;
        }

        $score = 0;
        foreach (array_unique(explode('-', $pageSlug)) as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }

            if (str_contains($imagePath, $token)) {
                $score += 1000;
            }
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
        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, Windows-1251, CP1251, ISO-8859-1');
            if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
                $value = $converted;
            }
        }

        return $this->cleanDatabaseText($value);
    }

    private function cleanDatabaseText(string $value): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1251, CP1251, ISO-8859-1, UTF-8');
            $value = is_string($converted) ? $converted : '';
        }

        $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($cleaned)) {
            $value = $cleaned;
        }

        $value = str_replace("\u{FFFD}", '', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim($value);
    }

    private function repairMojibake(string $value): string
    {
        if (strlen($value) > 20000) {
            return $value;
        }

        $best = $value;
        $bestScore = $this->mojibakeScore($value);
        $originalScore = $bestScore;

        foreach (['Windows-1252', 'CP1250', 'ISO-8859-1'] as $wrongEncoding) {
            try {
                $candidate = @mb_convert_encoding($value, $wrongEncoding, 'UTF-8');
            } catch (\Throwable) {
                continue;
            }
            if (! is_string($candidate) || ! mb_check_encoding($candidate, 'UTF-8')) {
                continue;
            }

            $score = $this->mojibakeScore($candidate);
            if ($score < $bestScore) {
                $best = $candidate;
                $bestScore = $score;
            }
        }

        if ($bestScore < $originalScore) {
            return $best;
        }

        if ($originalScore > 0) {
            $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $value);
            if (is_string($candidate)
                && mb_check_encoding($candidate, 'UTF-8')
                && $this->mojibakeScore($candidate) < $originalScore) {
                return $candidate;
            }
        }

        if (! preg_match('/(?:Ð.|Ñ.|Đ.|Ă.)/u', $value)) {
            return $value;
        }

        $candidate = @iconv('UTF-8', 'Windows-1251//IGNORE', $value);
        if (! is_string($candidate) || ! mb_check_encoding($candidate, 'UTF-8')) {
            return $value;
        }

        $badScore = preg_match_all('/(?:Ð.|Ñ.|Đ.|Ă.)/u', $value);
        $candidateBadScore = preg_match_all('/(?:Ð.|Ñ.|Đ.|Ă.)/u', $candidate);

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
        [$name] = $this->cleanAttributeNameAndUnit($name);

        return $name;
    }

    private function cleanAttributeNameAndUnit(string $name, string $unit = ''): array
    {
        $name = $this->cleanText($name);
        $unit = $this->cleanText($unit);
        [$name, $unit] = $this->stripLeadingUnitPrefixFromAttributeName($name, $unit);
        $name = preg_replace('/^[\s:;•—-]+|[\s:;•—-]+$/u', '', $name) ?? $name;

        return [trim(preg_replace('/\s+/u', ' ', $name) ?? $name), $unit];
    }

    private function stripLeadingUnitPrefixFromAttributeName(string $name, string $unit = ''): array
    {
        $knownUnits = [
            'кВт', 'Вт', 'В', 'атм', 'бар', 'мм', 'см', 'м', 'кг', 'г', 'л', 'шт',
            'лет', 'мес', 'мин', 'сек', '°C', '°С', 'C', '%', 'м²', 'м³', 'м2', 'м3',
            'дюйм', 'дюйма', 'дюймов', 'kw', 'kW', 'w', 'v', 'mm', 'cm', 'm', 'kg', 'g', 'l', 'pcs',
        ];

        usort($knownUnits, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $pattern = implode('|', array_map(fn (string $value): string => preg_quote($value, '/'), $knownUnits));

        if (preg_match('/^\?+\s*(' . $pattern . ')\s*(?=\p{Lu})/u', $name, $match) === 1) {
            $detectedUnit = $match[1];
            $name = preg_replace('/^\?+\s*' . preg_quote($detectedUnit, '/') . '\s*/u', '', $name, 1) ?? $name;

            if (trim($unit) === '') {
                $unit = $detectedUnit;
            }
        } else {
            $name = preg_replace('/^\?+\s*/u', '', $name) ?? $name;
        }

        return [$name, $unit];
    }

    private function cleanAttributeValue(string $value): string
    {
        $value = $this->cleanText($value);
        $value = $this->normalizeBooleanGlyphValue($value);
        $value = preg_replace('/^[\s:;•—-]+|[\s:;•—-]+$/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
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

        if (preg_match('/^(?:с|c)\/?п\b/u', $normalizedName) || str_contains($normalizedName, 'айдаровское')) {
            return true;
        }

        return str_contains($normalizedValue, 'javascript:')
            || str_contains($normalizedValue, 'cookie')
            || mb_strlen($normalizedValue) > 240;
    }

    private function isUnitOnlyAttributeValue(string $value, string $unit = ''): bool
    {
        $value = trim($this->cleanDatabaseText($this->cleanText($value)));
        $unit = trim($this->cleanDatabaseText($this->cleanText($unit)));

        if ($value === '') {
            return true;
        }

        $normalizedValue = $this->normalizeUnitToken($value);
        $normalizedUnit = $this->normalizeUnitToken($unit);

        if ($normalizedUnit !== '' && $normalizedValue === $normalizedUnit) {
            return true;
        }

        $tokens = preg_split('/\s+/u', $normalizedValue, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return true;
        }

        $knownUnits = [
            'bar', 'c', 'cm', 'g', 'kg', 'kw', 'kvt', 'l', 'm', 'm2', 'm3', 'mm', 'mpa',
            'pa', 'pcs', 'percent', 'v', 'w',
            'бар', 'в', 'вт', 'г', 'дюйм', 'квт', 'кг', 'л', 'литр', 'м', 'м2', 'м3',
            'мес', 'месяц', 'мин', 'мм', 'мпа', 'па', 'см', 'час', 'шт',
        ];

        foreach ($tokens as $token) {
            if ($token === '' || in_array($token, $knownUnits, true)) {
                continue;
            }

            return false;
        }

        return true;
    }

    private function normalizeUnitToken(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['°', '℃', '²', '³', 'кв.', 'кв '], ['', 'c', '2', '3', '', ''], $value);
        $value = str_replace(['квт', 'кВт', 'kwt', 'watt', 'литров', 'литра', 'месяцев', 'месяца', 'дюйма', 'дюймов', 'штук'], ['квт', 'квт', 'kvt', 'w', 'литр', 'литр', 'месяц', 'месяц', 'дюйм', 'дюйм', 'шт'], $value);
        $value = preg_replace('/[.,;:(){}\[\]\/\\\\|]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function splitValueAndUnit(string $value, string $fallbackUnit = ''): array
    {
        $unit = trim($fallbackUnit);

        if ($unit !== '') {
            $value = $this->stripTrailingUnit($value, $unit);
        }

        if ($unit === '' && preg_match('/^\s*([0-9]+(?:[,.][0-9]+)?)\s*(kw|w|watt|квт|вт|mm|cm|мм|см|м|l|л|kg|кг|g|г|m2|м2|м²|%|°c|c)\s*$/iu', $value, $match)) {
            $value = str_replace(',', '.', $match[1]);
            $unit = $match[2];
        }

        return [$value, $unit];
    }

    private function stripTrailingUnit(string $value, string $unit): string
    {
        $value = trim($this->cleanDatabaseText($this->cleanText($value)));
        $unit = trim($this->cleanDatabaseText($this->cleanText($unit)));

        if ($value === '' || $unit === '') {
            return $value;
        }

        $quotedUnit = preg_quote($unit, '/');
        $candidate = preg_replace('/(?:^|[\s\x{00A0}])' . $quotedUnit . '\s*$/u', '', $value);

        if (! is_string($candidate)) {
            return $value;
        }

        $candidate = trim($candidate);

        if ($candidate === '' || $candidate === $value || $this->isUnitOnlyAttributeValue($candidate, $unit)) {
            return $value;
        }

        return $candidate;
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

<?php

namespace App\Services\Integrations;

use App\Models\IntegrationCategory;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Throwable;

class CommerceMlCatalogImporter
{
    /** @var array<string, true> */
    private array $createdExternalIds = [];

    /** @var array<string, true> */
    private array $updatedExternalIds = [];

    /** @var Collection<string, int> */
    private Collection $categoryIds;

    /** @var Collection<int, Product> */
    private Collection $products;

    /** @var array<string, array<int, int>> */
    private array $skuIndex = [];

    /** @var array<string, array<int, int>> */
    private array $supplierArticleIndex = [];

    /** @var array<string, array<int, int>> */
    private array $nameIndex = [];

    /** @var array<string, array<int, int>> */
    private array $tokenIndex = [];

    /**
     * Import CommerceML into a staging catalogue. This method never mutates products.
     *
     * @return array{categories:int, products:int, offers:int, staging_created:int, staging_updated:int, matched:int, suggested:int, ambiguous:int, unmatched:int}
     */
    public function import(string $xml, string $sourceCode = 'onec'): array
    {
        $this->createdExternalIds = [];
        $this->updatedExternalIds = [];
        $documents = $this->parseDocuments($xml);

        $source = IntegrationSource::query()->firstOrCreate(
            ['code' => $sourceCode],
            ['name' => $sourceCode === 'onec' ? '1С' : $sourceCode, 'driver' => 'commerceml']
        );

        $this->prepareIndexes();

        $stats = [
            'categories' => 0,
            'products' => 0,
            'offers' => 0,
            'staging_created' => 0,
            'staging_updated' => 0,
            'matched' => 0,
            'suggested' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
        ];

        DB::transaction(function () use ($documents, $source, &$stats): void {
            foreach ($documents as $document) {
                foreach ($document->xpath('/*[local-name()="КоммерческаяИнформация"]/*[local-name()="Классификатор"]/*[local-name()="Группы"]/*[local-name()="Группа"]') ?: [] as $node) {
                    $this->stageCategory($source, $node, null, null, $stats);
                }
            }

            $this->categoryIds = IntegrationCategory::query()
                ->whereBelongsTo($source, 'source')
                ->pluck('id', 'external_id');

            foreach ($documents as $document) {
                foreach ($document->xpath('//*[local-name()="Товар"]') ?: [] as $node) {
                    $this->stageProduct($source, $node, $stats);
                }
            }

            foreach ($documents as $document) {
                foreach ($document->xpath('//*[local-name()="Предложение"]') ?: [] as $node) {
                    $this->stageOffer($source, $node, $stats);
                }
            }
        });

        $stats['staging_created'] = count($this->createdExternalIds);
        $stats['staging_updated'] = count($this->updatedExternalIds);

        return $stats;
    }

    /** @return array{matched:int,suggested:int,ambiguous:int,unmatched:int} */
    public function rematchSource(string $sourceCode): array
    {
        $source = IntegrationSource::query()->where('code', $sourceCode)->firstOrFail();
        $this->prepareIndexes();

        $stats = [
            'matched' => 0,
            'suggested' => 0,
            'ambiguous' => 0,
            'unmatched' => 0,
        ];

        IntegrationProduct::query()
            ->whereBelongsTo($source, 'source')
            ->whereNull('product_id')
            ->where('match_status', '!=', 'ignored')
            ->orderBy('id')
            ->chunkById(250, function (Collection $items) use (&$stats): void {
                foreach ($items as $item) {
                    $item->fill($this->match($item));
                    $item->save();
                    $stats[$item->match_status]++;
                }
            });

        return $stats;
    }

    /** @return array<int, SimpleXMLElement> */
    private function parseDocuments(string $xml): array
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
        $previous = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();
            $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
            if ($document !== false) {
                return [$document];
            }

            preg_match_all(
                '/(?:<\?xml[^>]*>\s*)?<КоммерческаяИнформация\b.*?<\/КоммерческаяИнформация>/us',
                $xml,
                $matches
            );
            $parts = $matches[0] ?? [];

            $documents = [];
            foreach ($parts as $part) {
                libxml_clear_errors();
                $part = ltrim($part, "\xEF\xBB\xBF\x00\x09\x0A\x0D\x20");
                $parsed = simplexml_load_string($part, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
                if ($parsed === false) {
                    throw new \InvalidArgumentException('Invalid CommerceML XML package.');
                }

                $documents[] = $parsed;
            }

            if (count($documents) < 2) {
                throw new \InvalidArgumentException('Invalid CommerceML XML.');
            }

            return $documents;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @param array<string, int> $stats */
    private function stageCategory(
        IntegrationSource $source,
        SimpleXMLElement $node,
        ?IntegrationCategory $parent,
        ?string $parentPath,
        array &$stats
    ): void {
        $externalId = $this->text($node, './*[local-name()="Ид"]');
        $name = $this->text($node, './*[local-name()="Наименование"]');
        if ($externalId === '' || $name === '') {
            return;
        }

        $category = IntegrationCategory::query()->updateOrCreate(
            [
                'integration_source_id' => $source->id,
                'external_id' => $externalId,
            ],
            [
                'parent_id' => $parent?->id,
                'parent_external_id' => $parent?->external_id,
                'name' => $name,
                'path' => filled($parentPath) ? $parentPath.' / '.$name : $name,
                'payload' => $this->nodePayload($node),
                'last_seen_at' => now(),
            ]
        );

        $stats['categories']++;

        foreach ($node->xpath('./*[local-name()="Группы"]/*[local-name()="Группа"]') ?: [] as $child) {
            $this->stageCategory($source, $child, $category, $category->path, $stats);
        }
    }

    /** @param array<string, int> $stats */
    private function stageProduct(IntegrationSource $source, SimpleXMLElement $node, array &$stats): void
    {
        $externalId = $this->text($node, './*[local-name()="Ид"]');
        if ($externalId === '') {
            return;
        }

        $item = IntegrationProduct::query()->firstOrNew([
            'integration_source_id' => $source->id,
            'external_id' => $externalId,
        ]);
        $alreadyExists = $item->exists;

        $item->fill([
            'integration_category_id' => $this->productCategoryId($node),
            'external_code' => $this->requisiteValue($node, 'Код') ?: null,
            'external_sku' => $this->text($node, './*[local-name()="Артикул"]') ?: null,
            'barcode' => $this->text($node, './*[local-name()="Штрихкод"]') ?: null,
            'name' => $this->text($node, './*[local-name()="Наименование"]') ?: null,
            'payload' => $this->nodePayload($node),
            'last_seen_at' => now(),
        ]);

        if (! $item->product_id && $item->match_status !== 'ignored') {
            $item->fill($this->match($item));
        }

        $item->save();
        $this->trackStagingRow($item->external_id, $alreadyExists);
        $stats['products']++;
        $stats[$item->match_status] = ($stats[$item->match_status] ?? 0) + 1;
    }

    /** @param array<string, int> $stats */
    private function stageOffer(IntegrationSource $source, SimpleXMLElement $node, array &$stats): void
    {
        $externalId = $this->text($node, './*[local-name()="Ид"]');
        if ($externalId === '') {
            return;
        }

        $baseId = explode('#', $externalId, 2)[0];
        $item = IntegrationProduct::query()
            ->where('integration_source_id', $source->id)
            ->whereIn('external_id', array_unique([$externalId, $baseId]))
            ->orderByRaw('external_id = ? desc', [$externalId])
            ->first();
        $alreadyExists = (bool) $item;

        if (! $item) {
            $item = new IntegrationProduct([
                'integration_source_id' => $source->id,
                'external_id' => $externalId,
                'match_status' => 'unmatched',
            ]);
        }

        $price = $this->text($node, './/*[local-name()="ЦенаЗаЕдиницу"]');
        $quantity = $this->text($node, './*[local-name()="Количество"]');
        $item->fill([
            'external_sku' => $item->external_sku ?: ($this->text($node, './*[local-name()="Артикул"]') ?: null),
            'name' => $item->name ?: ($this->text($node, './*[local-name()="Наименование"]') ?: null),
            'price' => is_numeric(str_replace(',', '.', $price)) ? str_replace(',', '.', $price) : $item->price,
            'stock_quantity' => is_numeric(str_replace(',', '.', $quantity)) ? str_replace(',', '.', $quantity) : $item->stock_quantity,
            'last_seen_at' => now(),
            'last_offer_seen_at' => now(),
        ]);

        if (! $item->product_id && $item->match_status !== 'ignored') {
            $item->fill($this->match($item));
        }

        $item->save();
        $this->trackStagingRow($item->external_id, $alreadyExists);
        $stats['offers']++;
    }

    private function trackStagingRow(string $externalId, bool $alreadyExists): void
    {
        if (! $alreadyExists) {
            $this->createdExternalIds[$externalId] = true;
            unset($this->updatedExternalIds[$externalId]);

            return;
        }

        if (! isset($this->createdExternalIds[$externalId])) {
            $this->updatedExternalIds[$externalId] = true;
        }
    }

    private function productCategoryId(SimpleXMLElement $node): ?int
    {
        foreach ($node->xpath('./*[local-name()="Группы"]/*[local-name()="Ид"]') ?: [] as $groupId) {
            $categoryId = $this->categoryIds->get(trim((string) $groupId));
            if ($categoryId) {
                return (int) $categoryId;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function match(IntegrationProduct $item): array
    {
        $exactMatches = collect([
            'article' => $item->external_sku,
            'barcode' => $item->barcode,
            'onec_code' => $item->external_code,
        ])->filter()->flatMap(function (string $value, string $kind): array {
            $identifier = $this->normalizeIdentifier($value);
            if ($identifier === '') {
                return [];
            }

            return collect($this->skuIndex[$identifier] ?? [])
                ->map(fn (int $id): array => ['product_id' => $id, 'method' => "{$kind}_to_sku"])
                ->concat(collect($this->supplierArticleIndex[$identifier] ?? [])
                    ->map(fn (int $id): array => ['product_id' => $id, 'method' => "{$kind}_to_supplier_article"]))
                ->all();
        })->unique('product_id')->values();

        if ($exactMatches->count() === 1) {
            $match = $exactMatches->first();

            return $this->matched($this->products->get($match['product_id']), $match['method'], 1.0);
        }

        if ($exactMatches->count() > 1) {
            return [
                'product_id' => null,
                'match_status' => 'ambiguous',
                'match_method' => 'exact_identifier_conflict',
                'match_confidence' => 1,
                'candidates' => $exactMatches->map(function (array $match): array {
                    $product = $this->products->get($match['product_id']);

                    return [
                        'product_id' => $product->id,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'score' => 1,
                        'method' => $match['method'],
                    ];
                })->all(),
                'matched_at' => null,
            ];
        }

        $normalizedName = $this->normalizeName((string) $item->name);
        if ($normalizedName === '') {
            return $this->unmatched();
        }

        $exactNameIds = collect($this->nameIndex[$normalizedName] ?? [])->unique()->values();
        if ($exactNameIds->count() === 1) {
            return $this->suggested($this->products->get($exactNameIds->first()), 'exact_name', 0.9);
        }

        $candidateIds = collect($this->nameTokens($normalizedName))
            ->flatMap(fn (string $token): array => $this->tokenIndex[$token] ?? [])
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(500);

        $candidates = $candidateIds
            ->map(fn (int $id): ?Product => $this->products->get($id))
            ->filter()
            ->filter(fn (Product $product): bool => $this->hasCompatibleDimensions(
                (string) $item->name,
                (string) $product->name,
            ))
            ->map(function (Product $product) use ($normalizedName): array {
                similar_text($normalizedName, $this->normalizeName($product->name), $score);

                return [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'score' => round($score / 100, 4),
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['score'] >= 0.65)
            ->sortByDesc('score')
            ->take(5)
            ->values();

        if ($candidates->isEmpty()) {
            return $this->unmatched();
        }

        $best = $candidates->first();
        $second = $candidates->get(1);
        if ($best['score'] >= 0.88 && (! $second || $best['score'] - $second['score'] >= 0.08)) {
            $product = $this->products->get($best['product_id']);

            return $this->suggested($product, 'fuzzy_name', $best['score'], $candidates);
        }

        return [
            'product_id' => null,
            'match_status' => 'ambiguous',
            'match_method' => 'fuzzy_name',
            'match_confidence' => $best['score'],
            'candidates' => $candidates->all(),
            'matched_at' => null,
        ];
    }

    private function prepareIndexes(): void
    {
        $this->products = Product::query()->get(['id', 'sku', 'name'])->keyBy('id');
        $this->skuIndex = [];
        $this->supplierArticleIndex = [];
        $this->nameIndex = [];
        $this->tokenIndex = [];

        foreach ($this->products as $product) {
            $sku = $this->normalizeIdentifier((string) $product->sku);
            if ($sku !== '') {
                $this->skuIndex[$sku][] = $product->id;
            }

            $name = $this->normalizeName($product->name);
            if ($name !== '') {
                $this->nameIndex[$name][] = $product->id;
                foreach ($this->nameTokens($name) as $token) {
                    $this->tokenIndex[$token][] = $product->id;
                }
            }
        }

        try {
            DB::table('supplier_products')
                ->whereNotNull('product_id')
                ->get(['product_id', 'supplier_article'])
                ->each(function ($row): void {
                    $article = $this->normalizeIdentifier((string) $row->supplier_article);
                    if ($article !== '' && $this->products->has((int) $row->product_id)) {
                        $this->supplierArticleIndex[$article][] = (int) $row->product_id;
                    }
                });
        } catch (Throwable) {
            // A minimal installation can run without the supplier catalogue.
        }
    }

    /** @return array<int, string> */
    private function nameTokens(string $name): array
    {
        return collect(explode(' ', $name))
            ->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->unique()
            ->values()
            ->all();
    }

    private function hasCompatibleDimensions(string $sourceName, string $productName): bool
    {
        $sourceDiameters = $this->diameters($sourceName);
        if ($sourceDiameters !== [] && $sourceDiameters !== $this->diameters($productName)) {
            return false;
        }

        $sourceMeasurements = $this->measurementsInMillimetres($sourceName);

        return $sourceMeasurements === [] || $sourceMeasurements === $this->measurementsInMillimetres($productName);
    }

    /** @return array<int, int> */
    private function diameters(string $name): array
    {
        preg_match_all(
            '/(?<![\p{L}\p{N}])(?:dn|d|ф|ø|⌀)\s*[-:]?\s*(\d{2,4})(?!\d)/iu',
            mb_strtolower($name),
            $matches,
        );

        $values = array_map('intval', $matches[1] ?? []);
        sort($values);

        return array_values(array_unique($values));
    }

    /** @return array<int, int> */
    private function measurementsInMillimetres(string $name): array
    {
        preg_match_all(
            '/(?<![\p{L}\p{N}])(\d+(?:[.,]\d+)?)\s*(мм|mm|см|cm|мп|метр(?:а|ов)?|м|m)(?!\p{L})/iu',
            mb_strtolower($name),
            $matches,
            PREG_SET_ORDER,
        );

        $values = [];
        foreach ($matches as $match) {
            $value = (float) str_replace(',', '.', $match[1]);
            $unit = mb_strtolower($match[2]);
            $multiplier = match ($unit) {
                'см', 'cm' => 10,
                'м', 'm', 'мп', 'метр', 'метра', 'метров' => 1000,
                default => 1,
            };
            $values[] = (int) round($value * $multiplier);
        }

        sort($values);

        return array_values(array_unique($values));
    }

    /** @return array<string, mixed> */
    private function matched(Product $product, string $method, float $confidence): array
    {
        return [
            'product_id' => $product->id,
            'match_status' => 'matched',
            'match_method' => $method,
            'match_confidence' => $confidence,
            'candidates' => null,
            'matched_at' => now(),
        ];
    }

    /** @param Collection<int, array<string, mixed>>|null $candidates
     * @return array<string, mixed>
     */
    private function suggested(Product $product, string $method, float $confidence, ?Collection $candidates = null): array
    {
        return [
            'product_id' => null,
            'match_status' => 'suggested',
            'match_method' => $method,
            'match_confidence' => $confidence,
            'candidates' => ($candidates ?: collect([[
                'product_id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'score' => $confidence,
            ]]))->all(),
            'matched_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function unmatched(): array
    {
        return [
            'product_id' => null,
            'match_status' => 'unmatched',
            'match_method' => null,
            'match_confidence' => null,
            'candidates' => null,
            'matched_at' => null,
        ];
    }

    private function text(SimpleXMLElement $node, string $xpath): string
    {
        $values = $node->xpath($xpath);

        return trim((string) ($values[0] ?? ''));
    }

    private function requisiteValue(SimpleXMLElement $node, string $name): string
    {
        $values = $node->xpath(
            './/*[local-name()="ЗначениеРеквизита"]'.
            '[./*[local-name()="Наименование" and normalize-space(text())="'.$name.'"]]'.
            '/*[local-name()="Значение"]'
        );

        return trim((string) ($values[0] ?? ''));
    }

    /** @return array<string, mixed> */
    private function nodePayload(SimpleXMLElement $node): array
    {
        return json_decode(json_encode($node, JSON_UNESCAPED_UNICODE), true) ?: [];
    }

    private function normalizeIdentifier(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[^\p{L}\p{N}]+/u', '', trim($value)));
    }

    private function normalizeName(string $value): string
    {
        $value = str_replace('ё', 'е', mb_strtolower(trim($value)));
        $value = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}

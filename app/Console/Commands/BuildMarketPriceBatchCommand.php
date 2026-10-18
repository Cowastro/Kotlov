<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuildMarketPriceBatchCommand extends Command
{
    protected $signature = 'catalog:build-market-price-batch
        {batch : Stable batch code for later review and application}
        {--brand= : Exact production brand name}
        {--sku=* : One or more exact production SKUs}
        {--include-priced : Allow explicitly selected active products that already have a retail price}
        {--after-id=0 : Process products with a larger production ID}
        {--limit=10 : Maximum products to research}
        {--input-contexts= : Read previously exported production product contexts instead of querying a database}
        {--input-queue= : Read unreviewed products from a downloaded production price-research CSV}
        {--export-contexts= : Export matching production product contexts without calling the research provider}
        {--output=storage/app/private/market-price-research/market-price-research-batch.json : Server-local JSON report path}';

    protected $description = 'Build a review-only market-price batch from active production products using hosted web search.';

    public function handle(MarketWebResearchService $research): int
    {
        $batchCode = trim((string) $this->argument('batch'));
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $batchCode) !== 1) {
            $this->error('Invalid batch code.');

            return self::INVALID;
        }

        $limit = (int) $this->option('limit');
        $afterId = (int) $this->option('after-id');
        $includePriced = (bool) $this->option('include-priced');
        if ($limit < 1 || $limit > 25 || $afterId < 0) {
            $this->error('Limit must be between 1 and 25 and after-id cannot be negative.');

            return self::INVALID;
        }

        $output = $this->safeResearchPath((string) $this->option('output'));
        $inputContexts = trim((string) $this->option('input-contexts'));
        $inputQueue = trim((string) $this->option('input-queue'));
        $exportContexts = trim((string) $this->option('export-contexts'));
        if ($inputContexts !== '' && $inputQueue !== '') {
            $this->error('Only one input source can be used.');

            return self::INVALID;
        }

        $skus = collect((array) $this->option('sku'))->map(fn ($sku): string => trim((string) $sku))->filter()->values();
        $brand = trim((string) $this->option('brand'));
        if ($inputContexts === '' && $inputQueue === '' && $skus->isEmpty() && $brand === '') {
            $this->error('Provide --brand or at least one --sku.');

            return self::INVALID;
        }

        $selection = [
            'production_only' => true,
            'active_only' => true,
            'unpriced_only' => ! $includePriced,
            'brand' => $brand !== '' ? $brand : null,
            'skus' => $skus->all(),
            'after_id' => $afterId,
            'limit' => $limit,
        ];
        if ($inputContexts !== '') {
            [$products, $selection] = $this->readContexts($inputContexts);
        } elseif ($inputQueue !== '') {
            [$products, $selection] = $this->readQueue(
                $inputQueue,
                $selection,
                $brand,
                $skus->all(),
                $afterId,
                $limit,
            );
        } else {
            $products = Product::query()
                ->with(['brand', 'category'])
                ->where('is_active', true)
                ->where('is_archived', false)
                ->when(! $includePriced, fn (Builder $query) => $query
                    ->where(fn (Builder $priceQuery) => $priceQuery->whereNull('price')->orWhere('price', '<=', 0)))
                ->where('id', '>', $afterId)
                ->when($skus->isNotEmpty(), fn (Builder $query) => $query->whereIn('sku', $skus->all()))
                ->when($brand !== '', fn (Builder $query) => $query->whereHas('brand', fn (Builder $brandQuery) => $brandQuery->where('name', $brand)))
                ->orderBy('id')
                ->limit($limit)
                ->get();
        }

        if ($products->isEmpty()) {
            $this->error('No matching active unpriced production products were found.');

            return self::FAILURE;
        }

        if ($exportContexts !== '') {
            $this->writeContexts($exportContexts, $selection, $products);
            $this->line('metric.status=contexts_exported');
            $this->line('metric.selected_products='.$products->count());
            $this->line('metric.last_product_id='.$products->max('id'));
            $this->line('metric.output='.$this->relativeStoragePath($this->safeResearchPath($exportContexts)));

            return self::SUCCESS;
        }

        $sourceRows = [];
        $productRows = [];
        $failedProducts = [];
        $offersCount = 0;
        $forcedHolds = 0;

        foreach ($products as $product) {
            $result = $research->research($product, (float) ($product->price ?? 0));
            if (($result['status'] ?? null) !== 'ready') {
                $failedProducts[] = [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'message' => trim((string) ($result['message'] ?? 'Research provider did not return a ready report.')),
                ];

                continue;
            }

            $observations = [];
            $excludedOffers = [];
            foreach ((array) ($result['offers'] ?? []) as $offer) {
                $observation = $this->observationFromOffer($offer, $product, $sourceRows);
                if ($observation !== null) {
                    $observations[] = $observation;
                } else {
                    $excludedOffers[] = [
                        'title' => trim((string) ($offer['title'] ?? '')),
                        'url' => trim((string) ($offer['url'] ?? '')),
                        'reason' => $this->offerExclusionReason($offer),
                    ];
                }
            }

            $forceHold = count($observations) < 3;
            $offersCount += count($observations);
            $forcedHolds += $forceHold ? 1 : 0;
            $productRows[] = [
                'sku' => $product->sku,
                'review_reason' => $forceHold
                    ? 'Live-web исследование точной production-модели нашло менее трёх независимых подтверждённых предложений; сохранить консультационный режим.'
                    : 'Live-web исследование точной production-модели нашло не менее трёх независимых предложений; перед применением требуется ручная проверка URL, цены и комплектности.',
                'force_consultation_hold' => $forceHold,
                'observations' => $observations,
                'research' => [
                    'summary' => trim((string) ($result['summary'] ?? '')),
                    'verdict' => trim((string) ($result['verdict'] ?? '')),
                    'confidence' => $result['confidence'] ?? 'low',
                    'search_queries' => array_values((array) ($result['search_queries'] ?? [])),
                    'rejected_candidates' => array_values((array) ($result['rejected_candidates'] ?? [])),
                    'excluded_offers' => $excludedOffers,
                    'model' => $result['model'] ?? null,
                    'searched_at' => $this->researchTimestamp($result['searched_at'] ?? null),
                ],
            ];
        }

        $payload = [
            'code' => $batchCode,
            'observed_at' => now()->toIso8601String(),
            'review_required' => true,
            'selection' => $selection,
            'sources' => array_values($sourceRows),
            'products' => $productRows,
            'failed_products' => $failedProducts,
        ];

        File::ensureDirectoryExists(dirname($output));
        File::put($output, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->line('metric.status=review_required');
        $this->line('metric.selected_products='.$products->count());
        $this->line('metric.researched_products='.count($productRows));
        $this->line('metric.failed_products='.count($failedProducts));
        $this->line('metric.exact_offers='.$offersCount);
        $this->line('metric.forced_consultation_holds='.$forcedHolds);
        $this->line('metric.last_product_id='.$products->max('id'));
        $this->line('metric.output='.$this->relativeStoragePath($output));

        return $productRows === [] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $offer
     * @param  array<string, array<string, mixed>>  $sourceRows
     * @return array<string, mixed>|null
     */
    private function observationFromOffer(array $offer, Product $product, array &$sourceRows): ?array
    {
        if (($offer['match'] ?? null) !== 'exact') {
            return null;
        }

        $url = trim((string) ($offer['url'] ?? ''));
        $priceByn = is_numeric($offer['price_byn'] ?? null) ? (float) $offer['price_byn'] : 0;
        if (filter_var($url, FILTER_VALIDATE_URL) === false || $priceByn <= 0) {
            return null;
        }

        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host === 'kotlov.by' || str_ends_with($host, '.kotlov.by')) {
            return null;
        }

        $sourceCode = 'web-'.substr(hash('sha256', $host), 0, 16);
        $currency = Str::upper(trim((string) ($offer['currency'] ?? 'BYN')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $currency = 'BYN';
        }
        $observedPrice = is_numeric($offer['price'] ?? null) && (float) $offer['price'] > 0
            ? (float) $offer['price']
            : $priceByn;
        $rate = round($priceByn / $observedPrice, 6);
        if ($rate <= 0) {
            return null;
        }

        $sourceRows[$sourceCode] = [
            'code' => $sourceCode,
            'name' => Str::limit(trim((string) ($offer['seller'] ?? $host)) ?: $host, 250, ''),
            'kind' => 'competitor',
            'base_url' => 'https://'.$host,
            'currency' => $currency,
            'region' => 'Открытый рынок',
            'freshness_hours' => 168,
            'minimum_match_confidence' => 0.99,
            'notes' => 'Источник найден hosted web_search; batch требует ручной проверки до применения.',
        ];

        return [
            'source' => $sourceCode,
            'url' => $url,
            'external_name' => Str::limit(trim((string) ($offer['title'] ?? $product->name)), 250, ''),
            'model' => Str::limit($product->name, 250, ''),
            'package' => Str::limit(trim((string) ($offer['package'] ?? '')), 250, '') ?: null,
            'observed_price' => round($observedPrice, 2),
            'currency' => $currency,
            'exchange_rate_to_byn' => $rate,
            'price_includes_vat' => $this->vatIncluded($offer['vat'] ?? null),
            'availability_status' => $this->availabilityStatus($offer['availability'] ?? null),
            'match_method' => 'exact_model',
            'match_confidence' => 0.99,
            'is_confirmed' => true,
            'is_comparable' => true,
            'validation_flags' => [],
            'evidence' => Str::limit(trim((string) ($offer['evidence'] ?? '')), 500, ''),
        ];
    }

    /** @param  array<string, mixed>  $offer */
    private function offerExclusionReason(array $offer): string
    {
        $url = trim((string) ($offer['url'] ?? ''));
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            ($offer['match'] ?? null) !== 'exact' => 'Предложение не подтверждает точное совпадение модели.',
            $host === 'kotlov.by', str_ends_with($host, '.kotlov.by') => 'Собственный домен KOTLOV не является независимым рыночным источником.',
            filter_var($url, FILTER_VALIDATE_URL) === false => 'Некорректный URL предложения.',
            ! is_numeric($offer['price_byn'] ?? null) || (float) $offer['price_byn'] <= 0 => 'Нет положительной подтверждённой цены в BYN.',
            default => 'Предложение не прошло обязательную проверку сопоставимости.',
        };
    }

    private function availabilityStatus(mixed $value): string
    {
        $value = Str::lower(trim((string) $value));

        return match (true) {
            str_contains($value, 'нет в наличии'), str_contains($value, 'out of stock') => 'out_of_stock',
            str_contains($value, 'в наличии'), str_contains($value, 'in stock') => 'in_stock',
            str_contains($value, 'заказ'), str_contains($value, 'preorder') => 'order',
            default => 'unknown',
        };
    }

    private function vatIncluded(mixed $value): ?bool
    {
        $value = Str::lower(trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, 'без ндс') || str_contains($value, 'excluding vat')) {
            return false;
        }

        return str_contains($value, 'ндс') || str_contains($value, 'vat') ? true : null;
    }

    private function researchTimestamp(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toIso8601String();
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : now()->toIso8601String();
    }

    /** @return array{0: Collection<int, Product>, 1: array<string, mixed>} */
    private function readContexts(string $value): array
    {
        $path = $this->safeResearchPath($value);
        if (! File::isFile($path)) {
            throw new \InvalidArgumentException('Context file not found.');
        }

        $payload = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($payload) || ! is_array($payload['selection'] ?? null) || ! is_array($payload['products'] ?? null)) {
            throw new \InvalidArgumentException('Invalid market-price context file.');
        }

        $products = collect($payload['products'])->map(function (mixed $row): Product {
            if (! is_array($row)
                || ! is_numeric($row['id'] ?? null)
                || blank($row['sku'] ?? null)
                || blank($row['name'] ?? null)) {
                throw new \InvalidArgumentException('Every context product requires id, sku and name.');
            }

            $product = new Product;
            $product->forceFill([
                'id' => (int) $row['id'],
                'sku' => (string) $row['sku'],
                'name' => (string) $row['name'],
                'price' => is_numeric($row['price'] ?? null) ? (float) $row['price'] : 0,
                'specs' => is_array($row['specs'] ?? null) ? $row['specs'] : [],
            ]);
            $product->setRelation('brand', filled($row['brand'] ?? null)
                ? new Brand(['name' => (string) $row['brand']])
                : null);
            $product->setRelation('category', filled($row['category'] ?? null)
                ? new Category(['name' => (string) $row['category']])
                : null);

            return $product;
        })->values();

        return [$products, $payload['selection']];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  list<string>  $skus
     * @return array{0: Collection<int, Product>, 1: array<string, mixed>}
     */
    private function readQueue(
        string $value,
        array $selection,
        string $brand,
        array $skus,
        int $afterId,
        int $limit,
    ): array {
        $path = $this->safePrivateCsvPath($value);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException('Production research queue file not found.');
        }

        try {
            $headers = fgetcsv($handle, separator: ';', enclosure: '"', escape: '');
            if (! is_array($headers)) {
                throw new \InvalidArgumentException('Production research queue is empty.');
            }
            $headers[0] = ltrim((string) $headers[0], "\xEF\xBB\xBF");
            $rows = [];
            while (($values = fgetcsv($handle, separator: ';', enclosure: '"', escape: '')) !== false) {
                if (count($values) !== count($headers)) {
                    throw new \InvalidArgumentException('Production research queue has an invalid row.');
                }
                $rows[] = array_combine($headers, $values);
            }
        } finally {
            fclose($handle);
        }

        $products = collect($rows)
            ->filter(fn (array $row): bool => ($row['review_state'] ?? '') === 'unreviewed'
                && str_contains((string) ($row['issues'] ?? ''), 'zero_or_missing_price')
                && (int) ($row['product_id'] ?? 0) > $afterId
                && ($brand === '' || (string) ($row['brand'] ?? '') === $brand)
                && ($skus === [] || in_array((string) ($row['sku'] ?? ''), $skus, true)))
            ->sortBy(fn (array $row): int => (int) $row['product_id'])
            ->take($limit)
            ->map(function (array $row): Product {
                $product = new Product;
                $product->forceFill([
                    'id' => (int) $row['product_id'],
                    'sku' => (string) $row['sku'],
                    'name' => (string) $row['name'],
                    'specs' => [],
                ]);
                $product->setRelation('brand', filled($row['brand'] ?? null)
                    ? new Brand(['name' => (string) $row['brand']])
                    : null);
                $product->setRelation('category', filled($row['category'] ?? null)
                    ? new Category(['name' => (string) $row['category']])
                    : null);

                return $product;
            })
            ->values();

        $selection['source_queue'] = str_replace('\\', '/', $value);
        $selection['after_id'] = $afterId;
        $selection['limit'] = $limit;

        return [$products, $selection];
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  Collection<int, Product>  $products
     */
    private function writeContexts(string $value, array $selection, $products): void
    {
        $path = $this->safeResearchPath($value);
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'selection' => $selection,
            'products' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'price' => (float) ($product->price ?? 0),
                'brand' => $product->brand?->name,
                'category' => $product->category?->name,
                'specs' => $product->specs ?: [],
            ])->values()->all(),
        ];

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    private function safeResearchPath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $prefix = 'storage/app/private/market-price-research/';
        if (! str_starts_with($value, $prefix) || str_contains($value, '..') || ! str_ends_with($value, '.json')) {
            throw new \InvalidArgumentException('Output must be a JSON file inside '.$prefix);
        }

        return base_path($value);
    }

    private function safePrivateCsvPath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        if (! str_starts_with($value, 'storage/app/private/')
            || str_contains($value, '..')
            || ! str_ends_with($value, '.csv')
            || ! File::isFile(base_path($value))) {
            throw new \InvalidArgumentException('Input queue must be an existing CSV inside storage/app/private.');
        }

        return base_path($value);
    }

    private function relativeStoragePath(string $path): string
    {
        return str_replace('\\', '/', Str::after($path, base_path().DIRECTORY_SEPARATOR));
    }
}

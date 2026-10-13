<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Market\MarketWebResearchService;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BuildMarketPriceBatchCommand extends Command
{
    protected $signature = 'catalog:build-market-price-batch
        {batch : Stable batch code for later review and application}
        {--brand= : Exact production brand name}
        {--sku=* : One or more exact production SKUs}
        {--after-id=0 : Process products with a larger production ID}
        {--limit=10 : Maximum products to research}
        {--output=storage/app/private/market-price-research/market-price-research-batch.json : Server-local JSON report path}';

    protected $description = 'Build a review-only market-price batch from active unpriced production products using hosted web search.';

    public function handle(MarketWebResearchService $research): int
    {
        $batchCode = trim((string) $this->argument('batch'));
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $batchCode) !== 1) {
            $this->error('Invalid batch code.');

            return self::INVALID;
        }

        $limit = (int) $this->option('limit');
        $afterId = (int) $this->option('after-id');
        if ($limit < 1 || $limit > 25 || $afterId < 0) {
            $this->error('Limit must be between 1 and 25 and after-id cannot be negative.');

            return self::INVALID;
        }

        $output = $this->safeOutputPath((string) $this->option('output'));
        $skus = collect((array) $this->option('sku'))->map(fn ($sku): string => trim((string) $sku))->filter()->values();
        $brand = trim((string) $this->option('brand'));
        if ($skus->isEmpty() && $brand === '') {
            $this->error('Provide --brand or at least one --sku.');

            return self::INVALID;
        }

        $products = Product::query()
            ->with(['brand', 'category'])
            ->where('is_active', true)
            ->where('is_archived', false)
            ->where(fn (Builder $query) => $query->whereNull('price')->orWhere('price', '<=', 0))
            ->where('id', '>', $afterId)
            ->when($skus->isNotEmpty(), fn (Builder $query) => $query->whereIn('sku', $skus->all()))
            ->when($brand !== '', fn (Builder $query) => $query->whereHas('brand', fn (Builder $brandQuery) => $brandQuery->where('name', $brand)))
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($products->isEmpty()) {
            $this->error('No matching active unpriced production products were found.');

            return self::FAILURE;
        }

        $sourceRows = [];
        $productRows = [];
        $failedProducts = [];
        $offersCount = 0;
        $forcedHolds = 0;

        foreach ($products as $product) {
            $result = $research->research($product, 0);
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
            foreach ((array) ($result['offers'] ?? []) as $offer) {
                $observation = $this->observationFromOffer($offer, $product, $sourceRows);
                if ($observation !== null) {
                    $observations[] = $observation;
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
                    'model' => $result['model'] ?? null,
                    'searched_at' => $this->researchTimestamp($result['searched_at'] ?? null),
                ],
            ];
        }

        $payload = [
            'code' => $batchCode,
            'observed_at' => now()->toIso8601String(),
            'review_required' => true,
            'selection' => [
                'production_only' => true,
                'active_only' => true,
                'unpriced_only' => true,
                'brand' => $brand !== '' ? $brand : null,
                'skus' => $skus->all(),
                'after_id' => $afterId,
                'limit' => $limit,
            ],
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

    /** @param array<string, mixed> $offer
     *  @param array<string, array<string, mixed>> $sourceRows
     *  @return array<string, mixed>|null
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
        if ($host === '') {
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

    private function safeOutputPath(string $value): string
    {
        $value = str_replace('\\', '/', trim($value));
        $prefix = 'storage/app/private/market-price-research/';
        if (! str_starts_with($value, $prefix) || str_contains($value, '..') || ! str_ends_with($value, '.json')) {
            throw new \InvalidArgumentException('Output must be a JSON file inside '.$prefix);
        }

        return base_path($value);
    }

    private function relativeStoragePath(string $path): string
    {
        return str_replace('\\', '/', Str::after($path, base_path().DIRECTORY_SEPARATOR));
    }
}

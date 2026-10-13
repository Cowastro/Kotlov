<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\MarketPriceSource;
use App\Models\Product;
use App\Models\User;
use App\Services\Market\MarketPriceObservationRecorder;
use App\Services\Market\MarketPriceRecommendation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ApplyMarketPriceBatchCommand extends Command
{
    protected $signature = 'catalog:apply-market-price-batch
        {batch : JSON file name from database/data/market-price-batches, without extension}
        {--apply : Persist observations, safe recommendations and consultation holds}';

    protected $description = 'Validate or apply a reviewed market-price evidence batch to the production catalog.';

    public function handle(
        MarketPriceObservationRecorder $recorder,
        MarketPriceRecommendation $recommendations,
    ): int {
        $batchName = trim((string) $this->argument('batch'));
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $batchName) !== 1) {
            $this->error('Invalid batch name.');

            return self::INVALID;
        }

        $path = database_path('data/market-price-batches/'.$batchName.'.json');
        if (! File::isFile($path)) {
            $this->error('Batch file not found.');

            return self::FAILURE;
        }

        $batch = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        $sources = collect(Arr::get($batch, 'sources', []))->keyBy('code');
        $products = collect(Arr::get($batch, 'products', []));
        $this->validateBatch($sources->all(), $products->all());

        $result = [
            'products' => $products->count(),
            'observations' => $products->sum(fn (array $item): int => count($item['observations'] ?? [])),
            'prices_applied' => 0,
            'prices_unchanged' => 0,
            'consultation_holds' => 0,
            'old_prices_cleared' => 0,
        ];

        if (! $this->option('apply')) {
            foreach ($products as $item) {
                $this->findProduct((string) $item['sku']);
            }
            $this->printMetrics('dry_run', $result);

            return self::SUCCESS;
        }

        $actor = User::query()->where('role', 'admin')->where('is_active', true)->orderBy('id')->first();
        if (! $actor) {
            $this->error('No active administrator is available for the price audit trail.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($batch, $sources, $products, $actor, $recorder, $recommendations, &$result): void {
            $sourceModels = $sources->mapWithKeys(function (array $source): array {
                $model = MarketPriceSource::query()->updateOrCreate(
                    ['code' => $source['code']],
                    [
                        'name' => $source['name'],
                        'kind' => $source['kind'] ?? 'competitor',
                        'collection_method' => 'manual',
                        'base_url' => $source['base_url'],
                        'currency' => $source['currency'] ?? 'BYN',
                        'region' => $source['region'] ?? 'Беларусь',
                        'freshness_hours' => $source['freshness_hours'] ?? 168,
                        'minimum_match_confidence' => $source['minimum_match_confidence'] ?? 0.95,
                        'collection_authorized' => false,
                        'is_active' => true,
                        'notes' => $source['notes'] ?? 'Ручная проверка точной модели.',
                    ],
                );

                return [$source['code'] => $model];
            });

            foreach ($products as $item) {
                $product = $this->findProduct((string) $item['sku'], lock: true);
                $oldHold = (bool) $product->requires_price_confirmation;
                $oldPrice = (float) $product->price;
                $oldOldPrice = $product->price_old !== null ? (float) $product->price_old : null;

                foreach ($item['observations'] as $observation) {
                    $source = $sourceModels->get($observation['source']);
                    $recorder->record($source, $product, [
                        ...Arr::except($observation, ['source']),
                        'observed_at' => $observation['observed_at'] ?? $batch['observed_at'],
                    ]);
                }

                if (($item['clear_invalid_old_price'] ?? false)
                    && $product->price_old !== null
                    && (float) $product->price_old <= (float) $product->price) {
                    $product->update(['price_old' => null]);
                    $result['old_prices_cleared']++;
                }

                $recommendation = $recommendations->forProduct($product->fresh());
                $hold = true;

                if ($recommendation['status'] === 'ready') {
                    $hold = false;
                    if ($recommendation['can_apply']) {
                        $recommendations->apply(
                            $product,
                            $actor,
                            'Проверенный серверный пакет '.$batch['code'].': '.$item['review_reason'],
                            userAgent: 'catalog:apply-market-price-batch',
                        );
                        $result['prices_applied']++;
                    } else {
                        $result['prices_unchanged']++;
                    }
                } else {
                    $result['consultation_holds']++;
                }

                $product->update(['requires_price_confirmation' => $hold]);

                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'action' => $hold ? 'market_price_confirmation_required' : 'market_price_review_completed',
                    'model_type' => Product::class,
                    'model_id' => $product->id,
                    'old_values' => [
                        'price' => $oldPrice,
                        'price_old' => $oldOldPrice,
                        'requires_price_confirmation' => $oldHold,
                    ],
                    'new_values' => [
                        'price' => (float) $product->fresh()->price,
                        'price_old' => $product->fresh()->price_old !== null ? (float) $product->fresh()->price_old : null,
                        'requires_price_confirmation' => $hold,
                        'review_status' => $recommendation['status'],
                        'review_reason' => $recommendation['reason'],
                        'batch' => $batch['code'],
                    ],
                    'user_agent' => 'catalog:apply-market-price-batch',
                ]);
            }
        });

        $this->printMetrics('applied', $result);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sources
     * @param  array<int, array<string, mixed>>  $products
     */
    private function validateBatch(array $sources, array $products): void
    {
        if ($sources === [] || $products === []) {
            throw new \InvalidArgumentException('A batch requires sources and products.');
        }

        foreach ($products as $product) {
            if (blank($product['sku'] ?? null) || blank($product['review_reason'] ?? null)) {
                throw new \InvalidArgumentException('Every product requires sku and review_reason.');
            }
            if (empty($product['observations'])) {
                throw new \InvalidArgumentException('Every product requires at least one observation.');
            }
            foreach ($product['observations'] as $observation) {
                if (! isset($sources[$observation['source'] ?? ''])) {
                    throw new \InvalidArgumentException('Observation refers to an unknown source.');
                }
            }
        }
    }

    private function findProduct(string $sku, bool $lock = false): Product
    {
        $query = Product::query()
            ->where('sku', $sku)
            ->where('is_active', true)
            ->where('is_archived', false);
        if ($lock) {
            $query->lockForUpdate();
        }

        $matches = $query->get();
        if ($matches->count() !== 1) {
            throw new \RuntimeException('Expected exactly one active product for SKU '.$sku.'.');
        }

        return $matches->first();
    }

    /** @param  array<string, int>  $result */
    private function printMetrics(string $status, array $result): void
    {
        $this->line('metric.status='.$status);
        foreach ($result as $name => $value) {
            $this->line('metric.'.$name.'='.$value);
        }
    }
}

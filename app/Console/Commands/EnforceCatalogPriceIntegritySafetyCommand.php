<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class EnforceCatalogPriceIntegritySafetyCommand extends Command
{
    protected $signature = 'catalog:enforce-price-integrity-safety
        {--apply : Put unsafe prices on consultation hold and clear invalid old prices}';

    protected $description = 'Protect checkout from production price/cost anomalies without exposing procurement data.';

    public function handle(): int
    {
        $minimumMargin = (float) config('shop.order_management.minimum_margin_percent', 10);
        $unsafeIds = $this->unsafePriceProductIds($minimumMargin);
        $invalidOldPriceIds = $this->invalidOldPriceProductIds();
        $alreadyHeld = Product::query()
            ->whereIntegerInRaw('id', $unsafeIds)
            ->where('requires_price_confirmation', true)
            ->count();

        $result = [
            'unsafe_price_candidates' => count($unsafeIds),
            'already_held' => $alreadyHeld,
            'holds_applied' => 0,
            'invalid_old_price_candidates' => count($invalidOldPriceIds),
            'old_prices_cleared' => 0,
        ];

        if (! $this->option('apply')) {
            $this->printMetrics('dry_run', $result, $minimumMargin);

            return self::SUCCESS;
        }

        $pendingHoldIds = Product::query()
            ->whereIntegerInRaw('id', $unsafeIds)
            ->where('requires_price_confirmation', false)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if ($pendingHoldIds === [] && $invalidOldPriceIds === []) {
            $this->printMetrics('applied', $result, $minimumMargin);

            return self::SUCCESS;
        }

        $actor = User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
        if (! $actor) {
            $this->error('No active administrator is available for the price safety audit trail.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($pendingHoldIds, $invalidOldPriceIds, $actor, &$result): void {
            $products = Product::query()
                ->whereIntegerInRaw('id', array_values(array_unique([...$pendingHoldIds, ...$invalidOldPriceIds])))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($pendingHoldIds as $productId) {
                $product = $products->get($productId);
                if (! $product || $product->requires_price_confirmation) {
                    continue;
                }

                $product->update(['requires_price_confirmation' => true]);
                $result['holds_applied']++;
                $this->recordActivity(
                    $actor->id,
                    $product,
                    'catalog_price_safety_hold_applied',
                    ['requires_price_confirmation' => false],
                    ['requires_price_confirmation' => true],
                );
            }

            foreach ($invalidOldPriceIds as $productId) {
                $product = $products->get($productId);
                if (! $product || $product->price_old === null || (float) $product->price_old > (float) $product->price) {
                    continue;
                }

                $oldPrice = (float) $product->price_old;
                $product->update(['price_old' => null]);
                $result['old_prices_cleared']++;
                $this->recordActivity(
                    $actor->id,
                    $product,
                    'catalog_invalid_old_price_cleared',
                    ['price_old' => $oldPrice],
                    ['price_old' => null],
                );
            }
        });

        $this->printMetrics('applied', $result, $minimumMargin);

        return self::SUCCESS;
    }

    /** @return array<int, int> */
    private function unsafePriceProductIds(float $minimumMargin): array
    {
        $supplierCost = DB::table('supplier_products')
            ->selectRaw('product_id, MIN(price_byn) AS minimum_cost')
            ->whereNotNull('product_id')
            ->where('stock_quantity', '>', 0)
            ->where('price_byn', '>', 0)
            ->groupBy('product_id');

        return $this->activeProducts()
            ->joinSub($supplierCost, 'costs', 'costs.product_id', '=', 'p.id')
            ->where('p.price', '>', 0)
            ->whereRaw(
                '(p.price < costs.minimum_cost * 1.0
                    OR ((p.price - costs.minimum_cost) * 100.0 / p.price) < CAST(? AS DECIMAL(10, 2))
                    OR p.price > costs.minimum_cost * 4)',
                [$minimumMargin],
            )
            ->orderBy('p.id')
            ->pluck('p.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /** @return array<int, int> */
    private function invalidOldPriceProductIds(): array
    {
        return $this->activeProducts()
            ->where('p.price_old', '>', 0)
            ->whereColumn('p.price_old', '<=', 'p.price')
            ->orderBy('p.id')
            ->pluck('p.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function activeProducts(): Builder
    {
        return DB::table('products as p')
            ->where('p.is_active', true)
            ->where('p.is_archived', false);
    }

    /** @param array<string, mixed> $oldValues @param array<string, mixed> $newValues */
    private function recordActivity(
        int $userId,
        Product $product,
        string $action,
        array $oldValues,
        array $newValues,
    ): void {
        ActivityLog::query()->create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => Product::class,
            'model_id' => $product->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_agent' => 'catalog:enforce-price-integrity-safety',
        ]);
    }

    /** @param array<string, int> $result */
    private function printMetrics(string $status, array $result, float $minimumMargin): void
    {
        $this->line('metric.status='.$status);
        foreach ($result as $name => $value) {
            $this->line('metric.'.$name.'='.$value);
        }
        $this->line('metric.minimum_margin_percent='.number_format($minimumMargin, 1, '.', ''));
    }
}

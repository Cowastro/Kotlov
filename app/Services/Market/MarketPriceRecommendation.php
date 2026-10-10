<?php

namespace App\Services\Market;

use App\Models\ActivityLog;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders\OrderItemSupplyContextResolver;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketPriceRecommendation
{
    public function __construct(
        private readonly MarketPriceSummary $summaries,
        private readonly OrderItemSupplyContextResolver $supplyContexts,
    ) {}

    /** @return array<string, mixed> */
    public function forProduct(Product $product, ?CarbonInterface $asOf = null): array
    {
        $product->loadMissing([
            'marketPriceObservations.source',
            'integrationProducts.source.supplier.latestChannelTransition',
            'supplierProducts.supplier.latestChannelTransition',
        ]);

        $summary = $this->summaries->forProduct($product, $asOf);
        $base = [
            'status' => 'insufficient_market',
            'label' => 'Недостаточно данных',
            'reason' => $summary['reason'] ?? null,
            'current_price' => $product->price !== null ? (float) $product->price : null,
            'recommended_price' => null,
            'price_change' => null,
            'purchase_price' => null,
            'purchase_price_label' => null,
            'supplier_name' => null,
            'source_label' => null,
            'current_margin_byn' => null,
            'current_margin_percent' => null,
            'recommended_margin_byn' => null,
            'recommended_margin_percent' => null,
            'minimum_margin_percent' => (float) config('shop.order_management.minimum_margin_percent', 10),
            'market' => $summary,
            'can_apply' => false,
        ];

        if ($summary['status'] !== 'ready') {
            return $base;
        }

        $item = new OrderItem([
            'product_id' => $product->id,
            'price' => (float) $product->price,
            'quantity' => 1,
        ]);
        $item->setRelation('product', $product);
        $supply = $this->supplyContexts->resolve($item);

        $base = array_merge($base, [
            'purchase_price' => $supply['wholesale_price'],
            'purchase_price_label' => $supply['wholesale_price_label'],
            'supplier_name' => $supply['supplier_name'],
            'source_label' => $supply['source_label'],
            'current_margin_byn' => $supply['margin_unit'],
            'current_margin_percent' => $supply['margin_percent'],
        ]);

        if (! $supply['has_purchase_price']) {
            return array_merge($base, [
                'status' => 'insufficient_cost',
                'label' => 'Нет входной цены',
                'reason' => 'Сначала подтвердите поставщика и входную цену. Без неё нельзя оценить влияние рекомендации на маржу.',
            ]);
        }

        $minimumMargin = $base['minimum_margin_percent'];
        if ($minimumMargin >= 100) {
            return array_merge($base, [
                'status' => 'invalid_margin_rule',
                'label' => 'Ошибка правила маржи',
                'reason' => 'Минимальная маржа должна быть меньше 100%.',
            ]);
        }

        $purchasePrice = (float) $supply['wholesale_price'];
        $minimumSafePrice = round($purchasePrice / (1 - $minimumMargin / 100), 2);
        $recommendedPrice = round(max((float) $summary['median'], $minimumSafePrice), 2);

        if ($recommendedPrice > (float) $summary['maximum']) {
            return array_merge($base, [
                'status' => 'margin_conflict',
                'label' => 'Рынок не покрывает маржу',
                'reason' => 'Для минимальной маржи '.number_format($minimumMargin, 1, ',', ' ')
                    .'% нужна цена не ниже '.number_format($minimumSafePrice, 2, ',', ' ')
                    .' BYN, что выше подтверждённого рыночного коридора.',
            ]);
        }

        $recommendedMargin = round($recommendedPrice - $purchasePrice, 2);
        $recommendedMarginPercent = $recommendedPrice > 0
            ? round($recommendedMargin / $recommendedPrice * 100, 1)
            : null;
        $currentPrice = (float) $product->price;
        $priceChange = round($recommendedPrice - $currentPrice, 2);

        return array_merge($base, [
            'status' => 'ready',
            'label' => abs($priceChange) < 0.01 ? 'Цена уже оптимальна' : 'Рекомендация готова',
            'reason' => 'Ориентир — медиана подтверждённого рынка; при необходимости цена поднята до уровня минимальной маржи.',
            'recommended_price' => $recommendedPrice,
            'price_change' => $priceChange,
            'recommended_margin_byn' => $recommendedMargin,
            'recommended_margin_percent' => $recommendedMarginPercent,
            'can_apply' => abs($priceChange) >= 0.01,
        ]);
    }

    /** @return array{product: Product, recommendation: array<string, mixed>, audit: ActivityLog} */
    public function apply(Product $product, User $actor, string $reason, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        if (! $actor->isAdmin()) {
            throw new AuthorizationException('Изменять цену по рекомендации может только администратор.');
        }

        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => 'Укажите осмысленную причину изменения цены (не менее 10 символов).',
            ]);
        }

        return DB::transaction(function () use ($product, $actor, $reason, $ipAddress, $userAgent): array {
            /** @var Product $locked */
            $locked = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $recommendation = $this->forProduct($locked);

            if ($recommendation['status'] !== 'ready' || ! $recommendation['can_apply']) {
                throw ValidationException::withMessages([
                    'price' => $recommendation['reason'] ?: 'Актуальную рекомендацию применить нельзя.',
                ]);
            }

            $oldPrice = (float) $locked->price;
            $newPrice = (float) $recommendation['recommended_price'];
            $market = $recommendation['market'];

            $locked->update(['price' => $newPrice]);

            $audit = ActivityLog::query()->create([
                'user_id' => $actor->id,
                'action' => 'market_price_recommendation_applied',
                'model_type' => Product::class,
                'model_id' => $locked->id,
                'old_values' => [
                    'price' => $oldPrice,
                    'currency' => $locked->currency ?: 'BYN',
                ],
                'new_values' => [
                    'price' => $newPrice,
                    'currency' => $locked->currency ?: 'BYN',
                    'reason' => $reason,
                    'purchase_price' => $recommendation['purchase_price'],
                    'supplier_name' => $recommendation['supplier_name'],
                    'source_label' => $recommendation['source_label'],
                    'current_margin_percent' => $recommendation['current_margin_percent'],
                    'recommended_margin_percent' => $recommendation['recommended_margin_percent'],
                    'market_minimum' => $market['minimum'],
                    'market_median' => $market['median'],
                    'market_maximum' => $market['maximum'],
                    'market_sources_count' => $market['sources_count'],
                    'market_checked_at' => $market['latest_observed_at']?->toIso8601String(),
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
            ]);

            return [
                'product' => $locked->fresh(),
                'recommendation' => $recommendation,
                'audit' => $audit,
            ];
        });
    }
}

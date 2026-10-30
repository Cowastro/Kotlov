<?php

namespace App\Services\Pricing;

use App\Models\B2bPriceLevel;
use App\Models\B2bPricingRule;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Support\Collection;

class B2bPartnerPricingEngine
{
    /** @var array<int, int>|null */
    private ?array $categoryParents = null;

    public const REASON_READY = 'ready';

    public const REASON_UNMATCHED = 'unmatched';

    public const REASON_INACTIVE_PRODUCT = 'inactive_product';

    public const REASON_NO_PURCHASE_PRICE = 'no_purchase_price';

    public const REASON_STALE_STOCK = 'stale_stock';

    public const REASON_NO_RETAIL_PRICE = 'no_retail_price';

    public const REASON_NO_RULE = 'no_rule';

    public const REASON_INVALID_RULE = 'invalid_rule';

    public const REASON_PRICE_CONFLICT = 'price_conflict';

    public const REASON_EXCLUDED = 'excluded';

    /** @return array<string, mixed> */
    public function evaluate(
        SupplierProduct $offer,
        Supplier $supplier,
        Collection $rules,
        array $categoryParents,
        bool $includeDraft = true,
    ): array {
        $product = $offer->product;
        $purchasePrice = round((float) $offer->price_byn, 2);
        $retailPrice = round((float) ($product?->price ?? 0), 2);
        $rule = $product
            ? $this->resolveRule($product->id, $product->brand_id, $product->category_id, $rules, $categoryParents, $includeDraft)
            : null;

        $base = [
            'offer_id' => (int) $offer->id,
            'product_id' => $product?->id ? (int) $product->id : null,
            'rule_id' => $rule?->id ? (int) $rule->id : null,
            'rule_name' => $rule?->name,
            'purchase_price' => $purchasePrice > 0 ? $purchasePrice : null,
            'retail_price' => $retailPrice > 0 ? $retailPrice : null,
            'b2b_price' => null,
            'minimum_safe_price' => null,
            'minimum_margin_percent' => $rule ? max(0, min(99, (float) $rule->minimum_margin_percent)) : null,
            'profit' => null,
            'margin_percent' => null,
            'stock_quantity' => (float) $offer->stock_quantity,
            'stock_updated_at' => $offer->last_stock_synced_at ?? $offer->last_synced_at ?? $offer->updated_at,
            'client_warehouse_label' => $supplier->b2b_client_warehouse_label ?: 'Партнёрский склад',
            'delivery_days_min' => (int) $supplier->b2b_delivery_days_min,
            'delivery_days_max' => (int) $supplier->b2b_delivery_days_max,
            'publishable' => false,
        ];

        if (! $product || $offer->match_status !== 'matched') {
            return $base + $this->reason(self::REASON_UNMATCHED, 'Товар не сопоставлен');
        }
        if (! $product->is_active || $product->is_archived) {
            return $base + $this->reason(self::REASON_INACTIVE_PRODUCT, 'Карточка товара не активна');
        }
        if ($offer->b2b_excluded) {
            return $base + $this->reason(self::REASON_EXCLUDED, $offer->b2b_exclusion_reason ?: 'Исключён менеджером');
        }
        if ($purchasePrice <= 0) {
            return $base + $this->reason(self::REASON_NO_PURCHASE_PRICE, 'Нет актуальной закупочной цены');
        }
        if (! $this->hasFreshStock($offer, $supplier)) {
            return $base + $this->reason(self::REASON_STALE_STOCK, 'Нет свежего положительного остатка');
        }
        if ($retailPrice <= 0) {
            return $base + $this->reason(self::REASON_NO_RETAIL_PRICE, 'Нет розничной цены');
        }
        if (! $rule) {
            return $base + $this->reason(self::REASON_NO_RULE, 'Нет правила маржи');
        }
        if (! $this->isValidRule($rule)) {
            return $base + $this->reason(self::REASON_INVALID_RULE, 'В правиле указано недопустимое значение');
        }

        $minimumSafePrice = $this->minimumSafePrice($purchasePrice, $rule);
        $b2bPrice = max($this->calculatePrice($purchasePrice, $rule), $minimumSafePrice);
        $maximumPrice = $retailPrice * (1 - max(0, (float) $rule->minimum_retail_discount_percent) / 100);
        if ($b2bPrice >= $retailPrice || $b2bPrice > $maximumPrice + 0.005) {
            return array_merge($base, [
                'b2b_price' => $b2bPrice,
                'minimum_safe_price' => $minimumSafePrice,
                'profit' => round($b2bPrice - $purchasePrice, 2),
                'margin_percent' => $b2bPrice > 0 ? round(($b2bPrice - $purchasePrice) / $b2bPrice * 100, 2) : null,
            ], $this->reason(self::REASON_PRICE_CONFLICT, 'B2B-цена не ниже розничной'));
        }

        $profit = round($b2bPrice - $purchasePrice, 2);

        return array_merge($base, [
            'b2b_price' => $b2bPrice,
            'minimum_safe_price' => $minimumSafePrice,
            'profit' => $profit,
            'margin_percent' => round($profit / $b2bPrice * 100, 2),
            'publishable' => $supplier->b2b_partner_enabled && $rule->status === B2bPricingRule::STATUS_ACTIVE,
        ], $this->reason(self::REASON_READY, 'Готово к публикации'));
    }

    /**
     * Public sale quote for a client. Purchase price and calculated profit are
     * deliberately omitted so this array is safe to pass to the storefront.
     *
     * @return array{base_price:float,price:float,price_level_id:?int,price_level_code:?string,price_level_name:?string,discount_percent:float}|null
     */
    public function clientQuote(SupplierProduct $offer, ?B2bPriceLevel $priceLevel = null): ?array
    {
        $offer->loadMissing(['supplier.b2bPricingRules', 'product.brand', 'product.category']);
        $supplier = $offer->supplier;
        if (! $supplier) {
            return null;
        }

        $categoryParents = $this->categoryParents ??= Category::query()
            ->pluck('parent_id', 'id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $evaluation = $this->evaluate(
            $offer,
            $supplier,
            $supplier->b2bPricingRules,
            $categoryParents,
            false,
        );
        if (! ($evaluation['publishable'] ?? false)) {
            return null;
        }

        $basePrice = (float) $evaluation['b2b_price'];
        $minimumSafePrice = (float) $evaluation['minimum_safe_price'];
        $discounted = $priceLevel?->applyToSalePrice($basePrice) ?? $basePrice;
        $step = max(0.01, (float) ($evaluation['rule_id']
            ? $supplier->b2bPricingRules->firstWhere('id', $evaluation['rule_id'])?->rounding_step_byn
            : 0.01));
        $price = min($basePrice, $this->roundUp(max($discounted, $minimumSafePrice), $step));
        $actualDiscount = $basePrice > 0
            ? round(max(0, ($basePrice - $price) / $basePrice * 100), 2)
            : 0.0;

        return [
            'base_price' => round($basePrice, 2),
            'price' => round($price, 2),
            'price_level_id' => $priceLevel?->id,
            'price_level_code' => $priceLevel?->code,
            'price_level_name' => $priceLevel?->name,
            'discount_percent' => $actualDiscount,
        ];
    }

    /** @return array{total:int,would_publish:int,published:int,reasons:array<string,int>,items:Collection<int,array<string,mixed>>} */
    public function preview(Supplier $supplier, bool $includeDraft = true): array
    {
        $rules = $supplier->b2bPricingRules()->get();
        $categoryParents = Category::query()->pluck('parent_id', 'id')->map(fn ($id): int => (int) $id)->all();
        $offers = $supplier->supplierProducts()
            ->with(['product.brand', 'product.category'])
            ->orderBy('id')
            ->get();

        $items = $offers->map(fn (SupplierProduct $offer): array => $this->evaluate(
            $offer,
            $supplier,
            $rules,
            $categoryParents,
            $includeDraft,
        ));

        return [
            'total' => $items->count(),
            'would_publish' => $items->where('reason', self::REASON_READY)->count(),
            'published' => $items->where('publishable', true)->count(),
            'reasons' => $items->where('reason', '!=', self::REASON_READY)->countBy('reason')->all(),
            'items' => $items,
        ];
    }

    private function resolveRule(
        int $productId,
        ?int $brandId,
        int $categoryId,
        Collection $rules,
        array $categoryParents,
        bool $includeDraft,
    ): ?B2bPricingRule {
        $categoryIds = [];
        $visited = [];
        while ($categoryId > 0 && ! isset($visited[$categoryId])) {
            $categoryIds[] = $categoryId;
            $visited[$categoryId] = true;
            $categoryId = (int) ($categoryParents[$categoryId] ?? 0);
        }

        return $rules
            ->filter(fn (B2bPricingRule $rule): bool => $rule->isEffective($includeDraft))
            ->filter(fn (B2bPricingRule $rule): bool => match ($rule->scope_type) {
                B2bPricingRule::SCOPE_PRODUCT => (int) $rule->scope_id === $productId,
                B2bPricingRule::SCOPE_BRAND => $brandId && (int) $rule->scope_id === $brandId,
                B2bPricingRule::SCOPE_CATEGORY => in_array((int) $rule->scope_id, $categoryIds, true),
                B2bPricingRule::SCOPE_SUPPLIER => true,
                default => false,
            })
            ->sort(function (B2bPricingRule $left, B2bPricingRule $right): int {
                $leftKey = [$this->scopeRank($left->scope_type), $left->priority, $left->id];
                $rightKey = [$this->scopeRank($right->scope_type), $right->priority, $right->id];

                return $rightKey <=> $leftKey;
            })
            ->first();
    }

    private function calculatePrice(float $purchasePrice, B2bPricingRule $rule): float
    {
        $value = (float) $rule->value;
        $price = match ($rule->calculation_type) {
            B2bPricingRule::TYPE_TARGET_MARGIN => $value > 0 && $value < 100
                ? $purchasePrice / (1 - $value / 100)
                : $purchasePrice,
            B2bPricingRule::TYPE_MARKUP => $purchasePrice * (1 + max(0, $value) / 100),
            B2bPricingRule::TYPE_FIXED_PRICE => max(0, $value),
            default => $purchasePrice,
        };

        $price = max($price, $purchasePrice + max(0, (float) $rule->minimum_profit_byn));

        return $this->roundUp($price, max(0.01, (float) $rule->rounding_step_byn));
    }

    private function minimumSafePrice(float $purchasePrice, B2bPricingRule $rule): float
    {
        $minimumMargin = max(0, min(99, (float) $rule->minimum_margin_percent));
        $marginFloor = $minimumMargin > 0
            ? $purchasePrice / (1 - $minimumMargin / 100)
            : $purchasePrice;
        $profitFloor = $purchasePrice + max(0, (float) $rule->minimum_profit_byn);

        return $this->roundUp(
            max($marginFloor, $profitFloor),
            max(0.01, (float) $rule->rounding_step_byn),
        );
    }

    private function roundUp(float $price, float $step): float
    {
        return round(ceil(($price - 0.000001) / $step) * $step, 2);
    }

    private function isValidRule(B2bPricingRule $rule): bool
    {
        $value = (float) $rule->value;

        return match ($rule->calculation_type) {
            B2bPricingRule::TYPE_TARGET_MARGIN => $value > 0 && $value < 100,
            B2bPricingRule::TYPE_MARKUP => $value >= 0,
            B2bPricingRule::TYPE_FIXED_PRICE => $value > 0,
            default => false,
        };
    }

    private function hasFreshStock(SupplierProduct $offer, Supplier $supplier): bool
    {
        if (! $supplier->b2b_require_positive_stock) {
            return true;
        }

        $confirmedAt = $offer->last_stock_synced_at ?? $offer->last_synced_at ?? $offer->updated_at;

        return (float) $offer->stock_quantity > 0
            && $confirmedAt?->gte(now()->subHours(max(1, (int) $supplier->b2b_stock_max_age_hours)));
    }

    private function scopeRank(string $scope): int
    {
        return match ($scope) {
            B2bPricingRule::SCOPE_PRODUCT => 4,
            B2bPricingRule::SCOPE_BRAND => 3,
            B2bPricingRule::SCOPE_CATEGORY => 2,
            default => 1,
        };
    }

    /** @return array{reason:string,reason_label:string} */
    private function reason(string $reason, string $label): array
    {
        return ['reason' => $reason, 'reason_label' => $label];
    }
}

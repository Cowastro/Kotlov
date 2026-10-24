<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;

class CartCouponService
{
    public function __construct(private readonly ChimneyBundlePromotion $chimneyBundlePromotion) {}

    /**
     * @return array{code: ?string, coupon: ?Coupon, discount: float, total: float, valid: bool}
     */
    public function quote(?string $code, float $subtotal, ?float $discountBase = null): array
    {
        $normalizedCode = $this->normalize($code);
        $coupon = $normalizedCode !== null
            ? Coupon::query()->where('code', $normalizedCode)->first()
            : null;
        $valid = $coupon !== null && $coupon->isValid($subtotal);
        $base = min($subtotal, max(0, $discountBase ?? $subtotal));
        $discount = (float) ($valid
            ? min($base, round($coupon->calculateDiscount($base), 2))
            : 0.0);

        return [
            'code' => $valid ? $normalizedCode : null,
            'coupon' => $valid ? $coupon : null,
            'discount' => $discount,
            'total' => round(max(0, $subtotal - $discount), 2),
            'valid' => $valid,
        ];
    }

    public function normalize(?string $code): ?string
    {
        $normalized = mb_strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    public function quoteForCart(?string $code, array $items): array
    {
        $subtotal = $this->subtotal($items);
        $bundle = $this->chimneyBundlePromotion->quote($items);
        $discountBase = $this->isOnlineOrderDiscountCode($code)
            ? max(0, $this->onlineOrderDiscountSubtotal($items) - $bundle['eligible_subtotal'])
            : $subtotal;

        $coupon = $this->quote($code, $subtotal, $discountBase);
        $couponDiscount = $coupon['discount'];
        $discount = round($couponDiscount + $bundle['discount'], 2);

        return array_merge($coupon, [
            'coupon_discount' => $couponDiscount,
            'bundle_discount' => $bundle['discount'],
            'bundle' => $bundle,
            'discount' => $discount,
            'total' => round(max(0, $subtotal - $discount), 2),
        ]);
    }

    public function onlineOrderDiscountSubtotal(array $items): float
    {
        $excluded = (array) config('shop.online_order_discount.excluded_category_slugs', []);
        // Products of a running supplier promotion keep the promotional price only.
        $promoIds = Product::query()
            ->whereIn('id', collect($items)->pluck('id')->filter()->all())
            ->whereNotNull('sale_campaign')
            ->where('is_sale', true)
            ->where('sale_ends_at', '>', now())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        return round(collect($items)
            ->filter(fn (array $item): bool => ($item['pricing_type'] ?? 'retail') !== 'b2b')
            ->reject(fn (array $item): bool => in_array($item['category_slug'] ?? null, $excluded, true))
            ->reject(fn (array $item): bool => in_array((int) ($item['id'] ?? 0), $promoIds, true))
            ->sum(fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 2);
    }

    public function isOnlineOrderDiscountCode(?string $code): bool
    {
        return $this->normalize($code) === $this->normalize(
            (string) config('shop.online_order_discount.code', 'ONLINE3')
        );
    }

    public function onlineOrderDiscountAppliesToPayment(?string $paymentType): bool
    {
        return ! in_array(
            $paymentType,
            (array) config('shop.online_order_discount.excluded_payment_methods', []),
            true
        );
    }

    private function subtotal(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)
        ), 2);
    }
}

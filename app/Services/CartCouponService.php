<?php

namespace App\Services;

use App\Models\Coupon;

class CartCouponService
{
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
        $discount = $valid
            ? min($base, round($coupon->calculateDiscount($base), 2))
            : 0.0;

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
        $discountBase = $this->isOnlineOrderDiscountCode($code)
            ? $this->onlineOrderDiscountSubtotal($items)
            : $subtotal;

        return $this->quote($code, $subtotal, $discountBase);
    }

    public function onlineOrderDiscountSubtotal(array $items): float
    {
        $excluded = (array) config('shop.online_order_discount.excluded_category_slugs', []);

        return round(collect($items)
            ->filter(fn (array $item): bool => ($item['pricing_type'] ?? 'retail') !== 'b2b')
            ->reject(fn (array $item): bool => in_array($item['category_slug'] ?? null, $excluded, true))
            ->sum(fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)), 2);
    }

    public function isOnlineOrderDiscountCode(?string $code): bool
    {
        return $this->normalize($code) === $this->normalize(
            (string) config('shop.online_order_discount.code', 'ONLINE3')
        );
    }

    private function subtotal(array $items): float
    {
        return round(collect($items)->sum(
            fn (array $item): float => (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0)
        ), 2);
    }
}

<?php

namespace App\Services;

use App\Models\Coupon;

class CartCouponService
{
    /**
     * @return array{code: ?string, coupon: ?Coupon, discount: float, total: float, valid: bool}
     */
    public function quote(?string $code, float $subtotal): array
    {
        $normalizedCode = $this->normalize($code);
        $coupon = $normalizedCode !== null
            ? Coupon::query()->where('code', $normalizedCode)->first()
            : null;
        $valid = $coupon !== null && $coupon->isValid($subtotal);
        $discount = $valid
            ? min($subtotal, round($coupon->calculateDiscount($subtotal), 2))
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
}

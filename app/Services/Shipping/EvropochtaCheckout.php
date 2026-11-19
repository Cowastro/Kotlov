<?php

namespace App\Services\Shipping;

use App\Models\Product;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class EvropochtaCheckout
{
    public const METHOD = 'evropochta';

    public const PAYMENT = 'bank_transfer';

    public function __construct(private readonly Evropochta $carrier) {}

    public function parcel(array $cart): ?array
    {
        if (! config('services.evropochta.checkout_enabled') || ! $this->carrier->configured() || count($cart) !== 1) {
            return null;
        }

        $item = reset($cart);
        if ((int) ($item['quantity'] ?? 0) !== 1 || ($item['pricing_type'] ?? 'retail') !== 'retail') {
            return null;
        }

        $product = Product::query()->active()->find($item['id'], ['id', 'shipping_parcel']);
        $dimensions = $product?->shipping_parcel ?? [];

        $parcel = ['product_id' => (int) $item['id'], 'store_id_start' => config('services.evropochta.checkout_origin')];
        foreach (['weight', 'length', 'width', 'height'] as $field) {
            $value = $dimensions[$field] ?? null;
            if (! is_numeric($value) || (float) $value < 0.01 || (float) $value > 250) {
                return null;
            }

            $parcel[$field] = round((float) $value, 2);
        }

        if (isset($dimensions['box']) && ($dimensions['weight_mode'] ?? null) === 'band') {
            $parcel['box'] = $dimensions['box'];
            $parcel['weight_mode'] = 'band';
        }

        try {
            return $parcel['weight'] <= 5 && ! Evropochta::isOversize($parcel) ? $parcel : null;
        } catch (RuntimeException) {
            return null;
        }
    }

    public function destinations(array $parcel): array
    {
        $branches = $this->carrier->branches();
        if (! isset($branches[$parcel['store_id_start']])) {
            return [];
        }

        return array_filter($branches, static function (array $branch): bool {
            $city = preg_replace('/^(?:г\.|город)\s*/ui', '', $branch['city']);

            return $city !== '' && mb_strtolower($city) !== 'минск';
        });
    }

    public function quote(array $parcel, int $destination, string $sessionId): array
    {
        $branch = $this->destinations($parcel)[$destination] ?? null;
        if ($branch === null) {
            throw new RuntimeException('Выберите отделение Европочты в регионе.');
        }

        $rate = $this->carrier->quote($parcel + ['store_id_finish' => $destination]);
        $kopecks = (int) round($rate['total'] * 100);
        $price = intdiv($kopecks + 49, 50) * 50 / 100;
        $snapshot = [
            'parcel' => $parcel,
            'store_id_finish' => $destination,
            'city' => preg_replace('/^(?:г\.|город)\s*/ui', '', $branch['city']),
            'address' => $branch['label'],
            'region' => $branch['region'],
            'carrier_price' => $rate['total'],
            'customer_price' => $price,
            'quoted_at' => now()->toIso8601String(),
        ];

        return [
            'price' => $price,
            'token' => Crypt::encrypt([
                'session' => $sessionId,
                'expires' => now()->addMinutes(15)->timestamp,
                'quote' => $snapshot,
            ]),
        ];
    }

    public function confirmedQuote(?array $parcel, int $destination, string $token, string $sessionId): ?array
    {
        if ($parcel === null) {
            return null;
        }

        try {
            $data = Crypt::decrypt($token);
        } catch (DecryptException) {
            return null;
        }

        if (($data['session'] ?? null) !== $sessionId
            || ($data['expires'] ?? 0) <= now()->timestamp
            || ($data['quote']['parcel'] ?? null) !== $parcel
            || ($data['quote']['store_id_finish'] ?? null) !== $destination) {
            return null;
        }

        return $data['quote'];
    }
}

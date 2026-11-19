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

    private const MAX_CHECKOUT_WEIGHT = 5;

    public function __construct(
        private readonly Evropochta $carrier,
        private readonly EvropochtaPackaging $packaging,
    ) {}

    public function parcel(array $cart): ?array
    {
        return $this->assess($cart)['parcel'];
    }

    /** @return array{parcel: ?array, message: ?string} */
    public function assess(array $cart): array
    {
        if (! config('services.evropochta.checkout_enabled') || ! $this->carrier->configured()) {
            return ['parcel' => null, 'message' => 'Европочта сейчас недоступна при оформлении.'];
        }

        if (count($cart) !== 1) {
            return ['parcel' => null, 'message' => 'Европочта при оформлении доступна для одной единицы товара.'];
        }

        $item = reset($cart);
        if ((int) ($item['quantity'] ?? 0) !== 1 || ($item['pricing_type'] ?? 'retail') !== 'retail') {
            return ['parcel' => null, 'message' => 'Европочта при оформлении доступна для одной единицы товара по розничной цене.'];
        }

        $product = Product::query()->active()->find($item['id'], ['id', 'shipping_parcel', 'specs', 'weight']);
        $dimensions = $product?->shipping_parcel ?? [];
        $weight = $dimensions['weight'] ?? null;
        if ($product && (! is_numeric($weight) || (float) $weight <= 0)) {
            $weight = $this->packaging->measurements($product)['item_weight'] ?? $product->weight;
        }

        if (is_numeric($weight) && round((float) $weight, 2) > self::MAX_CHECKOUT_WEIGHT) {
            return ['parcel' => null, 'message' => 'Европочта при оформлении доступна для посылок до '.self::MAX_CHECKOUT_WEIGHT.' кг. Этот товар превышает лимит по весу.'];
        }

        $parcel = ['product_id' => (int) $item['id'], 'store_id_start' => config('services.evropochta.checkout_origin')];
        foreach (['weight', 'length', 'width', 'height'] as $field) {
            $value = $dimensions[$field] ?? null;
            if (! is_numeric($value) || (float) $value < 0.01 || (float) $value > 250) {
                return ['parcel' => null, 'message' => 'Для Европочты при оформлении нужны подтверждённые вес и размеры товара в упаковке.'];
            }

            $parcel[$field] = round((float) $value, 2);
        }

        if (isset($dimensions['box']) && ($dimensions['weight_mode'] ?? null) === 'band') {
            $parcel['box'] = $dimensions['box'];
            $parcel['weight_mode'] = 'band';
        }

        try {
            if (! Evropochta::isOversize($parcel)) {
                return ['parcel' => $parcel, 'message' => null];
            }
        } catch (RuntimeException) {
            // Invalid dimensions also make the parcel ineligible for automatic checkout.
        }

        return ['parcel' => null, 'message' => 'Размеры упаковки выходят за пределы, доступные для Европочты при оформлении.'];
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

    public function estimate(array $parcel, int $destination): array
    {
        $branch = $this->destinations($parcel)[$destination] ?? null;
        if ($branch === null) {
            throw new RuntimeException('Выберите отделение Европочты в регионе.');
        }

        $rate = $this->carrier->quote($parcel + ['store_id_finish' => $destination]);
        $kopecks = (int) round($rate['total'] * 100);
        $price = intdiv($kopecks + 49, 50) * 50 / 100;
        return [
            'parcel' => $parcel,
            'store_id_finish' => $destination,
            'city' => preg_replace('/^(?:г\.|город)\s*/ui', '', $branch['city']),
            'address' => $branch['label'],
            'region' => $branch['region'],
            'carrier_price' => $rate['total'],
            'customer_price' => $price,
            'oversize' => $rate['oversize'],
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    public function quote(array $parcel, int $destination, string $sessionId): array
    {
        $snapshot = $this->estimate($parcel, $destination);

        return [
            'price' => $snapshot['customer_price'],
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

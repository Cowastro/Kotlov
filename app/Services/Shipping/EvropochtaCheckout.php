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

    public const COD_PAYMENT = 'evropochta_cod';

    public const MAX_CHECKOUT_WEIGHT = 30;

    private const PAYMENT_ON_RECEIPT_CATEGORIES = ['schetchiki-gaza', 'signalizatory-zagazovannosti'];

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

        if ($cart === []) {
            return ['parcel' => null, 'message' => 'Добавьте товары в корзину.'];
        }

        $quantities = [];
        foreach ($cart as $item) {
            if (($item['pricing_type'] ?? 'retail') !== 'retail') {
                return ['parcel' => null, 'message' => 'Европочта при оформлении доступна для розничных заказов.'];
            }

            $id = (int) ($item['id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($id < 1 || $quantity < 1) {
                return ['parcel' => null, 'message' => 'Проверьте состав корзины.'];
            }

            $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
        }

        $origin = ['store_id_start' => config('services.evropochta.checkout_origin')];
        if (array_sum($quantities) === 1) {
            return $this->assessSingle((int) array_key_first($quantities), $origin);
        }

        ksort($quantities, SORT_NUMERIC);
        $products = Product::query()->active()->notArchived()->whereKey(array_keys($quantities))
            ->get(['id', 'shipping_parcel'])->keyBy('id');
        $lines = [];
        $items = [];
        foreach ($quantities as $id => $quantity) {
            $saved = $products->get($id)?->shipping_parcel ?? [];
            $confirmed = $this->confirmedMeasurements($saved);
            if ($confirmed === null) {
                return ['parcel' => null, 'message' => 'Для общей коробки нужны подтверждённые вес и размеры всех товаров в упаковке.'];
            }

            $lines[] = ['quantity' => $quantity, 'parcel' => $confirmed + $saved];
            $items[] = ['product_id' => $id, 'quantity' => $quantity];
        }

        $box = $this->packaging->combine($lines, self::MAX_CHECKOUT_WEIGHT);
        if ($box === null || Evropochta::isOversize($box)) {
            return ['parcel' => null, 'message' => 'Товары не помещаются в одну доступную коробку Европочты с допустимым весом.'];
        }

        return ['parcel' => $origin + $box + ['items' => $items], 'message' => null];
    }

    private function assessSingle(int $id, array $origin): array
    {
        $product = Product::query()->active()->notArchived()->find($id, ['id', 'shipping_parcel', 'specs', 'weight']);
        $dimensions = $product?->shipping_parcel ?? [];
        $weight = $dimensions['weight'] ?? null;
        if ($product && (! is_numeric($weight) || (float) $weight <= 0)) {
            $weight = $this->packaging->measurements($product)['item_weight'] ?? $product->weight;
        }

        if (is_numeric($weight) && (float) $weight > self::MAX_CHECKOUT_WEIGHT) {
            return ['parcel' => null, 'message' => 'Европочта при оформлении доступна для посылок до '.self::MAX_CHECKOUT_WEIGHT.' кг. Этот товар превышает лимит по весу.'];
        }

        $confirmed = $this->confirmedMeasurements($dimensions);
        if ($confirmed === null) {
            return ['parcel' => null, 'message' => 'Для Европочты при оформлении нужны подтверждённые вес и размеры товара в упаковке.'];
        }

        $parcel = ['product_id' => $id] + $origin + $confirmed;
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

    private function confirmedMeasurements(array $dimensions): ?array
    {
        $measurements = [];
        foreach (['weight', 'length', 'width', 'height'] as $field) {
            $value = $dimensions[$field] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value) || $value < 0.01 || $value > 250) {
                return null;
            }

            $measurements[$field] = round((float) $value, 2);
        }

        return $measurements;
    }

    /** @return list<string> */
    public function paymentMethods(array $parcel): array
    {
        $products = isset($parcel['items']) ? array_column($parcel['items'], 'product_id') : [$parcel['product_id']];
        $paymentOnReceiptOnly = Product::query()
            ->whereKey($products)
            ->whereHas('category', fn ($category) => $category->whereIn('slug', self::PAYMENT_ON_RECEIPT_CATEGORIES))
            ->exists();

        return $paymentOnReceiptOnly ? [self::COD_PAYMENT] : [self::COD_PAYMENT, self::PAYMENT];
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

        $quote = [
            'price' => $snapshot['customer_price'],
            'token' => Crypt::encrypt([
                'session' => $sessionId,
                'expires' => now()->addMinutes(15)->timestamp,
                'quote' => $snapshot,
            ]),
        ];
        if (isset($parcel['items'])) {
            $quote['box'] = [
                'dimensions' => [$parcel['length'], $parcel['width'], $parcel['height']],
                'weight' => $parcel['weight'],
            ];
        }

        return $quote;
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

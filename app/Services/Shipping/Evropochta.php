<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Evropochta
{
    public function configured(): bool
    {
        return filled(config('services.evropochta.token'));
    }

    public function branches(): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Настройте ключ API Европочты на сервере.');
        }

        return Cache::remember('evropochta.branches.v1', now()->addHours(6), function (): array {
            try {
                $response = $this->request()->get('stores');
            } catch (ConnectionException) {
                throw new RuntimeException('Справочник отделений Европочты временно недоступен.');
            }

            $branches = collect($this->payload($response))
                ->filter(fn (mixed $branch): bool => is_array($branch) && (int) ($branch['type'] ?? 0) === 1
                    && ! empty($branch['id']) && ! empty($branch['ops_name']))
                ->sortBy(['city', 'street', 'house'])
                ->mapWithKeys(fn (array $branch): array => [
                    $branch['id'] => [
                        'label' => implode(', ', array_filter([
                            $branch['city'] ?? null,
                            $branch['street'] ?? null,
                            $branch['house'] ?? null,
                            isset($branch['ops_number']) ? '№'.$branch['ops_number'] : $branch['ops_name'],
                        ])),
                        'heavy' => filter_var($branch['is_large_weight_limit'] ?? false, FILTER_VALIDATE_BOOL),
                    ],
                ])->all();

            if ($branches === []) {
                throw new RuntimeException('Европочта не вернула отделения для пересылки.');
            }

            return $branches;
        });
    }

    public function quote(array $parcel): array
    {
        $weight = (float) ($parcel['weight'] ?? 0);
        if ($weight <= 0 || $weight > 50) {
            throw new RuntimeException('Допустимый вес упакованной посылки — от 0,01 до 50 кг.');
        }

        $sides = array_map(fn (string $side): int => (int) round((float) ($parcel[$side] ?? 0) * 100), ['length', 'width', 'height']);
        rsort($sides);
        [$longest, $middle, $shortest] = $sides;

        if ($middle < 1000 || $shortest < 1) {
            throw new RuntimeException('Укажите три размера. Две стороны посылки должны быть не короче 10 см.');
        }

        if ($longest > 25000 || array_sum($sides) > 35000) {
            throw new RuntimeException('Для юрлиц максимальная сторона — 250 см, сумма трёх сторон — 350 см.');
        }

        // Размеры в сотых долях сантиметра. Обхват считается поперёк длинной стороны.
        $oversize = $longest > 10500 || $longest + 2 * ($middle + $shortest) > 20000;

        $branches = $this->branches();
        $from = $branches[$parcel['store_id_start'] ?? 0] ?? null;
        $to = $branches[$parcel['store_id_finish'] ?? 0] ?? null;
        if (! $from || ! $to) {
            throw new RuntimeException('Выберите оба отделения из справочника Европочты.');
        }

        if ($weight > 30 && (! $from['heavy'] || ! $to['heavy'])) {
            throw new RuntimeException('Для веса свыше 30 кг оба отделения должны работать с тяжеловесными посылками.');
        }

        try {
            $response = $this->request()->asMultipart()->post('postal/payment/calculate', [
                'is_juristic' => 1,
                'delivery_type' => 1,
                'store_id_start' => $parcel['store_id_start'],
                'store_id_finish' => $parcel['store_id_finish'],
                'weight' => $weight,
                'is_oversize' => (int) $oversize,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Европочта не ответила на запрос тарифа.');
        }

        $result = $this->payload($response);
        $sender = $result['sender_pays'] ?? null;
        $receiver = $result['receiver_pays'] ?? null;
        if (! is_numeric($sender) || ! is_numeric($receiver) || $sender < 0 || $receiver < 0) {
            throw new RuntimeException('Европочта не вернула итоговую стоимость.');
        }

        return ['total' => round($sender + $receiver, 2), 'oversize' => $oversize];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://api-kassa.evropochta.by/api/external')
            ->acceptJson()
            ->withHeader('Token', config('services.evropochta.token'))
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(10);
    }

    private function payload(Response $response): array
    {
        $data = $response->json();

        if ($response->status() === 401 || $response->status() === 403) {
            throw new RuntimeException('Европочта отклонила доступ. Проверьте ключ и договор.');
        }

        if ($response->status() === 422) {
            throw new RuntimeException('Европочта не рассчитала тариф для этих отделений и параметров посылки.');
        }

        if (! $response->successful() || ! is_array($data)) {
            throw new RuntimeException("Европочта вернула некорректный ответ (HTTP {$response->status()}).");
        }

        return $data;
    }
}

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
    public const STATUS_CREATED = 0;

    public const LABEL_FORMATS = [
        'thermal' => '10 × 10 см',
        'address' => 'A5',
    ];

    public function configured(): bool
    {
        return filled(config('services.evropochta.token'));
    }

    public function branches(): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Настройте ключ API Европочты на сервере.');
        }

        return Cache::remember('evropochta.branches.v2', now()->addHours(6), function (): array {
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
                        'city' => trim((string) ($branch['city'] ?? '')),
                        'region' => trim((string) ($branch['region'] ?? '')),
                        'heavy' => filter_var($branch['is_large_weight_limit'] ?? false, FILTER_VALIDATE_BOOL),
                    ],
                ])->all();

            if ($branches === []) {
                throw new RuntimeException('Европочта не вернула отделения для пересылки.');
            }

            return $branches;
        });
    }

    public function estimate(array $parcel, int $destination): array
    {
        $branch = $this->branches()[$destination] ?? null;
        if ($branch === null) {
            throw new RuntimeException('Выберите отделение получения из справочника Европочты.');
        }

        $rate = $this->quote($parcel + ['store_id_finish' => $destination]);
        $customerPrice = (int) round($rate['total'] * 1.20, 0, PHP_ROUND_HALF_UP);

        return [
            'parcel' => $parcel,
            'store_id_finish' => $destination,
            'origin' => ['id' => (int) ($parcel['store_id_start'] ?? 0),
                'address' => $this->branches()[$parcel['store_id_start'] ?? 0]['label'] ?? null],
            'city' => preg_replace('/^(?:г\.|город)\s*/ui', '', $branch['city']),
            'address' => $branch['label'],
            'region' => $branch['region'],
            'carrier_price' => $rate['total'],
            'customer_price' => $customerPrice,
            'oversize' => $rate['oversize'],
            'quoted_at' => now()->toIso8601String(),
        ];
    }

    public function quote(array $parcel): array
    {
        $weight = (float) ($parcel['weight'] ?? 0);
        if ($weight <= 0 || $weight > 50) {
            throw new RuntimeException('Допустимый вес упакованной посылки — от 0,01 до 50 кг.');
        }

        $oversize = self::isOversize($parcel);

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

    public static function isOversize(array $parcel): bool
    {
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
        return $longest > 10500 || $longest + 2 * ($middle + $shortest) > 20000;
    }

    public function createShipment(array $data): string
    {
        try {
            $response = $this->request()->asJson()->post('postal/create', $data);
            $result = $response->json();
            $number = data_get($result, 'number');

            // The API returns the existing number on an external_id collision.
            if ($response->status() === 422
                && is_string($message = data_get($result, 'message'))
                && preg_match('/^33 Такой PostalItemExternalId уже существует:\s*(BY\d{12})\s*$/u', $message, $matches)) {
                $existing = $this->request()->get('postal/'.$matches[1]);
                if ($existing->successful()
                    && $existing->json('external_id') === $data['external_id']
                    && $existing->json('number') === $matches[1]) {
                    return $matches[1];
                }

                throw new EvropochtaShipmentException('Европочта уже приняла заявку. Подождите три минуты и повторите действие, чтобы получить трек-номер.');
            }
        } catch (ConnectionException) {
            throw new EvropochtaShipmentException('Европочта не подтвердила результат запроса. Подождите три минуты перед повторной попыткой.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new EvropochtaShipmentException('Европочта отклонила доступ. Проверьте ключ и договор.');
        }
        if ($response->status() === 422) {
            throw new EvropochtaShipmentException('Европочта отклонила заявку. Проверьте получателя, отделения и параметры посылки.');
        }
        if (! $response->successful() || ! is_string($number) || ! preg_match('/^BY\d{12}$/D', $number)) {
            throw new EvropochtaShipmentException('Европочта не подтвердила трек-номер. Подождите три минуты перед повторной попыткой.');
        }

        return $number;
    }

    /** @return array{code: int, status: string} */
    public function shipmentStatus(string $number): array
    {
        $this->validateShipmentNumber($number);

        try {
            $response = $this->request()->get("postal/{$number}");
        } catch (ConnectionException) {
            throw new EvropochtaShipmentException('Европочта не ответила на запрос статуса.');
        }

        return $this->statusFromResponse($response, $number, 'Не удалось подтвердить статус отправления.');
    }

    /** @return array{code: int, status: string} */
    public function cancelShipment(string $number): array
    {
        $this->validateShipmentNumber($number);

        try {
            $response = $this->request()->send('POST', "postal/{$number}/cancel");
        } catch (ConnectionException) {
            throw new EvropochtaShipmentException('Европочта не подтвердила отмену. Дождитесь обновления статуса.');
        }

        if ($response->status() === 422) {
            throw new EvropochtaShipmentException('Европочта отклонила отмену. Проверьте текущий статус заявки.');
        }

        $status = $this->statusFromResponse($response, $number, 'Европочта не подтвердила отмену. Дождитесь обновления статуса.');
        if ($status['code'] === self::STATUS_CREATED) {
            throw new EvropochtaShipmentException('Европочта пока показывает статус «Заявка создана». Дождитесь обновления статуса.');
        }

        return $status;
    }

    private function validateShipmentNumber(string $number): void
    {
        if (! preg_match('/^BY\d{12}$/D', $number)) {
            throw new EvropochtaShipmentException('Проверьте трек-номер Европочты.');
        }
    }

    /** @return array{code: int, status: string} */
    private function statusFromResponse(Response $response, string $number, string $error): array
    {
        if (in_array($response->status(), [401, 403], true)) {
            throw new EvropochtaShipmentException('Европочта отклонила доступ. Проверьте ключ и договор.');
        }
        if ($response->status() === 404) {
            throw new EvropochtaShipmentException('Европочта пока не нашла отправление. Проверьте статус позже.');
        }

        $code = $response->json('status.value');
        $name = $response->json('status.name');
        if (! $response->successful() || $response->json('number') !== $number || ! is_int($code) || ! is_string($name) || blank($name)) {
            throw new EvropochtaShipmentException($error);
        }

        return ['code' => $code, 'status' => $name];
    }

    /** @return list<array{time: string, status: string, detail: string}> */
    public function trackingHistory(string $number): array
    {
        try {
            $response = $this->request()->get('postal/history', ['number' => $number]);
        } catch (ConnectionException) {
            throw new EvropochtaShipmentException('Европочта не ответила на запрос отслеживания.');
        }

        if ($response->status() === 404) {
            throw new EvropochtaShipmentException('Отправление пока не найдено. После оформления подождите три минуты и проверьте снова.');
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new EvropochtaShipmentException('Европочта отклонила доступ. Проверьте ключ и договор.');
        }
        $events = $response->json();
        if (! $response->successful() || ! is_array($events) || ! array_is_list($events)) {
            throw new EvropochtaShipmentException('Не удалось получить историю. Проверьте трек-номер или повторите позже.');
        }

        return collect($events)
            ->filter(fn (mixed $event): bool => is_array($event) && is_string(data_get($event, 'status_to.name')))
            ->sortByDesc('created_at')
            ->map(fn (array $event): array => [
                'time' => is_string($event['created_at'] ?? null) ? $event['created_at'] : '',
                'status' => data_get($event, 'status_to.name'),
                'detail' => is_string($event['info'] ?? null) ? $event['info'] : '',
            ])->values()->all();
    }

    public function shipmentLabel(string $number, string $format): string
    {
        if (! isset(self::LABEL_FORMATS[$format]) || ! preg_match('/^BY\d{12}$/D', $number)) {
            throw new EvropochtaShipmentException('Проверьте трек-номер и формат ярлыка.');
        }

        try {
            $response = $this->request()->accept('application/pdf')->get("label/{$format}/{$number}");
        } catch (ConnectionException) {
            throw new EvropochtaShipmentException('Европочта не ответила на запрос ярлыка. Повторите попытку позже.');
        }

        if ($response->status() === 404) {
            throw new EvropochtaShipmentException('Ярлык ещё готовится. Повторите запрос через три минуты после оформления.');
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new EvropochtaShipmentException('Европочта отклонила доступ. Проверьте ключ и договор.');
        }
        if (! $response->successful() || ! str_starts_with($response->body(), '%PDF-')) {
            throw new EvropochtaShipmentException('Европочта пока не вернула PDF ярлыка. Повторите попытку позже.');
        }

        return $response->body();
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

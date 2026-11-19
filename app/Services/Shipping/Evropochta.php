<?php

namespace App\Services\Shipping;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Evropochta
{
    public function configured(): bool
    {
        return filled(config('services.evropochta.token'));
    }

    public function quote(array $parcel): float
    {
        $result = $this->post('payment/calculate', $parcel + ['is_juristic' => 1], true);
        $price = $result['shipment_price'] ?? null;

        if (! is_numeric($price) || (float) $price < 0) {
            throw new RuntimeException('Европочта не вернула базовую стоимость пересылки.');
        }

        return (float) $price;
    }

    public function create(array $parcel): string
    {
        $result = $this->post('create', $parcel + ['is_auto_delivery' => false]);
        $number = $result['number'] ?? null;

        if (! is_string($number) || blank($number) || strlen($number) > 100) {
            throw new RuntimeException('Европочта не вернула трек-номер. Проверьте наличие заявки в кабинете перевозчика.');
        }

        return $number;
    }

    private function post(string $path, array $parcel, bool $asForm = false): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Настройте ключ API Европочты на сервере.');
        }

        $request = Http::baseUrl('https://api-kassa.evropochta.by/api/external/postal')
            ->acceptJson()
            ->withHeader('Token', config('services.evropochta.token'))
            ->connectTimeout(3)
            ->timeout(10);

        if ($asForm) {
            $request->asMultipart();
        }

        try {
            $response = $request->post($path, $parcel + ['delivery_type' => 1]);
        } catch (ConnectionException) {
            throw new RuntimeException('Нет ответа от Европочты. Если создавали заявку, проверьте её наличие в кабинете перевозчика.');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException("Ответ Европочты: HTTP {$response->status()}. Если создавали заявку, проверьте её наличие в кабинете перевозчика.");
        }

        return $response->json();
    }
}

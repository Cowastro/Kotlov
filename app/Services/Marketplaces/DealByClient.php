<?php

namespace App\Services\Marketplaces;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;

class DealByClient
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.deal_by.token')) !== '';
    }

    /** @return array<string, mixed> */
    public function listProducts(int $limit = 1, ?int $lastId = null): array
    {
        return $this->get('products/list', array_filter([
            'limit' => max(1, min(100, $limit)),
            'last_id' => $lastId,
        ], fn (mixed $value): bool => $value !== null));
    }

    /**
     * @param  array<string, scalar>  $filters
     * @return array<string, mixed>
     */
    public function listOrders(int $limit = 1, ?int $lastId = null, array $filters = []): array
    {
        $allowedFilters = array_intersect_key($filters, array_flip([
            'status', 'date_from', 'date_to', 'last_modified_from', 'last_modified_to', 'sort_dir',
        ]));

        return $this->get('orders/list', array_filter(array_merge($allowedFilters, [
            'limit' => max(1, min(100, $limit)),
            'last_id' => $lastId,
        ]), fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @param  array<int, int|string>  $orderIds
     * @return array<string, mixed>
     */
    public function setOrderStatus(array $orderIds, string $status, ?string $cancellationText = null): array
    {
        $body = [
            'status' => $status,
            'ids' => array_values($orderIds),
        ];

        if ($status === 'canceled') {
            $body['cancellation_reason'] = 'another';
            $body['cancellation_text'] = $cancellationText ?: 'Отменено менеджером KOTLOV.BY';
        }

        return $this->post('orders/set_status', $body);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return array<string, mixed>
     */
    public function editProducts(array $products): array
    {
        return $this->post('products/edit', $products);
    }

    /** @param array<string, scalar> $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->request()
            ->get($this->url($path), $query)
            ->throw();

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Deal.by вернул ответ не в формате JSON.');
        }

        if (filled($payload['error'] ?? null)) {
            throw new RuntimeException('Deal.by API отклонил параметры запроса: '.trim((string) $payload['error']));
        }

        return $payload;
    }

    /** @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = $this->request()
            ->post($this->url($path), $body)
            ->throw();

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Deal.by вернул ответ не в формате JSON.');
        }

        if (filled($payload['error'] ?? null) || filled($payload['errors'] ?? null)) {
            throw new RuntimeException('Deal.by не принял новый статус заказа.');
        }

        return $payload;
    }

    private function request(): PendingRequest
    {
        $token = trim((string) config('services.deal_by.token'));
        if ($token === '') {
            throw new LogicException('DEAL_BY_API_TOKEN не настроен.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->connectTimeout((int) config('services.deal_by.connect_timeout', 5))
            ->timeout((int) config('services.deal_by.timeout', 20))
            ->retry(2, 500, throw: false);
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.deal_by.base_url'), '/');
        if ($baseUrl === '') {
            throw new LogicException('DEAL_BY_API_BASE_URL не настроен.');
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }
}

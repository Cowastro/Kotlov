<?php

namespace App\Services\Telephony;

use App\Models\TelephonyConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class A1OpenApiClient
{
    /** @return list<array<string, mixed>> */
    public function employees(TelephonyConnection $connection): array
    {
        return $this->get($connection, '/employees', ['company_id' => $connection->company_id]);
    }

    /** @return list<array<string, mixed>> */
    public function callHistory(TelephonyConnection $connection, int $start, int $end): array
    {
        return $this->get($connection, '/cdr', [
            'company_id' => $connection->company_id,
            'start' => $start,
            'end' => $end,
        ]);
    }

    /** @return array<string, mixed> */
    public function startOutgoingCall(TelephonyConnection $connection, string $source, string $destination): array
    {
        return $this->request($connection, 'post', '/call/outgoing', [
            'company_id' => $connection->company_id,
            'source' => $source,
            'destination' => $destination,
        ])->json() ?? [];
    }

    /** @return list<array<string, mixed>> */
    private function get(TelephonyConnection $connection, string $path, array $query): array
    {
        $data = $this->request($connection, 'get', $path, $query)->json();

        if (! is_array($data)) {
            throw new RuntimeException('ВАТС A1 вернула ответ неизвестного формата.');
        }

        if (array_is_list($data)) {
            return $data;
        }

        foreach (['content', 'data', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }

        return [$data];
    }

    private function request(TelephonyConnection $connection, string $method, string $path, array $query, bool $canRefresh = true): Response
    {
        $token = $this->accessToken($connection);
        $response = $this->http()
            ->withHeaders(['Authentication' => $token])
            ->send(strtoupper($method), $this->url($path), ['query' => $query]);

        if ($response->status() === 403 && $canRefresh) {
            $connection->forceFill(['access_token' => null, 'access_token_expires_at' => null])->save();

            return $this->request($connection->fresh(), $method, $path, $query, false);
        }

        $response->throw();

        return $response;
    }

    private function accessToken(TelephonyConnection $connection): string
    {
        if ($connection->access_token && $connection->access_token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        $canRefresh = $connection->refresh_token
            && $connection->refresh_token_expires_at?->isAfter(now()->addMinute());
        $request = $this->http();

        if ($canRefresh) {
            $response = $request
                ->withHeaders(['Authorization' => $connection->refresh_token])
                ->put($this->url('/auth/tokens'));
        } else {
            if (! $connection->company_id || ! $connection->primary_api_key) {
                throw new RuntimeException('Не заполнены ID клиента и первичный код A1.');
            }

            $response = $request
                ->withHeaders(['Authorization' => base64_encode($connection->company_id.':'.$connection->primary_api_key)])
                ->get($this->url('/auth/tokens'));
        }

        $response->throw();
        $tokens = $response->json();
        $accessToken = data_get($tokens, 'access_token') ?? data_get($tokens, 'accessToken');
        $refreshToken = data_get($tokens, 'refresh_token') ?? data_get($tokens, 'refreshToken');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('ВАТС A1 не вернула access_token.');
        }

        $connection->update([
            'access_token' => $accessToken,
            'refresh_token' => is_string($refreshToken) ? $refreshToken : $connection->refresh_token,
            'access_token_expires_at' => now()->addHours(23),
            'refresh_token_expires_at' => is_string($refreshToken) ? now()->addDays(6)->addHours(23) : $connection->refresh_token_expires_at,
            'last_error' => null,
        ]);

        return $accessToken;
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->connectTimeout(5)->timeout(20);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('telephony.base_url'), '/').'/'.ltrim($path, '/');
    }
}

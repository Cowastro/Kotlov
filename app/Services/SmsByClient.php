<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SmsByClient
{
    /** @return array{code: string, sms_id: string|null} */
    public function sendVerificationCode(string $phone): array
    {
        $config = config('services.sms_by');

        if (! ($config['enabled'] ?? false)) {
            throw new RuntimeException('SMS verification is disabled.');
        }

        foreach (['token', 'password_object_id', 'alphaname_id'] as $key) {
            if (blank($config[$key] ?? null)) {
                throw new RuntimeException('SMS verification is not configured.');
            }
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(max(5, (int) ($config['timeout'] ?? 10)))
                ->get(rtrim((string) $config['base_url'], '/').'/api/v1/sendSmsMessageWithCode', [
                    'token' => $config['token'],
                    'password_object_id' => $config['password_object_id'],
                    'phone' => $phone,
                    'message' => $config['message'],
                    'alphaname_id' => $config['alphaname_id'],
                ]);

            $payload = $response->json();

            if (! $response->successful()
                || ($payload['status'] ?? null) !== 'ok'
                || blank($payload['code'] ?? null)) {
                throw new RuntimeException('SMS provider rejected the request.');
            }

            return [
                'code' => (string) $payload['code'],
                'sms_id' => isset($payload['sms_id']) ? (string) $payload['sms_id'] : null,
            ];
        } catch (Throwable) {
            // Do not attach the original exception: its URL may contain the API token.
            throw new RuntimeException('SMS service is temporarily unavailable.');
        }
    }
}

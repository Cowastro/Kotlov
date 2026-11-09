<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class SmsByClient
{
    public const CODE_MESSAGE = 'Код KOTLOV: %CODE%';

    /** @return array{code: string, sms_id: string|null, parts: int|null} */
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

        return $this->sendCode($phone, $config['alphaname_id'], $config['message']);
    }

    public function diagnostics(): array
    {
        $config = config('services.sms_by');
        $balance = $this->request('getBalance');
        $senders = $this->request('getAlphanames');
        $senderId = $config['alphaname_id'] ?? null;
        if (blank($senderId)) {
            $senderId = array_search('KOTLOV.BY', $senders, true);
        }
        $sender = preg_match('/^\d+$/D', (string) $senderId) ? ($senders[$senderId] ?? null) : null;

        $objectId = $config['password_object_id'] ?? null;
        $code = filled($objectId) ? $this->request('getPasswordObject', ['id' => $objectId]) : [];
        $code = $code['result'] ?? $code;

        return [
            'balance' => $balance['result'][0]['balance'] ?? null,
            'currency' => $balance['currency'] ?? null,
            'sender' => is_string($sender) ? $sender : null,
            'sender_id' => is_string($sender) ? (string) $senderId : null,
            'six_digits' => ($code['type_id'] ?? null) === 'numbers' && (int) ($code['len'] ?? 0) === 6,
        ];
    }

    /** @return array{code: string, sms_id: string|null, parts: int|null} */
    public function sendTestCode(string $phone): array
    {
        $check = $this->diagnostics();
        if ($check['sender_id'] === null || ! $check['six_digits'] || (float) $check['balance'] <= 0) {
            throw new RuntimeException('Для теста нужны положительный баланс, доступный отправитель и шестизначный цифровой код.');
        }

        return $this->sendCode($phone, $check['sender_id'], self::CODE_MESSAGE);
    }

    public function deliveryStatus(string $smsId): string
    {
        $payload = $this->request('checkSMS', ['sms_id' => $smsId]);
        if ((string) ($payload['sms_id'] ?? '') !== $smsId) {
            throw new RuntimeException('SMS.BY пока не вернул статус сообщения.');
        }

        return ! empty($payload['delivered']) ? 'Доставлено' : (! empty($payload['sent']) ? 'Отправлено оператору' : 'Ожидает отправки');
    }

    /** @return array{code: string, sms_id: string|null, parts: int|null} */
    private function sendCode(string $phone, string $senderId, string $message): array
    {
        $sample = str_replace('%CODE%', '000000', $message);
        if (! str_contains($message, '%CODE%') || strlen(mb_convert_encoding($sample, 'UTF-16BE', 'UTF-8')) > 140) {
            throw new RuntimeException('Шаблон кода должен содержать %CODE% и помещаться в одно SMS.');
        }

        $payload = $this->request('sendSmsMessageWithCode', [
            'password_object_id' => config('services.sms_by.password_object_id'),
            'phone' => $phone,
            'message' => $message,
            'alphaname_id' => $senderId,
        ]);

        if (strtolower((string) ($payload['status'] ?? '')) !== 'ok'
            || ! preg_match('/^\d{6}$/D', (string) ($payload['code'] ?? ''))) {
            throw new RuntimeException('SMS.BY не подтвердил отправку шестизначного кода.');
        }

        return [
            'code' => (string) $payload['code'],
            'sms_id' => isset($payload['sms_id']) ? (string) $payload['sms_id'] : null,
            'parts' => isset($payload['parts']) ? (int) $payload['parts'] : null,
        ];
    }

    private function request(string $method, array $parameters = []): array
    {
        $config = config('services.sms_by');
        if (blank($config['token'] ?? null)) {
            throw new RuntimeException('Ключ SMS.BY на сервере не настроен.');
        }

        try {
            $response = Http::acceptJson()
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(max(5, (int) ($config['timeout'] ?? 10)))
                ->get(rtrim((string) $config['base_url'], '/').'/api/v1/'.$method, ['token' => $config['token']] + $parameters);
            $payload = $response->json();
        } catch (Throwable) {
            throw new RuntimeException('SMS.BY недоступен. Если запрос уже отправлен, проверьте журнал сервиса перед повтором.');
        }

        if (! $response->successful() || ! is_array($payload) || in_array(strtolower((string) ($payload['status'] ?? '')), ['error', 'fail', 'failed'], true)) {
            throw new RuntimeException('SMS.BY отклонил запрос (HTTP '.$response->status().').');
        }

        return $payload;
    }
}

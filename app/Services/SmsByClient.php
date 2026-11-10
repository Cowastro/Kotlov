<?php

namespace App\Services;

use Illuminate\Support\Arr;
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

    /**
     * Sender ids by name for mailings: SMS alpha-name and Viber name «KOTLOV.BY» (or the ids from the config).
     *
     * @return array{sms: ?string, viber: ?string}
     */
    public function mailingSenders(): array
    {
        $config = config('services.sms_by');
        $pick = function (string $method, ?string $configured): ?string {
            $names = $this->request($method);
            $names = is_array($names['result'] ?? null) ? $names['result'] : $names;
            if (filled($configured)) {
                return array_key_exists((string) $configured, $names) ? (string) $configured : null;
            }
            $id = array_search(config('services.sms_by.sender_name', 'KOTLOV.BY'), $names, true);

            return $id === false ? null : (string) $id;
        };

        return [
            'sms' => $pick('getAlphanames', $config['alphaname_id'] ?? null),
            'viber' => $pick('getVibernames', $config['vibername_id'] ?? null),
        ];
    }

    /**
     * One Viber message; with $smsAlphanameId set SMS.BY sends it as SMS when Viber does not deliver within $forwardMinutes.
     *
     * @return string provider message id
     */
    public function sendViber(string $phone, string $viberId, string $text, ?string $buttonCaption = null, ?string $buttonLink = null,
        ?string $smsAlphanameId = null, int $forwardMinutes = 60): string
    {
        $button = filled($buttonCaption) && filled($buttonLink);
        $payload = $this->request('sendQuickViberMessage', array_filter([
            'vibername_id' => $viberId,
            'phone' => $phone,
            'type_message' => $button ? 'BUTTON' : 'TEXT',
            'message' => $text,
            'button' => $button ? $buttonCaption : null,
            'button_link' => $button ? $buttonLink : null,
            'forwarding_message' => $smsAlphanameId ? 1 : null,
            'forwarding_time' => $smsAlphanameId ? max(5, min(60, $forwardMinutes)) : null,
            'alphaname_id' => $smsAlphanameId,
        ], fn ($v): bool => $v !== null), 'v2', true);
        $id = $payload['result']['message_id'] ?? $payload['message_id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException('SMS.BY не вернул номер Viber-сообщения.');
        }

        return (string) $id;
    }

    /** @return string provider SMS id */
    public function sendSms(string $phone, string $alphanameId, string $text): string
    {
        $payload = $this->request('sendQuickSMS', ['phone' => $phone, 'message' => $text, 'alphaname_id' => $alphanameId], 'v1', true);
        $id = $payload['sms_id'] ?? $payload['result']['sms_id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw new RuntimeException('SMS.BY не вернул номер SMS.');
        }

        return (string) $id;
    }

    /** @return array{status: string, raw: string} status: sent|delivered|read|failed */
    public function viberStatus(string $messageId): array
    {
        $payload = $this->request('getViberDeliveryData', ['message_id' => $messageId], 'v2', true);
        $raw = strtoupper((string) ($payload['result']['status'] ?? $payload['status'] ?? ''));

        return ['status' => self::mapStatus($raw), 'raw' => $raw];
    }

    /** @return array{status: string, raw: string} */
    public function smsStatus(string $smsId): array
    {
        $payload = $this->request('checkSMS', ['sms_id' => $smsId]);
        if (! empty($payload['delivered'])) {
            return ['status' => 'delivered', 'raw' => 'DELIVERED'];
        }
        $raw = strtoupper((string) ($payload['status'] ?? (! empty($payload['sent']) ? 'SENT' : 'NEW')));

        return ['status' => self::mapStatus($raw), 'raw' => $raw];
    }

    /** SMS.BY status word → ours. */
    public static function mapStatus(string $raw): string
    {
        $raw = strtoupper($raw);

        return match (true) {
            in_array($raw, ['READ', 'SEEN', 'CLICKED'], true) => 'read',
            $raw === 'DELIVERED' => 'delivered',
            (bool) preg_match('/ERROR|FAIL|UNDELIV|EXPIRED|REJECT|BLOCK|NOT_|INVALID/', $raw) => 'failed',
            default => 'sent',
        };
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

    private function request(string $method, array $parameters = [], string $version = 'v1', bool $post = false): array
    {
        $config = config('services.sms_by');
        if (blank($config['token'] ?? null)) {
            throw new RuntimeException('Ключ SMS.BY на сервере не настроен.');
        }

        try {
            $client = Http::acceptJson()
                ->withoutRedirecting()
                ->connectTimeout(5)
                ->timeout(max(5, (int) ($config['timeout'] ?? 10)));
            $url = rtrim((string) $config['base_url'], '/').'/api/'.$version.'/'.$method;
            $response = $post
                ? $client->asForm()->post($url, ['token' => $config['token']] + $parameters)
                : $client->get($url, ['token' => $config['token']] + $parameters);
            $payload = $response->json();
        } catch (Throwable) {
            throw new RuntimeException('SMS.BY недоступен. Если запрос уже отправлен, проверьте журнал сервиса перед повтором.');
        }

        if (! $response->successful() || ! is_array($payload) || in_array(strtolower((string) ($payload['status'] ?? '')), ['error', 'fail', 'failed'], true)) {
            $reason = is_array($payload) ? ($payload['message'] ?? $payload['error'] ?? $payload['errors'] ?? null) : null;
            $reason = is_array($reason) ? implode('; ', array_filter(array_map(fn ($r) => is_scalar($r) ? (string) $r : null, Arr::flatten($reason)))) : (string) $reason;

            throw new RuntimeException('SMS.BY отклонил запрос (HTTP '.$response->status().')'.($reason !== '' ? ': '.mb_substr(strip_tags($reason), 0, 150) : '').'.');
        }

        return $payload;
    }
}

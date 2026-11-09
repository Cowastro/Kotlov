<?php

namespace App\Services;

use App\Models\LeadChannel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class LeadChannelApi
{
    /** @return array{state: string, provider_message_id?: string} */
    public function send(LeadChannel $channel, string $recipient, string $body): array
    {
        [$url, $headers, $payload, $idPath] = match ($channel->provider) {
            'telegram' => [
                $this->telegramUrl($channel, 'sendMessage'), [],
                ['chat_id' => $recipient, 'text' => $body], 'result.message_id',
            ],
            'viber' => [
                'https://chatapi.viber.com/pa/send_message', ['X-Viber-Auth-Token' => $channel->access_token],
                ['receiver' => $recipient, 'type' => 'text', 'text' => $body], 'message_token',
            ],
            'whatsapp' => [
                $this->metaUrl($channel, 'graph.facebook.com'), ['Authorization' => 'Bearer '.$channel->access_token],
                ['messaging_product' => 'whatsapp', 'to' => $recipient, 'type' => 'text', 'text' => ['body' => $body]],
                'messages.0.id',
            ],
            'instagram' => [
                $this->metaUrl($channel, 'graph.instagram.com'), ['Authorization' => 'Bearer '.$channel->access_token],
                ['recipient' => ['id' => $recipient], 'message' => ['text' => $body]], 'message_id',
            ],
        };

        try {
            $response = Http::timeout(10)->connectTimeout(4)->withoutRedirecting()->withHeaders($headers)->post($url, $payload);
        } catch (ConnectionException) {
            return ['state' => 'unknown'];
        }

        if ($response->serverError()) {
            return ['state' => 'unknown'];
        }

        $payload = json_decode($response->body(), true, flags: JSON_BIGINT_AS_STRING);
        $id = data_get($payload, $idPath);
        if ($response->successful() && filled($id)) {
            return ['state' => 'sent', 'provider_message_id' => (string) $id];
        }

        $rejected = $response->clientError()
            || data_get($payload, 'ok') === false
            || ($channel->provider === 'viber' && is_numeric(data_get($payload, 'status')) && (int) $payload['status'] !== 0);

        return ['state' => $rejected ? 'failed' : 'unknown'];
    }

    public function registerWebhook(LeadChannel $channel): bool
    {
        $http = Http::timeout(15)->connectTimeout(4)->withoutRedirecting();
        try {
            if ($channel->provider === 'telegram') {
                $response = $http->post($this->telegramUrl($channel, 'setWebhook'), [
                    'url' => $channel->webhookUrl(),
                    'secret_token' => $channel->webhook_secret,
                    'allowed_updates' => ['message', 'edited_message'],
                ]);

                return $response->successful() && $response->json('ok') === true;
            }

            $response = $http->withHeaders(['X-Viber-Auth-Token' => $channel->access_token])
                ->post('https://chatapi.viber.com/pa/set_webhook', [
                    'url' => $channel->webhookUrl(),
                    'event_types' => [],
                    'send_name' => true,
                    'send_photo' => false,
                ]);

            return $response->successful() && $response->json('status') === 0;
        } catch (ConnectionException) {
            return false;
        }
    }

    private function telegramUrl(LeadChannel $channel, string $method): string
    {
        return 'https://api.telegram.org/bot'.$channel->access_token.'/'.$method;
    }

    private function metaUrl(LeadChannel $channel, string $host): string
    {
        return 'https://'.$host.'/'.$channel->api_version.'/'.rawurlencode($channel->account_id).'/messages';
    }
}

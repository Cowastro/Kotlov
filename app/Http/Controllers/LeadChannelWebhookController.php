<?php

namespace App\Http\Controllers;

use App\Models\LeadChannel;
use App\Services\LeadInbox;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LeadChannelWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, LeadInbox $inbox): Response
    {
        $channel = LeadChannel::settings($provider);
        abort_unless($channel->acceptsMessages(), 404);

        if ($request->isMethod('GET')) {
            $mode = $request->query('hub.mode', $request->query('hub_mode'));
            $token = $request->query('hub.verify_token', $request->query('hub_verify_token'));
            $challenge = $request->query('hub.challenge', $request->query('hub_challenge'));
            abort_unless($channel->usesMeta() && $mode === 'subscribe' && is_string($challenge) && is_string($token)
                && filled($channel->verify_token) && hash_equals($channel->verify_token, $token), 404);

            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        $content = $request->getContent();
        $meta = $channel->usesMeta();
        $signature = $request->header($meta ? 'X-Hub-Signature-256' : 'X-Viber-Content-Signature');
        $expected = ($meta ? 'sha256=' : '').hash_hmac(
            'sha256', $content, $meta ? $channel->webhook_secret : $channel->access_token,
        );
        abort_unless(is_string($signature) && hash_equals($expected, $signature), 404);

        $payload = json_decode($content, true, flags: JSON_BIGINT_AS_STRING);
        abort_unless(is_array($payload), 400);

        match ($provider) {
            'viber' => $this->receiveViber($channel, $payload, $inbox),
            'instagram' => $this->receiveInstagram($channel, $payload, $inbox),
            'whatsapp' => $this->receiveWhatsApp($channel, $payload, $inbox),
        };

        return response()->json(['ok' => true]);
    }

    private function receiveViber(LeadChannel $channel, array $payload, LeadInbox $inbox): void
    {
        if (($payload['event'] ?? null) !== 'message') {
            return;
        }

        $this->receive($channel, $inbox, data_get($payload, 'sender.id'), $payload['message_token'] ?? null,
            data_get($payload, 'message.text'), data_get($payload, 'sender.name'));
    }

    private function receiveInstagram(LeadChannel $channel, array $payload, LeadInbox $inbox): void
    {
        if (($payload['object'] ?? null) !== 'instagram') {
            return;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            if ((string) data_get($entry, 'id') !== $channel->account_id) {
                continue;
            }

            foreach ((array) data_get($entry, 'messaging', []) as $event) {
                if (data_get($event, 'message.is_echo') || data_get($event, 'message.is_self')
                    || data_get($event, 'message.is_deleted') || (string) data_get($event, 'sender.id') === $channel->account_id) {
                    continue;
                }

                $this->receive($channel, $inbox, data_get($event, 'sender.id'), data_get($event, 'message.mid'),
                    data_get($event, 'message.text'));
            }
        }
    }

    private function receiveWhatsApp(LeadChannel $channel, array $payload, LeadInbox $inbox): void
    {
        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) data_get($entry, 'changes', []) as $change) {
                if (data_get($change, 'field') !== 'messages'
                    || (string) data_get($change, 'value.metadata.phone_number_id') !== $channel->account_id) {
                    continue;
                }

                $contacts = collect((array) data_get($change, 'value.contacts', []))->keyBy('wa_id');
                foreach ((array) data_get($change, 'value.messages', []) as $message) {
                    $sender = data_get($message, 'from');
                    $phone = is_string($sender) && preg_match('/^\d{6,15}$/D', $sender) ? '+'.$sender : null;

                    $text = data_get($message, 'text.body')
                        ?? data_get($message, 'image.caption')
                        ?? data_get($message, 'video.caption')
                        ?? data_get($message, 'document.caption');
                    $this->receive($channel, $inbox, $sender, data_get($message, 'id'), $text,
                        data_get($contacts->get(is_string($sender) ? $sender : ''), 'profile.name'), $phone);
                }
            }
        }
    }

    private function receive(
        LeadChannel $channel,
        LeadInbox $inbox,
        mixed $sender,
        mixed $event,
        mixed $text,
        mixed $name = null,
        ?string $phone = null,
    ): void {
        $maxSenderLength = $channel->provider === 'viber' ? 64 : 128;
        if (! (is_string($sender) || is_int($sender)) || blank((string) $sender) || strlen((string) $sender) > $maxSenderLength
            || ! (is_string($event) || is_int($event)) || blank((string) $event) || strlen((string) $event) > 255) {
            return;
        }

        $inbox->receive(
            $channel,
            (string) $sender,
            (string) $event,
            is_string($text) && filled($text) ? trim($text) : '[Сообщение без текста]',
            is_string($name) && filled($name) ? $name : LeadChannel::LABELS[$channel->provider].' · '.$sender,
            $phone,
        );
    }
}

<?php

namespace App\Services;

use App\Models\ContactRequest;
use App\Models\LeadChannel;
use App\Models\LeadMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadInbox
{
    public function __construct(
        private readonly ContactRequestNotifier $notifier,
        private readonly LeadChannelApi $api,
    ) {}

    /** @return array{request: ContactRequest, changed: bool} */
    public function receive(
        LeadChannel $channel,
        string $sender,
        string $event,
        string $body,
        string $name,
        ?string $phone = null,
        ?string $notes = null,
    ): array {
        $externalId = $channel->conversationPrefix().($channel->provider === 'viber' ? bin2hex($sender) : $sender);
        $eventKey = hash('sha256', $event);
        $body = Str::limit($body, 12000);

        $result = DB::transaction(function () use ($channel, $externalId, $event, $eventKey, $body, $name, $phone, $notes): array {
            $identity = ['external_source' => $channel->externalSource(), 'external_id' => $externalId];

            ContactRequest::query()->toBase()->upsert([
                ...$identity,
                'source' => $channel->source(),
                'name' => Str::limit($name, 255, ''),
                'status' => 'new',
                'phone' => $phone,
                'message' => '',
                'admin_notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ], array_keys($identity), ['external_id']);

            $lead = ContactRequest::query()->where($identity)->lockForUpdate()->firstOrFail();

            if ($lead->last_external_event_id === $event || $lead->leadMessages()->where('event_key', $eventKey)->exists()) {
                return ['request' => $lead, 'changed' => false];
            }

            $this->preserveOriginalMessage($lead);
            $lead->leadMessages()->create([
                'direction' => 'incoming',
                'state' => 'received',
                'event_key' => $eventKey,
                'body' => $body,
            ]);

            $lead->update([
                'message' => Str::limit(trim($lead->message."\n\n".$body), 12000),
                'phone' => $lead->phone ?: $phone,
                'status' => in_array($lead->status, ['done', 'cancelled', 'waiting'], true) ? 'new' : $lead->status,
                'last_external_event_id' => $event,
            ]);

            return ['request' => $lead, 'changed' => true];
        }, 3);

        if ($result['changed']) {
            $this->notifier->notify($result['request']);
        }

        return $result;
    }

    public function replyChannel(ContactRequest $lead): ?LeadChannel
    {
        $provider = $lead->external_source === 'telegram_bot' ? 'telegram' : $lead->external_source;
        if (! isset(LeadChannel::LABELS[$provider])) {
            return null;
        }

        $channel = LeadChannel::settings($provider);
        if (! $channel->acceptsMessages() || blank($channel->access_token)
            || ! str_starts_with((string) $lead->external_id, $channel->conversationPrefix())) {
            return null;
        }

        return $channel;
    }

    public function reply(ContactRequest $lead, int $userId, string $body, string $sendKey): ?LeadMessage
    {
        $channel = $this->replyChannel($lead);
        if (! $channel) {
            return null;
        }

        $message = DB::transaction(function () use ($lead, $userId, $body, $sendKey): LeadMessage {
            $lockedLead = ContactRequest::query()->lockForUpdate()->findOrFail($lead->id);
            $this->preserveOriginalMessage($lockedLead);

            return $lockedLead->leadMessages()->firstOrCreate(['send_key' => $sendKey, 'user_id' => $userId], [
                'direction' => 'outgoing',
                'state' => 'pending',
                'body' => $body,
            ]);
        }, 3);

        if (! $message->wasRecentlyCreated) {
            return $message;
        }

        $recipient = substr($lead->external_id, strlen($channel->conversationPrefix()));
        $message->update($this->api->send($channel, $channel->provider === 'viber' ? hex2bin($recipient) : $recipient, $body));
        $lead->touch();

        return $message;
    }

    private function preserveOriginalMessage(ContactRequest $lead): void
    {
        if (filled($lead->message) && ! $lead->leadMessages()->exists()) {
            $lead->leadMessages()->create([
                'direction' => 'legacy',
                'state' => 'received',
                'event_key' => $lead->last_external_event_id ? hash('sha256', $lead->last_external_event_id) : null,
                'body' => $lead->message,
            ]);
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LeadChannel extends Model
{
    public const LABELS = [
        'telegram' => 'Telegram',
        'viber' => 'Viber',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
    ];

    protected $primaryKey = 'provider';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'provider', 'enabled', 'account_id', 'api_version', 'access_token',
        'webhook_secret', 'verify_token', 'public_url',
    ];

    protected $hidden = ['access_token', 'webhook_secret', 'verify_token'];

    protected $casts = [
        'enabled' => 'boolean',
        'access_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'verify_token' => 'encrypted',
    ];

    public static function settings(string $provider): self
    {
        $channel = self::find($provider);
        if ($channel) {
            return $channel;
        }

        $channel = new self(['provider' => $provider]);
        if ($provider === 'telegram') {
            $channel->fill([
                'enabled' => filled(config('services.telegram.leads_webhook_secret')),
                'access_token' => config('services.telegram.leads_bot_token'),
                'webhook_secret' => config('services.telegram.leads_webhook_secret'),
            ]);
        }

        return $channel;
    }

    public function usesMeta(): bool
    {
        return in_array($this->provider, ['instagram', 'whatsapp'], true);
    }

    public function acceptsMessages(): bool
    {
        return $this->enabled && filled(match ($this->provider) {
            'viber' => $this->access_token,
            default => $this->webhook_secret,
        });
    }

    public function externalSource(): string
    {
        return $this->provider === 'telegram' ? 'telegram_bot' : $this->provider;
    }

    public function source(): string
    {
        return $this->provider === 'telegram' ? 'telegram_channel' : $this->provider;
    }

    public function webhookUrl(): string
    {
        $path = $this->provider === 'telegram'
            ? route('integrations.telegram.leads.webhook', absolute: false)
            : route('integrations.leads.webhook', $this->provider, absolute: false);

        return rtrim(config('app.url'), '/').$path;
    }

    public function conversationPrefix(): string
    {
        if ($this->provider === 'telegram') {
            $botId = strstr((string) $this->access_token, ':', true);
            $legacyBotId = strstr((string) config('services.telegram.leads_bot_token'), ':', true);

            return ! $this->exists || ($botId && $botId === $legacyBotId) ? 'chat:' : $botId.':chat:';
        }

        return ($this->provider === 'viber' ? hash('sha256', $this->account_id) : $this->account_id).':';
    }

    public function lastIncomingAt(): ?Carbon
    {
        $prefix = strtr($this->conversationPrefix(), ['!' => '!!', '%' => '!%', '_' => '!_']);
        $timestamp = LeadMessage::query()
            ->join('contact_requests', 'lead_messages.contact_request_id', '=', 'contact_requests.id')
            ->where('contact_requests.external_source', $this->externalSource())
            ->whereRaw("contact_requests.external_id like ? escape '!'", [$prefix.'%'])
            ->where('lead_messages.direction', 'incoming')
            ->max('lead_messages.created_at');

        return $timestamp ? Carbon::parse($timestamp) : null;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmMailingCampaign extends Model
{
    public const CHANNELS = [
        'viber_sms' => 'Viber, если не доставлено — SMS',
        'viber' => 'Только Viber',
        'sms' => 'Только SMS',
    ];

    public const STATUSES = [
        'draft' => 'Черновик',
        'sending' => 'Отправляется',
        'done' => 'Отправлена',
        'cancelled' => 'Остановлена',
    ];

    protected $attributes = ['status' => 'draft', 'channel' => 'viber_sms'];

    protected $fillable = ['mailing_list_id', 'name', 'channel', 'text', 'sms_text', 'button_caption', 'button_link', 'status',
        'recipients_count', 'created_by', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function mailingList(): BelongsTo
    {
        return $this->belongsTo(CrmMailingList::class, 'mailing_list_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmMailingMessage::class, 'campaign_id');
    }

    public function usesViber(): bool
    {
        return $this->channel !== 'sms';
    }

    public function smsBody(): string
    {
        return trim((string) ($this->sms_text ?: $this->text).($this->button_link && ! $this->sms_text ? ' '.$this->button_link : ''));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMailingMessage extends Model
{
    public const STATUSES = [
        'pending' => 'В очереди',
        'sent' => 'Отправлено',
        'delivered' => 'Доставлено',
        'read' => 'Прочитано',
        'failed' => 'Не доставлено',
        'skipped' => 'Пропущено',
    ];

    /** Statuses that will not change any more. */
    public const FINAL = ['delivered', 'read', 'failed', 'skipped'];

    protected $fillable = ['campaign_id', 'member_id', 'phone', 'channel', 'provider_message_id', 'status', 'provider_status', 'error',
        'sent_at', 'status_checked_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'status_checked_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CrmMailingCampaign::class, 'campaign_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(CrmMailingListMember::class, 'member_id');
    }
}

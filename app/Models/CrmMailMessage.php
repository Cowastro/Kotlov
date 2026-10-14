<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmMailMessage extends Model
{
    protected $fillable = [
        'thread_id', 'mailbox_id', 'sent_by_user_id', 'message_id', 'in_reply_to',
        'imap_uid', 'direction', 'from_name', 'from_email', 'to', 'cc', 'subject',
        'text_body', 'html_body', 'attachments_count', 'is_read', 'received_at',
        'sent_at', 'metadata',
    ];

    protected $casts = [
        'to' => 'array',
        'cc' => 'array',
        'metadata' => 'array',
        'is_read' => 'boolean',
        'received_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(CrmMailThread::class, 'thread_id');
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(CrmMailbox::class, 'mailbox_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function isLatestInThread(): bool
    {
        $latestId = static::query()
            ->where('thread_id', $this->thread_id)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->value('id');

        return (int) $latestId === (int) $this->id;
    }
}

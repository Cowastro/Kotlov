<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'thread_id', 'sender_id', 'body', 'attachments', 'read_at',
    ];

    protected $casts = [
        'attachments' => 'array',
        'read_at'     => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }
}

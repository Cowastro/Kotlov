<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupportConversation extends Model
{
    protected $fillable = [
        'public_id',
        'session_hash',
        'name',
        'phone',
        'status',
        'page_url',
        'assigned_user_id',
        'last_message_at',
        'last_visitor_message_at',
        'last_manager_message_at',
        'ai_handoff_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'last_visitor_message_at' => 'datetime',
        'last_manager_message_at' => 'datetime',
        'ai_handoff_at' => 'datetime',
    ];

    public const STATUSES = [
        'new' => 'Ждёт ответа',
        'answered' => 'Ответили',
        'closed' => 'Закрыт',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $conversation): void {
            if (Schema::hasTable('lead_channels')) {
                $conversation->syncInboxEntry();
            }
        });
        static::deleted(function (self $conversation): void {
            if (Schema::hasTable('lead_channels')) {
                ContactRequest::query()
                    ->where('external_source', ContactRequest::SITE_CHAT_SOURCE)
                    ->where('external_id', $conversation->public_id)
                    ->delete();
            }
        });
    }

    private function syncInboxEntry(): void
    {
        DB::transaction(function (): void {
            $chat = self::query()->lockForUpdate()->findOrFail($this->id);
            $message = $chat->messages()->where('sender_type', 'visitor')->reorder('id', 'desc')->value('body') ?? '';

            ContactRequest::query()->toBase()->upsert([
                'external_source' => ContactRequest::SITE_CHAT_SOURCE,
                'external_id' => $chat->public_id,
                'source' => ContactRequest::SITE_CHAT_SOURCE,
                'name' => $chat->name,
                'phone' => $chat->phone,
                'message' => $message,
                'status' => match ($chat->status) {
                    'closed' => 'done',
                    'answered' => 'waiting',
                    default => 'new',
                },
                'responsible_user_id' => $chat->assigned_user_id,
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
            ], ['external_source', 'external_id'], [
                'name', 'phone', 'message', 'status', 'responsible_user_id', 'updated_at',
            ]);
        }, 3);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(SupportMessage::class)->latestOfMany();
    }
}

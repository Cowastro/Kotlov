<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

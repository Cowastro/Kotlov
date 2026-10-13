<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CrmMailThread extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'new' => 'Новый лид',
        'in_progress' => 'В работе',
        'waiting_customer' => 'Ждём клиента',
        'waiting_manager' => 'Нужен ответ',
        'closed' => 'Закрыто',
        'spam' => 'Спам',
    ];

    public const PRIORITIES = [
        'low' => 'Низкий',
        'normal' => 'Обычный',
        'high' => 'Высокий',
        'urgent' => 'Срочно',
    ];

    protected $fillable = [
        'mailbox_id', 'crm_counterparty_id', 'crm_contact_id', 'assigned_user_id',
        'thread_key', 'subject', 'participant_name', 'participant_email', 'status',
        'priority', 'messages_count', 'unread_count', 'last_message_at', 'handled_at',
        'manager_note',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'handled_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(CrmMailbox::class, 'mailbox_id');
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'crm_counterparty_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmMailMessage::class, 'thread_id')->orderBy('received_at');
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'waiting_manager']);
    }

    public function takeFor(int $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $this->forceFill([
                'assigned_user_id' => $userId,
                'status' => 'in_progress',
            ])->save();

            $this->counterparty()
                ->whereNull('responsible_user_id')
                ->update(['responsible_user_id' => $userId]);
        });
    }

    public function markAsSpam(): void
    {
        $this->forceFill([
            'status' => 'spam',
            'unread_count' => 0,
            'handled_at' => now(),
        ])->save();

        $this->messages()->where('direction', 'incoming')->update(['is_read' => true]);
    }

    public function restoreFromSpam(): void
    {
        $this->forceFill([
            'status' => 'waiting_manager',
            'handled_at' => null,
        ])->save();
    }
}

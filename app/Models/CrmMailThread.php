<?php

namespace App\Models;

use App\Services\CommercialDocuments\CommercialDocumentMailSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmMailThread extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Черновик',
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

    public const SALES_STAGES = [
        'new_inquiry' => 'Новый запрос',
        'qualification' => 'Уточнение потребности',
        'proposal_sent' => 'КП отправлено',
        'contact_planned' => 'Запланирован контакт',
        'decision_pending' => 'Клиент думает',
        'negotiation' => 'Переговоры / дожим',
        'needs_revision' => 'Нужна корректировка КП',
        'won' => 'Сделка успешна',
        'lost' => 'Сделка не состоялась',
    ];

    public const OPEN_SALES_STAGES = [
        'new_inquiry',
        'qualification',
        'proposal_sent',
        'contact_planned',
        'decision_pending',
        'negotiation',
        'needs_revision',
    ];

    protected $fillable = [
        'mailbox_id', 'crm_counterparty_id', 'crm_contact_id', 'assigned_user_id', 'order_id',
        'supplier_order_request_id', 'product_id', 'supplier_id',
        'thread_key', 'subject', 'participant_name', 'participant_email', 'status', 'sales_stage',
        'priority', 'messages_count', 'unread_count', 'last_message_at', 'handled_at',
        'next_follow_up_at', 'follow_up_note', 'manager_note',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'handled_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::forceDeleting(function (CrmMailThread $thread): void {
            if ($thread->status === 'spam' && Schema::hasTable('crm_mail_blocked_senders')) {
                static::rememberBlockedSender($thread->mailbox_id, $thread->participant_email);
            }

            if (Schema::hasTable('crm_mail_deleted_messages')) {
                $now = now();
                $markers = $thread->messages()
                    ->whereNotNull('imap_uid')
                    ->get(['mailbox_id', 'imap_uid', 'message_id'])
                    ->map(fn (CrmMailMessage $message): array => [
                        'mailbox_id' => $message->mailbox_id,
                        'imap_uid' => $message->imap_uid,
                        'message_id' => $message->message_id,
                        'deleted_at' => $now,
                    ])
                    ->all();

                if ($markers !== []) {
                    DB::table('crm_mail_deleted_messages')->upsert(
                        $markers,
                        ['mailbox_id', 'imap_uid'],
                        ['message_id', 'deleted_at'],
                    );
                }
            }
        });

        static::saved(function (CrmMailThread $thread): void {
            if ($thread->wasChanged([
                'assigned_user_id', 'status', 'sales_stage', 'next_follow_up_at',
                'follow_up_note', 'manager_note',
            ])) {
                app(CommercialDocumentMailSync::class)->fromMailThread($thread);
            }
        });
    }

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

    public function supplierOrderRequest(): BelongsTo
    {
        return $this->belongsTo(SupplierOrderRequest::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CrmMailMessage::class, 'thread_id')->orderBy('received_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(CrmMailMessage::class, 'thread_id')->latestOfMany();
    }

    public function refreshMessageSummary(): void
    {
        $latestMessage = $this->messages()
            ->reorder()
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        $this->forceFill([
            'subject' => $latestMessage?->subject ?: $this->subject,
            'messages_count' => $this->messages()->count(),
            'unread_count' => $this->messages()
                ->where('direction', 'incoming')
                ->where('is_read', false)
                ->count(),
            'last_message_at' => $latestMessage?->received_at ?: $this->last_message_at,
        ])->save();
    }

    public function commercialDocuments(): HasMany
    {
        return $this->hasMany(CommercialDocument::class, 'crm_mail_thread_id');
    }

    public function hasValidRecipient(): bool
    {
        if (filter_var(trim((string) $this->participant_email), FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }

        $draft = $this->relationLoaded('messages')
            ? $this->messages->where('direction', 'draft')->sortByDesc('id')->first()
            : $this->messages()->where('direction', 'draft')->latest('id')->first();

        return collect($draft?->to ?? [])->contains(
            fn (mixed $recipient): bool => is_array($recipient)
                && filter_var(trim((string) ($recipient['email'] ?? '')), FILTER_VALIDATE_EMAIL) !== false,
        );
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', ['new', 'waiting_manager']);
    }

    public function scopeRequiresReaction(Builder $query): Builder
    {
        return $query->where(function (Builder $reaction): void {
            $reaction->whereIn('status', ['new', 'waiting_manager'])
                ->orWhere(function (Builder $followUp): void {
                    $followUp->whereNotIn('status', ['closed', 'spam'])
                        ->whereNotNull('next_follow_up_at')
                        ->where('next_follow_up_at', '<=', now());
                });
        });
    }

    public function scopePotentialDeals(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'closed')
            ->whereIn('sales_stage', self::OPEN_SALES_STAGES);
    }

    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', ['closed', 'spam'])
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now());
    }

    public function scopeAssignedWork(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_user_id', $userId)
            ->whereNotIn('status', ['draft', 'closed', 'spam']);
    }

    public function scopeLatestMessageDirection(Builder $query, string $direction): Builder
    {
        return $query->whereHas(
            'latestMessage',
            fn (Builder $messages): Builder => $messages->where('direction', $direction),
        );
    }

    public function isPotentialDeal(): bool
    {
        return in_array($this->sales_stage, self::OPEN_SALES_STAGES, true);
    }

    public function markProposalSent(?int $userId = null): void
    {
        $this->forceFill([
            'assigned_user_id' => $this->assigned_user_id ?: $userId,
            'sales_stage' => 'proposal_sent',
            'status' => 'waiting_customer',
            'next_follow_up_at' => now()->addWeekdays(2)->setTime(10, 0),
            'follow_up_note' => 'Связаться с клиентом после отправки коммерческого предложения.',
        ])->save();
    }

    public function markCustomerReplied(): void
    {
        if ($this->status === 'spam') {
            return;
        }

        $updates = [
            'status' => $this->status === 'new' ? 'new' : 'waiting_manager',
        ];

        if (! $this->isPotentialDeal()) {
            $this->forceFill($updates)->save();

            return;
        }

        $this->forceFill($updates + [
            'sales_stage' => 'negotiation',
            'next_follow_up_at' => now(),
            'follow_up_note' => 'Получен ответ клиента — обработать и назначить следующий контакт.',
        ])->save();
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
        DB::transaction(function (): void {
            static::rememberBlockedSender($this->mailbox_id, $this->participant_email);

            $threads = static::query()
                ->where('mailbox_id', $this->mailbox_id)
                ->where('participant_email', mb_strtolower(trim((string) $this->participant_email)));
            $threadIds = (clone $threads)->pluck('id');

            $threads->update([
                'status' => 'spam',
                'unread_count' => 0,
                'handled_at' => now(),
                'updated_at' => now(),
            ]);

            CrmMailMessage::query()
                ->whereIn('thread_id', $threadIds)
                ->where('direction', 'incoming')
                ->update(['is_read' => true]);
        });

        $this->refresh();
    }

    public function restoreFromSpam(): void
    {
        if (Schema::hasTable('crm_mail_blocked_senders')) {
            DB::table('crm_mail_blocked_senders')
                ->where('mailbox_id', $this->mailbox_id)
                ->where('email', mb_strtolower(trim((string) $this->participant_email)))
                ->delete();
        }

        static::query()
            ->where('mailbox_id', $this->mailbox_id)
            ->where('participant_email', mb_strtolower(trim((string) $this->participant_email)))
            ->where('status', 'spam')
            ->update([
                'status' => 'waiting_manager',
                'handled_at' => null,
                'updated_at' => now(),
            ]);

        $this->refresh();
    }

    public static function senderIsBlocked(int $mailboxId, string $email): bool
    {
        $email = mb_strtolower(trim($email));

        if (Schema::hasTable('crm_mail_blocked_senders')
            && DB::table('crm_mail_blocked_senders')
                ->where('mailbox_id', $mailboxId)
                ->where('email', $email)
                ->exists()) {
            return true;
        }

        return static::query()
            ->where('mailbox_id', $mailboxId)
            ->where('participant_email', $email)
            ->where('status', 'spam')
            ->exists();
    }

    /** Mail of the mailboxes the employee may see (see CrmMailbox::scopeVisibleTo). */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->isAdmin()) {
            return $query;
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->whereNull($query->qualifyColumn('mailbox_id'))
            ->orWhereIn($query->qualifyColumn('mailbox_id'), CrmMailbox::query()->visibleTo($user)->select('id')));
    }

    private static function rememberBlockedSender(int $mailboxId, ?string $email): void
    {
        $email = mb_strtolower(trim((string) $email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! Schema::hasTable('crm_mail_blocked_senders')) {
            return;
        }

        DB::table('crm_mail_blocked_senders')->upsert(
            [[
                'mailbox_id' => $mailboxId,
                'email' => $email,
                'blocked_at' => now(),
            ]],
            ['mailbox_id', 'email'],
            ['blocked_at'],
        );
    }
}

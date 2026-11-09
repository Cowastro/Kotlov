<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class ContactRequest extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'phone',
        'email',
        'message',
        'status',
        'responsible_user_id',
        'next_follow_up_at',
        'admin_notes',
        'product_id',
        'requested_quantity',
        'client_response',
        'product_name',
        'product_url',
        'city',
        'source',
        'external_source',
        'external_id',
        'last_external_event_id',
        'needs_attention',
        'archived_at',
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'requested_quantity' => 'integer',
        'needs_attention' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public const OPEN_STATUSES = ['new', 'read', 'in_progress', 'waiting'];

    public const CLOSED_STATUSES = ['done', 'cancelled'];

    public const SITE_CHAT_SOURCE = 'site_chat';

    public static array $statuses = [
        'new' => 'Новая',
        'read' => 'Просмотрена',
        'in_progress' => 'В работе',
        'waiting' => 'Ждём клиента',
        'done' => 'Обработана',
        'cancelled' => 'Неактуальна',
    ];

    public static array $sources = [
        'product_page' => 'Карточка товара',
        'product_availability_request' => 'Уточнение наличия товара',
        'consultation_form' => 'Форма консультации',
        'site_contact_form' => 'Форма на странице контактов',
        self::SITE_CHAT_SOURCE => 'Онлайн-чат сайта',
        'quick_callback' => 'Быстрый звонок за 5 минут',
        'ai_selection' => 'AI-подбор',
        'b2b_price_request' => 'B2B: запрос оптовой цены',
        'telegram_channel' => 'Telegram',
        'viber' => 'Viber',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $lead): void {
            if ($lead->isDirty('status') && in_array($lead->status, self::CLOSED_STATUSES, true) && self::inboxStateAvailable()) {
                $lead->needs_attention = false;
            }
        });
    }

    public static function inboxStateAvailable(): bool
    {
        return once(fn (): bool => Schema::hasColumn('contact_requests', 'needs_attention'));
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->activeInbox()->where('needs_attention', true);
    }

    public function scopeActiveInbox(Builder $query): Builder
    {
        return $query->whereNull('archived_at')->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeArchivedInbox(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereNotNull('archived_at')->orWhereIn('status', self::CLOSED_STATUSES));
    }

    public function clearInboxAttention(): void
    {
        if ($this->needs_attention) {
            self::query()->whereKey($this->id)
                ->where('last_external_event_id', $this->last_external_event_id)
                ->toBase()->update(['needs_attention' => false]);
        }
    }

    public function channelLabel(): string
    {
        return match ($this->external_source) {
            'telegram_bot' => 'Telegram',
            default => LeadChannel::LABELS[$this->external_source] ?? self::$sources[$this->source] ?? 'Форма сайта',
        };
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function telephonyCalls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class)->latest('started_at');
    }

    public function leadMessages(): HasMany
    {
        return $this->hasMany(LeadMessage::class);
    }

    public function latestLeadMessage(): HasOne
    {
        return $this->hasOne(LeadMessage::class)->latestOfMany();
    }

    public function siteChat(): BelongsTo
    {
        return $this->belongsTo(SupportConversation::class, 'external_id', 'public_id');
    }

    public function isSiteChat(): bool
    {
        return $this->external_source === self::SITE_CHAT_SOURCE;
    }
}

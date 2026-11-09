<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    ];

    protected $casts = [
        'next_follow_up_at' => 'datetime',
        'requested_quantity' => 'integer',
    ];

    public const OPEN_STATUSES = ['new', 'read', 'in_progress', 'waiting'];

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
        'telegram_channel' => 'Telegram-канал KOTLOV.BY',
        'viber' => 'Viber',
        'instagram' => 'Instagram',
        'whatsapp' => 'WhatsApp',
    ];

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

    public function isSiteChat(): bool
    {
        return $this->external_source === self::SITE_CHAT_SOURCE;
    }
}

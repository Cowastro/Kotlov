<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstallRequest extends Model
{
    protected $fillable = [
        // Существующие поля
        'client_id',
        'installer_id',
        'product_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'description',
        'project_details',
        'region',
        'city',
        'preferred_date',
        'status',
        'price_agreed',

        // Новые поля (Этап 8)
        'specialization',
        'address',
        'budget',
        'source',
        'notes',
        'installer_profile_id',
        'telegram_message_id',
        'telegram_notified_at',
        'leads_sent_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'price_agreed' => 'decimal:2',
        'budget' => 'decimal:2',
        'telegram_notified_at' => 'datetime',
        'leads_sent_at' => 'datetime',
        'project_details' => 'array',
    ];

    public const STATUSES = [
        'new' => 'Новая',
        'accepted' => 'Принята',
        'in_progress' => 'В работе',
        'done' => 'Выполнена',
        'cancelled' => 'Отменена',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function installerProfile(): BelongsTo
    {
        return $this->belongsTo(InstallerProfile::class);
    }

    public const SPECIALIZATIONS = [
        'heating' => 'Системы отопления и котельные',
        'heatpump' => 'Монтаж теплового насоса',
        'radiators' => 'Монтаж радиаторов',
        'solid_fuel' => 'Монтаж твердотопливного котла',
        'pellet' => 'Монтаж пеллетного котла',
        'underfloor' => 'Монтаж тёплого пола',
        'fireplace' => 'Монтаж камина',
        'chimney' => 'Монтаж дымохода',
        'sauna' => 'Монтаж банной печи',
        'service' => 'Сервис',
        'commissioning' => 'Пусконаладка',
        'engineering' => 'Инженерный подбор',
        'other' => 'Другое',
    ];

    /** Installers this request was offered to by e-mail. */
    public function leads(): HasMany
    {
        return $this->hasMany(InstallRequestLead::class);
    }
}

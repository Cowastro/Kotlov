<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmCounterparty extends Model
{
    public const TYPES = [
        'organization' => 'Организация',
        'person' => 'Физическое лицо',
    ];

    public const CLASSIFICATIONS = [
        'unclassified' => 'Не классифицирован',
        'retail' => 'Розница',
        'b2b_partner' => 'B2B-партнёр',
        'b2b_prospect' => 'Потенциальный B2B',
        'supplier' => 'Поставщик',
        'installer' => 'Монтажник',
        'other' => 'Прочее',
    ];

    public const BUSINESS_UNITS = [
        'wholesale' => 'СанБизнесГруп — опт/B2B',
        'retail' => 'СБГ.by — розница',
    ];

    public const STATUSES = [
        'lead' => 'Новый лид',
        'active' => 'Активный',
        'paused' => 'Приостановлен',
        'inactive' => 'Неактуален',
    ];

    public const SOURCES = [
        'manual' => 'Создан вручную',
        'email' => 'Почта',
        'telephony' => 'Телефония',
        '1c_trade' => '1С Управление торговлей',
        '1c_accounting' => '1С Бухгалтерия',
    ];

    protected $fillable = [
        'type', 'classification', 'business_unit', 'status', 'name', 'public_name', 'legal_name',
        'business_region', 'one_c_manager_name', 'registered_at', 'abc_xyz_classification',
        'unp', 'external_1c_id', 'responsible_user_id', 'source', 'website',
        'legal_address', 'postal_address', 'actual_address', 'notes', 'last_contact_at',
        'commercial_discount_percent', 'commercial_payment_terms', 'commercial_delivery_terms',
        'commercial_currency', 'commercial_note',
    ];

    protected $casts = [
        'registered_at' => 'date',
        'last_contact_at' => 'datetime',
        'commercial_discount_percent' => 'decimal:2',
    ];

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'counterparty_id');
    }

    public function contactPoints(): HasMany
    {
        return $this->hasMany(CrmContactPoint::class, 'counterparty_id');
    }

    public function externalIdentities(): HasMany
    {
        return $this->hasMany(CrmExternalIdentity::class, 'counterparty_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class, 'crm_counterparty_id');
    }

    public function mailThreads(): HasMany
    {
        return $this->hasMany(CrmMailThread::class, 'crm_counterparty_id');
    }

    public function commercialDocuments(): HasMany
    {
        return $this->hasMany(CommercialDocument::class, 'crm_counterparty_id')->latest('issued_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'crm_counterparty_id')->latest();
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class, 'crm_counterparty_id');
    }

    public function getPhonesListAttribute(): string
    {
        return $this->contactPoints->where('type', 'phone')->pluck('value')->join(', ');
    }

    public function getEmailsListAttribute(): string
    {
        return $this->contactPoints->where('type', 'email')->pluck('value')->join(', ');
    }
}

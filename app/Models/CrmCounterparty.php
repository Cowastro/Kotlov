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

    protected $fillable = [
        'type', 'classification', 'business_unit', 'status', 'name', 'legal_name',
        'unp', 'external_1c_id', 'responsible_user_id', 'source', 'website',
        'legal_address', 'notes', 'last_contact_at',
    ];

    protected $casts = ['last_contact_at' => 'datetime'];

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class, 'counterparty_id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(TelephonyCall::class, 'crm_counterparty_id');
    }
}

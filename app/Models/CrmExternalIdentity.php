<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmExternalIdentity extends Model
{
    public const SOURCES = [
        '1c_trade' => '1С Управление торговлей',
        '1c_accounting' => '1С Бухгалтерия',
    ];

    protected $fillable = [
        'counterparty_id', 'source', 'external_id', 'external_code', 'external_name',
        'payload', 'synced_at',
    ];

    protected $casts = ['payload' => 'array', 'synced_at' => 'datetime'];

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'counterparty_id');
    }
}

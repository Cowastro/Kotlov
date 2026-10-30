<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bReconciliationStatement extends Model
{
    public const STATUSES = [
        'client_confirmed' => 'Подтверждён партнёром',
        'agreed' => 'Согласован обеими сторонами',
    ];

    protected $fillable = [
        'b2b_client_id', 'date_from', 'date_to', 'statement_hash', 'source',
        'opening_balance', 'period_debit', 'period_credit', 'closing_balance',
        'currency', 'entries_snapshot', 'status',
        'client_confirmed_by', 'client_confirmed_at',
        'manager_confirmed_by', 'manager_confirmed_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'opening_balance' => 'decimal:2',
        'period_debit' => 'decimal:2',
        'period_credit' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'entries_snapshot' => 'array',
        'client_confirmed_at' => 'datetime',
        'manager_confirmed_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function clientConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_confirmed_by');
    }

    public function managerConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_confirmed_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

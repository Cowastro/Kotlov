<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bAccountingEntry extends Model
{
    public const TYPES = [
        'charge' => 'Начисление по счёту',
        'payment' => 'Оплата',
        'refund' => 'Возврат оплаты',
        'adjustment' => 'Корректировка',
    ];

    protected $fillable = [
        'b2b_client_id', 'integration_source_id', 'commercial_document_id', 'order_id',
        'external_key', 'entry_type', 'occurred_at', 'document_number', 'description',
        'debit', 'credit', 'currency', 'imported_at', 'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'imported_at' => 'datetime',
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function commercialDocument(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->entry_type] ?? $this->entry_type;
    }
}

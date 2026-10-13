<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommercialDocument extends Model
{
    public const TYPES = [
        'quote' => 'Коммерческое предложение',
        'invoice' => 'Счёт на оплату',
    ];

    public const STATUSES = [
        'draft' => 'Черновик',
        'sent' => 'Отправлен',
        'accepted' => 'Согласован',
        'paid' => 'Оплачен',
        'cancelled' => 'Отменён',
    ];

    protected $fillable = [
        'number', 'type', 'status', 'ai_assisted', 'source_document_id', 'created_by', 'manager_id', 'crm_mail_thread_id',
        'client_name', 'client_company', 'client_email', 'client_phone', 'client_unp', 'client_address',
        'issued_at', 'valid_until', 'currency', 'subtotal', 'discount_total', 'delivery_price', 'total',
        'vat_rate', 'payment_terms', 'delivery_terms', 'notes', 'seller_snapshot', 'sent_at', 'accepted_at',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ai_assisted' => 'boolean',
        'seller_snapshot' => 'array',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'total' => 'decimal:2',
        'vat_rate' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (CommercialDocument $document): void {
            $document->issued_at ??= today();
            $document->valid_until ??= today()->addDays($document->type === 'invoice' ? 5 : 10);
            $document->seller_snapshot ??= config('legal.commercial_documents');
            $document->created_by ??= auth()->id();
            $document->manager_id ??= auth()->id();
            $document->number ??= self::nextNumber($document->type);
        });
    }

    public static function nextNumber(string $type): string
    {
        $prefix = $type === 'invoice' ? 'СЧ' : 'КП';
        $year = now()->format('Y');
        $lastId = (int) self::query()->max('id') + 1;

        return sprintf('%s-%s-%05d', $prefix, $year, $lastId);
    }

    public function recalculate(): void
    {
        $subtotal = round((float) $this->items()->sum('line_total'), 2);
        $total = max(0, round($subtotal - (float) $this->discount_total + (float) $this->delivery_price, 2));
        $this->forceFill(['subtotal' => $subtotal, 'total' => $total])->saveQuietly();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CommercialDocumentItem::class)->orderBy('sort_order');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_document_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mailThread(): BelongsTo
    {
        return $this->belongsTo(CrmMailThread::class, 'crm_mail_thread_id');
    }
}

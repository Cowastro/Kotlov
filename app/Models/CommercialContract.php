<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialContract extends Model
{
    public const STATUSES = [
        'generated' => 'Сформирован',
        'sent' => 'Отправлен',
        'signed' => 'Подписан',
        'cancelled' => 'Отменён',
    ];

    protected $fillable = [
        'commercial_document_id', 'created_by', 'number', 'status', 'template_mode',
        'template_version', 'contract_date', 'valid_until', 'seller_snapshot',
        'buyer_snapshot', 'terms_snapshot', 'source_template_path', 'generated_docx_path',
        'generated_pdf_path', 'content_checksum', 'generated_at',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'valid_until' => 'date',
        'seller_snapshot' => 'array',
        'buyer_snapshot' => 'array',
        'terms_snapshot' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            $contract->contract_date ??= today();
            $contract->created_by ??= auth()->id();
            $contract->number ??= self::nextNumber();
        });
    }

    public static function nextNumber(): string
    {
        return sprintf('ДГ-%s-%05d', now()->format('Y'), ((int) self::query()->max('id')) + 1);
    }

    public function commercialDocument(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

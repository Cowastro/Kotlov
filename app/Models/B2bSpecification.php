<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class B2bSpecification extends Model
{
    public const STATUSES = [
        'submitted' => 'Получена',
        'reviewing' => 'В работе',
        'clarification' => 'Нужно уточнение',
        'quoted' => 'КП подготовлено',
        'closed' => 'Завершена',
        'cancelled' => 'Отменена',
    ];

    protected $fillable = [
        'b2b_client_id', 'user_id', 'reference', 'title', 'comment', 'status',
        'manager_id', 'commercial_document_id', 'client_message', 'manager_note', 'submitted_at', 'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (B2bSpecification $specification): void {
            $specification->reference ??= 'SP-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $specification->submitted_at ??= now();
        });

        static::updating(function (B2bSpecification $specification): void {
            if ($specification->isDirty('status') && $specification->status !== 'submitted') {
                $specification->reviewed_at ??= now();
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function commercialDocument(): BelongsTo
    {
        return $this->belongsTo(CommercialDocument::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(B2bSpecificationAttachment::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

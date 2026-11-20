<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OneCPriceBatch extends Model
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const SENDING = 'sending';

    public const CREATED = 'created';

    public const FAILED = 'failed';

    public const CANCELLED = 'cancelled';

    public const STATUSES = [
        self::DRAFT => 'Черновик',
        self::APPROVED => 'Ждёт агента 1С',
        self::SENDING => 'Агент создаёт документ',
        self::CREATED => 'Документ создан в 1С',
        self::FAILED => 'Ошибка',
        self::CANCELLED => 'Отменён',
    ];

    protected $table = 'onec_price_batches';

    protected $fillable = [
        'status', 'comment', 'created_by', 'approved_by', 'approved_at', 'picked_at', 'finished_at',
        'onec_document_number', 'onec_document_key', 'error',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'picked_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OneCPriceBatchItem::class, 'onec_price_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

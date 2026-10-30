<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bSpecificationAttachment extends Model
{
    public const OCR_STATUSES = [
        'idle' => 'Не запускалось',
        'queued' => 'В очереди',
        'processing' => 'Распознаётся',
        'completed' => 'Распознано',
        'failed' => 'Ошибка распознавания',
    ];

    protected $fillable = [
        'b2b_specification_id', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256',
        'ocr_batch_id', 'ocr_status', 'ocr_message', 'ocr_attempts', 'ocr_requested_by',
        'ocr_requested_at', 'ocr_started_at', 'ocr_completed_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'ocr_attempts' => 'integer',
        'ocr_requested_at' => 'datetime',
        'ocr_started_at' => 'datetime',
        'ocr_completed_at' => 'datetime',
    ];

    public function specification(): BelongsTo
    {
        return $this->belongsTo(B2bSpecification::class, 'b2b_specification_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B2bSpecificationItem::class, 'b2b_specification_attachment_id');
    }

    public function ocrRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocr_requested_by');
    }

    public function ocrStatusLabel(): string
    {
        return self::OCR_STATUSES[$this->ocr_status] ?? $this->ocr_status;
    }
}

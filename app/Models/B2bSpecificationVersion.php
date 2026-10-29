<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2bSpecificationVersion extends Model
{
    public const SOURCES = [
        'upload' => 'Первая загрузка',
        'revision' => 'Новая редакция клиента',
        'reparse' => 'Повторный разбор',
        'migration' => 'Исходная версия',
    ];

    protected $fillable = [
        'b2b_specification_id', 'parent_version_id', 'number', 'source', 'parsing_status', 'note',
        'message', 'item_count', 'matched_count', 'suggested_count', 'attachment_ids',
        'diff_summary', 'created_by',
    ];

    protected $casts = [
        'number' => 'integer',
        'item_count' => 'integer',
        'matched_count' => 'integer',
        'suggested_count' => 'integer',
        'attachment_ids' => 'array',
        'diff_summary' => 'array',
    ];

    public function specification(): BelongsTo
    {
        return $this->belongsTo(B2bSpecification::class, 'b2b_specification_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_version_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B2bSpecificationItem::class, 'b2b_specification_version_id');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}

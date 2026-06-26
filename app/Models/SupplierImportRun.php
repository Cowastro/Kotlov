<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierImportRun extends Model
{
    protected $fillable = [
        'source_id',
        'status',
        'file_path',
        'file_hash',
        'stats',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'stats' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SupplierSource::class, 'source_id');
    }

    public function previewItems(): HasMany
    {
        return $this->hasMany(SupplierImportPreviewItem::class, 'run_id');
    }
}

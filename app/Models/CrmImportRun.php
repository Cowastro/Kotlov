<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmImportRun extends Model
{
    protected $fillable = [
        'source', 'import_type', 'file_name', 'checksum', 'status', 'rows_count',
        'created_count', 'matched_count', 'conflict_count', 'contact_point_count',
        'call_match_count', 'summary', 'error', 'imported_by', 'finished_at',
    ];

    protected $casts = ['summary' => 'array', 'finished_at' => 'datetime'];

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(CrmImportConflict::class, 'import_run_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmImportConflict extends Model
{
    public const STATUSES = [
        'pending' => 'Требует разбора',
        'resolved' => 'Разобран',
        'ignored' => 'Игнорирован',
    ];

    protected $fillable = [
        'import_run_id', 'source', 'import_type', 'external_id', 'external_name',
        'reason', 'candidate_ids', 'payload', 'status', 'resolved_counterparty_id',
        'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'candidate_ids' => 'array', 'payload' => 'array', 'resolved_at' => 'datetime',
    ];

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(CrmImportRun::class, 'import_run_id');
    }

    public function resolvedCounterparty(): BelongsTo
    {
        return $this->belongsTo(CrmCounterparty::class, 'resolved_counterparty_id');
    }
}

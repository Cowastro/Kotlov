<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierChannelTransition extends Model
{
    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_READY = 'ready';

    public const STATUS_LEGACY_DISABLED = 'legacy_disabled';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'supplier_id', 'integration_source_id', 'previewed_by', 'status', 'snapshot',
        'control_exchange_run_id', 'ready_at', 'confirmed_by', 'confirmed_at',
        'legacy_disabled_at', 'rolled_back_by', 'rolled_back_at', 'rollback_reason', 'notes',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'ready_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'legacy_disabled_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(IntegrationSource::class, 'integration_source_id');
    }

    public function previewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previewed_by');
    }

    public function controlExchangeRun(): BelongsTo
    {
        return $this->belongsTo(IntegrationExchangeRun::class, 'control_exchange_run_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rolledBackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rolled_back_by');
    }
}

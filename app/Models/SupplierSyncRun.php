<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Storage;

class SupplierSyncRun extends Model
{
    protected $fillable = [
        'command', 'status', 'started_at', 'finished_at', 'duration_ms',
        'exit_code', 'changes_count', 'price_changes_count',
        'stock_changes_count', 'supplier_names', 'context',
        'source_file_name', 'source_file_path', 'source_file_type',
        'source_file_size', 'source_file_sha256',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'context' => 'array',
        'source_file_size' => 'integer',
    ];

    public function changes(): HasMany
    {
        return $this->hasMany(SupplierSyncChange::class);
    }

    public function linkedSupplier(): HasOneThrough
    {
        return $this->hasOneThrough(
            Supplier::class,
            SupplierSync::class,
            'command',
            'id',
            'command',
            'supplier_id',
        );
    }

    public function changedSupplier(): HasOneThrough
    {
        return $this->hasOneThrough(
            Supplier::class,
            SupplierSyncChange::class,
            'supplier_sync_run_id',
            'id',
            'id',
            'supplier_id',
        );
    }

    public function supplierForNavigation(): ?Supplier
    {
        return $this->linkedSupplier ?: $this->changedSupplier;
    }

    public function hasArchivedSource(): bool
    {
        return is_string($this->source_file_path)
            && str_starts_with($this->source_file_path, 'supplier-sync-sources/')
            && Storage::disk('local')->exists($this->source_file_path);
    }

    public function sourceFileSizeLabel(): ?string
    {
        if (! $this->source_file_size) {
            return null;
        }

        return $this->source_file_size >= 1024 * 1024
            ? number_format($this->source_file_size / 1024 / 1024, 1, ',', ' ').' МБ'
            : number_format($this->source_file_size / 1024, 1, ',', ' ').' КБ';
    }
}

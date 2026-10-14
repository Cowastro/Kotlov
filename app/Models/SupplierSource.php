<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierSource extends Model
{
    protected $fillable = [
        'supplier_id',
        'name',
        'type',
        'config',
        'column_mappings',
        'schedule_cron',
        'is_active',
        'last_run_at',
    ];

    protected $casts = [
        'config' => 'array',
        'column_mappings' => 'array',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(SupplierImportRun::class, 'source_id');
    }

    public function sourceUrl(): ?string
    {
        $url = collect([
            data_get($this->config, 'url'),
            data_get($this->config, 'folder_url'),
            data_get($this->config, 'spreadsheet_url'),
        ])->first(fn ($value): bool => filled($value));

        return $url ? trim((string) $url) : null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'google_sheet' => 'Google Таблица',
            'google_drive' => 'Папка Google Drive',
            'excel' => 'Excel-файл',
            'csv' => 'CSV-файл',
            'api' => 'API',
            'email' => 'Файл по email',
            default => $this->type ?: 'Источник прайса',
        };
    }

    public function scheduleLabel(): string
    {
        return filled($this->schedule_cron)
            ? $this->schedule_cron
            : 'По запросу';
    }
}

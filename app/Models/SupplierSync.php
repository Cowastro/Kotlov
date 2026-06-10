<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SupplierSync extends Model
{
    protected $fillable = [
        'key',
        'name',
        'code',
        'title',
        'description',
        'command',
        'source_url',
        'image_disk_path',
        'is_active',
        'last_run_at',
        'last_status',
        'last_exit_code',
        'last_log_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
    ];

    public function getProductsCountAttribute(): int
    {
        return DB::table('supplier_products')
            ->where('supplier_sync_id', $this->id)
            ->whereNotNull('product_id')
            ->distinct()
            ->count('product_id');
    }

    public function getMappingsCountAttribute(): int
    {
        return DB::table('supplier_products')
            ->where('supplier_sync_id', $this->id)
            ->count();
    }

    public function getAttributesCountAttribute(): int
    {
        $productIds = DB::table('supplier_products')
            ->where('supplier_sync_id', $this->id)
            ->whereNotNull('product_id')
            ->distinct()
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return 0;
        }

        return DB::table('product_attribute_values')
            ->whereIn('product_id', $productIds)
            ->count();
    }

    public function getPhotosCountAttribute(): int
    {
        if (! $this->image_disk_path) {
            return 0;
        }

        $dir = public_path($this->image_disk_path);

        if (! is_dir($dir)) {
            return 0;
        }

        return count(glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: []);
    }

    public function runSync(string $mode): array
    {
        $parameters = $mode === 'update'
            ? ['--apply' => true]
            : ['--dry-run' => true];

        $exitCode = Artisan::call($this->command, $parameters);
        $output = trim(Artisan::output());
        $logPath = $this->writeLog($mode, $exitCode, $output);

        $this->forceFill([
            'last_run_at' => now(),
            'last_status' => $exitCode === 0 ? ($mode === 'update' ? 'updated' : 'dry-run') : 'failed',
            'last_exit_code' => $exitCode,
            'last_log_path' => $logPath,
        ])->save();

        return [
            'exit_code' => $exitCode,
            'output' => $output,
            'log_path' => $logPath,
        ];
    }

    public function logContents(): string
    {
        if (! $this->last_log_path || ! file_exists($this->last_log_path)) {
            return 'Логов пока нет.';
        }

        return file_get_contents($this->last_log_path) ?: 'Лог пуст.';
    }

    public static function cleanLogs(): void
    {
        $dir = storage_path('logs/supplier-sync');

        if (is_dir($dir)) {
            File::cleanDirectory($dir);
        }

        static::query()->update([
            'last_log_path' => null,
            'last_status' => 'never',
            'last_exit_code' => null,
            'last_run_at' => null,
        ]);
    }

    private function writeLog(string $mode, int $exitCode, string $output): string
    {
        $dir = storage_path('logs/supplier-sync');
        File::ensureDirectoryExists($dir);

        $path = sprintf('%s/%s_%s_%s.log', $dir, $this->key, now()->format('Ymd_His'), $mode);

        file_put_contents($path, implode(PHP_EOL, [
            'Supplier: ' . $this->key,
            'Mode: ' . $mode,
            'Command: ' . $this->command,
            'Exit code: ' . $exitCode,
            'Run at: ' . now()->toDateTimeString(),
            str_repeat('-', 80),
            $output,
        ]));

        return $path;
    }
}

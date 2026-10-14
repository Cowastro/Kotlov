<?php

namespace App\Services;

use App\Models\SupplierSyncRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupplierSyncSourceArchive
{
    private const ALLOWED_EXTENSIONS = ['csv', 'xls', 'xlsx', 'ods', 'xml', 'json', 'zip'];

    private const MAX_BYTES = 100 * 1024 * 1024;

    public function archiveFirst(SupplierSyncRun $run, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if ($this->archive($run, (string) $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function archive(SupplierSyncRun $run, string $source, ?string $originalName = null): bool
    {
        $path = $this->resolvePath($source);
        if ($path === null) {
            return false;
        }

        $extension = Str::lower((string) pathinfo($path, PATHINFO_EXTENSION));
        $size = filesize($path);
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)
            || $size === false || $size < 1 || $size > self::MAX_BYTES) {
            return false;
        }

        $sha256 = hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            return false;
        }

        $archivePath = 'supplier-sync-sources/'.$sha256.'.'.$extension;
        if (! Storage::disk('local')->exists($archivePath)) {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                return false;
            }

            try {
                if (! Storage::disk('local')->writeStream($archivePath, $stream)) {
                    return false;
                }
            } finally {
                fclose($stream);
            }
        }

        $name = basename(str_replace('\\', '/', trim((string) ($originalName ?: basename($path)))));
        $name = preg_replace('~[\x00-\x1F\x7F<>:"/\\|?*]+~u', '-', $name) ?: '';
        if ($name === '' || pathinfo($name, PATHINFO_EXTENSION) === '') {
            $name = 'price-list.'.$extension;
        }

        $run->forceFill([
            'source_file_name' => Str::limit($name, 255, ''),
            'source_file_path' => $archivePath,
            'source_file_type' => $extension,
            'source_file_size' => $size,
            'source_file_sha256' => $sha256,
        ])->save();

        return true;
    }

    private function resolvePath(string $source): ?string
    {
        $source = trim($source);
        if ($source === '' || filter_var($source, FILTER_VALIDATE_URL)) {
            return null;
        }

        $candidates = [$source];
        if (! preg_match('/^(?:[A-Z]:[\\\\\/]|\/)/i', $source)) {
            $candidates[] = base_path($source);
            $candidates[] = storage_path('app/'.$source);
            $candidates[] = storage_path('app/private/'.$source);
        }

        foreach (array_unique($candidates) as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

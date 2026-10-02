<?php

namespace App\Support;

use Illuminate\Support\Str;

class InstallerMedia
{
    public static function isLegacyPublicPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return Str::startsWith(ltrim($path, '/'), ['img/', 'video/']);
    }

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Str::startsWith($path, '/')) {
            return asset(ltrim($path, '/'));
        }

        if (Str::startsWith($path, ['img/', 'video/'])) {
            return asset($path);
        }

        return asset('storage/'.ltrim($path, '/'));
    }

    /**
     * Metadata expected by Filament's FileUpload for files that predate the
     * storage disk and still live directly below public/img or public/video.
     *
     * @param  string|array<string, string>|null  $storedFileNames
     * @return array{name: string, size: int, type: ?string, url: string}
     */
    public static function fileUploadMetadata(
        string $path,
        string|array|null $storedFileNames = null,
    ): array {
        $relativePath = ltrim($path, '/');
        $absolutePath = public_path($relativePath);

        $storedName = is_array($storedFileNames)
            ? ($storedFileNames[$path] ?? null)
            : $storedFileNames;

        return [
            'name' => $storedName ?: basename($relativePath),
            'size' => is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
            'type' => is_file($absolutePath) ? (mime_content_type($absolutePath) ?: null) : null,
            'url' => (string) self::url($path),
        ];
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

class InstallerMedia
{
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

        return asset('storage/' . ltrim($path, '/'));
    }
}

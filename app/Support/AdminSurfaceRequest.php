<?php

namespace App\Support;

use Illuminate\Http\Request;

final class AdminSurfaceRequest
{
    public static function matches(Request $request): bool
    {
        if ($request->is('admin', 'admin/*') || str_starts_with($request->getHost(), 'admin.')) {
            return true;
        }

        if (! $request->hasHeader('X-Livewire') || ! $request->is('livewire*')) {
            return false;
        }

        $referer = (string) $request->headers->get('referer');
        $path = trim((string) parse_url($referer, PHP_URL_PATH), '/');

        return parse_url($referer, PHP_URL_HOST) === $request->getHost()
            && ($path === 'admin' || str_starts_with($path, 'admin/'));
    }
}

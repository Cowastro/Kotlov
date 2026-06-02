<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function redirectTo(Request $request): ?string
    {
        // API запросы — без редиректа
        if ($request->expectsJson()) {
            return null;
        }

        // Все остальные — на страницу входа
        return '/login';
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRecentB2bPassword
{
    public function handle(Request $request, Closure $next, string $section = 'security'): Response
    {
        $confirmedAt = (int) $request->session()->get('auth.password_confirmed_at', 0);
        $timeout = max(60, (int) config('auth.password_timeout', 10800));

        if ($confirmedAt < time() - $timeout) {
            return to_route('b2b.cabinet', ['section' => $section])
                ->withErrors(['security' => 'Срок подтверждения истёк. Введите пароль и повторите действие.']);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Only a short-lived handoff from the POST form may use the city GET endpoint. */
class ProtectCitySelection
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true)
            && $request->is('city/select/*')
            && ! $request->hasValidRelativeSignature()) {
            return response('Ссылка выбора города устарела. Выберите город в меню сайта.', 410)
                ->header('X-Robots-Tag', 'noindex, nofollow')
                ->header('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}

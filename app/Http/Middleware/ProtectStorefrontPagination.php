<?php

namespace App\Http\Middleware;

use App\Support\AdminSurfaceRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectStorefrontPagination
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)
            || ! $request->query->has('page')
            || AdminSurfaceRequest::matches($request)
            || $request->is('b2b', 'b2b/*', 'api/*', '1c/*', 'integrations/*', 'livewire*')) {
            return $next($request);
        }

        // The public catalog has fewer than 2,000 pages. A generous ceiling
        // rejects crawler-generated offsets before any session or DB access.
        $page = $request->query->all()['page'];
        if (! is_scalar($page) || filter_var($page, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 10000],
        ]) === false) {
            return response('Страница не найдена.', 404)
                ->header('X-Robots-Tag', 'noindex, follow');
        }

        return $next($request);
    }
}

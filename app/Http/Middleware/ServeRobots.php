<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** DB-free fallback for hosts where Apache's static robots rewrite is unavailable. */
class ServeRobots
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true) || $request->getPathInfo() !== '/robots.txt') {
            return $next($request);
        }

        $baseDomain = config('app.base_domain', 'kotlov.by');
        $host = $request->getHost();
        $admin = $host === 'admin.'.$baseDomain;
        if (! $admin && ! in_array($host, [$baseDomain, 'www.'.$baseDomain, 'new.'.$baseDomain], true)) {
            return $next($request);
        }

        $response = response(file_get_contents(public_path('seo/robots-'.($admin ? 'admin' : 'main').'.txt')))
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('Cache-Control', 'public, max-age=300');

        if ($admin) {
            $response->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}

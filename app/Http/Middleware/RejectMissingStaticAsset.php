<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Fallback for development servers which do not process Apache rewrite rules. */
class RejectMissingStaticAsset
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if (in_array($request->method(), ['GET', 'HEAD'], true) && $path === '/img/og-default.jpg') {
            return redirect('/img/og/kotlov-default.jpg', 301);
        }
        if (in_array($request->method(), ['GET', 'HEAD'], true)
            && preg_match('~^(?:/(?:assets|img|images|fonts|css|js)/.+\.(?:avif|gif|ico|jpe?g|png|svg|webp|woff2?|ttf|eot|css|js|map)|/apple-touch-icon(?:-precomposed)?\.png)$~i', $path)
            && ! is_file(public_path(ltrim($path, '/')))) {
            return response('', 404)->header('Cache-Control', 'public, max-age=300');
        }

        return $next($request);
    }
}

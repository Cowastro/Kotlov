<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminNoIndex
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! $this->isAdminRequest($request)) {
            return $response;
        }

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        $contentType = (string) $response->headers->get('Content-Type', '');

        if (
            str_contains($contentType, 'text/html')
            && method_exists($response, 'getContent')
            && method_exists($response, 'setContent')
        ) {
            $content = (string) $response->getContent();

            if ($content && ! str_contains($content, 'name="robots"')) {
                $content = str_ireplace(
                    '</head>',
                    '<meta name="robots" content="noindex,nofollow">' . PHP_EOL . '</head>',
                    $content
                );

                $response->setContent($content);
            }
        }

        return $response;
    }

    private function isAdminRequest(Request $request): bool
    {
        return $request->getHost() === 'admin.' . config('app.base_domain', 'kotlov.by')
            || str_starts_with('/' . ltrim($request->path(), '/'), '/admin');
    }
}

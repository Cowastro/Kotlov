<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CapturePartnerAttribution
{
    public const COOKIE = 'partner_attribution';

    public const FIELDS = ['utm_source', 'utm_medium', 'utm_campaign', 'ref'];

    public function handle(Request $request, Closure $next): Response
    {
        $existing = $request->cookie(self::COOKIE);
        $attributes = $existing === null
            ? $this->sanitize($request->isMethod('GET') ? $request->query() : [])
            : $this->sanitize(is_string($existing) ? (json_decode($existing, true) ?? []) : []);

        $request->attributes->set(self::COOKIE, $attributes);
        $response = $next($request);

        $landing = $response->isSuccessful() || ($attributes !== [] && $response->isRedirection());
        if ($existing === null && $request->isMethod('GET') && $landing && ! $request->is('admin', 'admin/*')) {
            $response->headers->setCookie(cookie(self::COOKIE, json_encode((object) $attributes, JSON_THROW_ON_ERROR), 30 * 24 * 60, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }

    private function sanitize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $attributes = [];
        foreach (self::FIELDS as $field) {
            $value = $input[$field] ?? null;
            // Accept campaign slugs only, excluding URLs, email addresses and phone-like strings.
            if (is_string($value) && preg_match('/\A[a-z][a-z0-9_-]{0,79}\z/i', $value)
                && ! preg_match('/\d{7,}/', $value)) {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }
}

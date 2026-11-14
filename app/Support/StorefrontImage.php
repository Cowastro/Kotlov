<?php

namespace App\Support;

final class StorefrontImage
{
    /** Display copies only; full-size product and SEO images remain available. */
    public static function categoryThumbnail(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        $domain = (string) config('app.base_domain', 'kotlov.by');
        if ($host && $host !== request()->getHost() && $host !== $domain && ! str_ends_with($host, '.'.$domain)) {
            return $url;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('~^/?img/popular/([a-z0-9_-]+)\.(?:jpg|jpeg|png)$~i', $path, $matches)) {
            $optimized = 'img/popular/optimized/'.$matches[1].'.webp';
            if (is_file(public_path($optimized))) {
                return asset($optimized);
            }
        }

        return $url;
    }
}

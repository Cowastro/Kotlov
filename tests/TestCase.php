<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function citySelectionLink(string $url): string
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $slug = basename($parts['path']);
        $path = URL::temporarySignedRoute('city.select', now()->addMinutes(10), [
            'slug' => $slug, 'return' => $query['return'] ?? '/',
        ], absolute: false);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'kotlov.by').$path;
    }
}

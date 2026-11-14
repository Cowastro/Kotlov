<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RobotsAvailabilityTest extends TestCase
{
    public function test_primary_and_admin_robots_do_not_open_database_connections_or_sessions(): void
    {
        config(['app.base_domain' => 'kotlov.by']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach (['kotlov.by', 'new.kotlov.by', 'www.kotlov.by', 'admin.kotlov.by'] as $host) {
            $response = $this->get('https://'.$host.'/robots.txt');
            $response->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
            $this->assertSame([], $response->headers->getCookies());
            if ($host === 'admin.kotlov.by') {
                $this->assertSame("User-agent: *\nDisallow: /\n", str_replace("\r\n", "\n", $response->getContent()));
                $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            } else {
                $response->assertContent(file_get_contents(resource_path('seo/robots-main.txt')));
            }
        }

        $this->assertSame([], $queries);
    }

    public function test_static_copy_preserves_the_canonical_pagination_and_sitemap_rules(): void
    {
        $this->assertSame(
            file_get_contents(resource_path('seo/robots-main.txt')),
            file_get_contents(public_path('seo/robots-main.txt')),
        );
    }
}

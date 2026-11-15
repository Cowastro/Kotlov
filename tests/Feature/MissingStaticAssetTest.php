<?php

namespace Tests\Feature;

use App\Http\Middleware\RejectMissingStaticAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MissingStaticAssetTest extends TestCase
{
    public function test_absent_static_files_do_not_query_the_database_or_start_sessions(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        foreach (['/images/yes.png', '/images/no.gif', '/img/missing-audit.jpg', '/assets/css/missing-audit.css', '/apple-touch-icon.png'] as $path) {
            $response = $this->get($path)->assertNotFound();
            $this->assertSame([], $response->headers->getCookies());
        }
        $this->get('/img/og-default.jpg')->assertStatus(301)->assertRedirect('/img/og/kotlov-default.jpg');
        $this->assertSame([], $queries);
    }

    public function test_existing_assets_and_dynamic_download_routes_are_not_intercepted(): void
    {
        $middleware = new RejectMissingStaticAsset;
        foreach (['/img/og/kotlov-default.jpg', '/proxy-image/product/missing.jpg', '/storage/manual.pdf', '/admin/files/manual.png', '/images/no-extension'] as $path) {
            $response = $middleware->handle(Request::create($path), fn () => response('passed'));
            $this->assertSame('passed', $response->getContent());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\ProtectStorefrontPagination;
use App\Support\PaginationWindow;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontPaginationSafetyTest extends TestCase
{
    public function test_crawler_generated_page_offsets_are_rejected_without_database_queries(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        foreach (['991103302', '268857467', '-1', '0', 'abc', '999999999999999999999', '1.5'] as $page) {
            $this->get('https://kotlov.by/pechki?page='.$page)->assertNotFound()
                ->assertHeader('X-Robots-Tag', 'noindex, follow');
        }
        $this->get('https://kotlov.by/pechki?page[]=1')->assertNotFound();
        $this->assertSame([], $queries);
    }

    public function test_large_out_of_range_page_cannot_allocate_a_reverse_pagination_range(): void
    {
        foreach ([991103302, 268857467, 100, 4] as $page) {
            $paginator = new LengthAwarePaginator([], 60, 24, $page);
            $this->assertSame([], PaginationWindow::urls($paginator));
        }
        $paginator = new LengthAwarePaginator([], 2400, 24, 50);
        $this->assertSame([48, 49, 50, 51, 52], array_keys(PaginationWindow::urls($paginator)));
        $this->assertSame([1], array_keys(PaginationWindow::urls(new LengthAwarePaginator([], 0, 24, 1))));
    }

    public function test_normal_pages_and_private_integration_endpoints_keep_their_pagination(): void
    {
        $middleware = new ProtectStorefrontPagination;
        foreach ([
            'https://kotlov.by/pechki?page=2',
            'https://kotlov.by/admin/products?page=991103302',
            'https://admin.kotlov.by/products?page=991103302',
            'https://kotlov.by/b2b/catalog?page=991103302',
            'https://kotlov.by/integrations/catalog?page=991103302',
        ] as $url) {
            $response = $middleware->handle(Request::create($url), fn () => response('passed'));
            $this->assertSame('passed', $response->getContent());
        }
    }
}

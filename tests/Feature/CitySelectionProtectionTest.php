<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use App\Services\SupplierSyncJournalRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CitySelectionProtectionTest extends TestCase
{
    public function test_unsigned_legacy_crawl_link_is_rejected_without_database_queries(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->get('https://kotlov.by/city/select/minsk?return=%2Fpechki')
            ->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertCookieMissing('selected_city');
        $this->assertSame([], $queries);
    }

    public function test_forged_expired_and_tampered_handoffs_do_not_reach_the_database(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->get('https://kotlov.by/city/select/minsk?signature='.str_repeat('a', 64))->assertStatus(410);
        $expired = URL::temporarySignedRoute('city.select', now()->subMinute(), ['slug' => 'minsk', 'return' => '/'], absolute: false);
        $this->get('https://kotlov.by'.$expired)->assertStatus(410);
        $valid = $this->citySelectionLink('https://kotlov.by/city/select/minsk?return=%2Fcontacts');
        $this->get(str_replace('return=%2Fcontacts', 'return=%2Fcheckout', $valid))->assertStatus(410);
        $this->assertSame([], $queries);
    }

    public function test_registering_storefront_view_data_does_not_query_the_database(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        (new AppServiceProvider(app()))->boot(app(SupplierSyncJournalRecorder::class));
        $this->assertSame([], $queries);
    }
}

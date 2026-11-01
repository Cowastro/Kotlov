<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupplierFreshnessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_offers_and_hours_since_the_last_sync(): void
    {
        $id = DB::table('suppliers')->insertGetId(['name' => 'Тест', 'code' => 'fresh_test', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('supplier_products')->insert([
            'supplier_id' => $id, 'supplier_article' => 'НС-1', 'supplier_name' => 'A',
            'last_synced_at' => now()->subHours(30), 'created_at' => now(), 'updated_at' => now()->subHours(30),
        ]);

        $this->artisan('supplier:freshness')
            ->expectsOutput('metric.supplier_fresh.fresh_test.offers=1')
            ->expectsOutput('metric.supplier_fresh.fresh_test.linked=0')
            ->expectsOutput('metric.supplier_fresh.fresh_test.synced_h=30')
            ->assertSuccessful();
    }
}

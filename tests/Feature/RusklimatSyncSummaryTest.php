<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RusklimatSyncSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_prints_a_safe_summary_with_rows_that_have_no_card(): void
    {
        if (! DB::table('suppliers')->where('code', 'rusklimat')->exists()) {
            DB::table('suppliers')->insert(['code' => 'rusklimat', 'name' => 'Русклимат', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $file = tempnam(sys_get_temp_dir(), 'rk').'.csv';
        file_put_contents($file, "Группа,Модель,\"Наличие,шт\",\"Розница, BYN\",\"Дилер, BYN\",НС-код,Бренд\n"
            ."Кондиционеры Ballu,BSGRI-09HN8_22Y,40,1700,1100,НС-1408803,Ballu\n");

        $this->artisan('supplier:sync-rusklimat', ['--dry-run' => true, '--stock-file' => $file, '--no-images' => true])
            ->expectsOutputToContain('metric.rusklimat_sync.matched=')
            ->expectsOutputToContain('audit.rusklimat_sync.new=ns-1408803:ballu:40:1700:')
            ->assertSuccessful();
    }
}

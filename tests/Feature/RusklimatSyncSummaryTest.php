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

    public function test_dry_run_lists_low_confidence_matches_with_the_proposed_card(): void
    {
        if (! DB::table('suppliers')->where('code', 'rusklimat')->exists()) {
            DB::table('suppliers')->insert(['code' => 'rusklimat', 'name' => 'Русклимат', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $now = now();
        $brandId = (int) DB::table('brands')->whereRaw('lower(name) = ?', ['zilon'])->value('id');
        if ($brandId === 0) {
            $brandId = (int) DB::table('brands')->insertGetId(['name' => 'Zilon', 'slug' => 'zilon-review-test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
        $categoryId = DB::table('categories')->insertGetId(['name' => 'Тепловые завесы', 'slug' => 'zavesy-review-test', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        $productId = DB::table('products')->insertGetId([
            'category_id' => $categoryId, 'brand_id' => $brandId, 'name' => 'Zilon ZVV QX7 RT9',
            'slug' => 'zilon-zvv-qx7-rt9-review-test', 'sku' => 'ZRT-REVIEW-1', 'price' => 500, 'currency' => 'BYN',
            'is_active' => true, 'is_archived' => false, 'in_stock' => true, 'availability_status' => 'in_stock',
            'requires_price_confirmation' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $file = tempnam(sys_get_temp_dir(), 'rk').'.csv';
        file_put_contents($file, "Группа,Модель,\"Наличие,шт\",\"Розница, BYN\",\"Дилер, BYN\",НС-код,Бренд
"
            ."Тепловые завесы Zilon,ZVV QX7 RT9 W2,3,620,400,НС-7700001,Zilon
");

        $this->artisan('supplier:sync-rusklimat', ['--dry-run' => true, '--stock-file' => $file, '--no-images' => true])
            ->expectsOutputToContain("audit.rusklimat_sync.review=ns-7700001:zilon:{$productId}:3:620")
            ->assertSuccessful();
    }
}

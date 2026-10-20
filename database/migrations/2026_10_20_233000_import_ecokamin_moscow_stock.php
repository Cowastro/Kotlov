<?php

use App\Services\EcokaminWarehouseStockImporter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_warehouse_stocks')
            || ! Schema::hasTable('brands')
            || ! Schema::hasTable('suppliers')
            || ! DB::table('brands')->where('slug', 'ecokamin')->exists()
            || ! DB::table('suppliers')->where(function ($query): void {
                $query->whereIn('code', ['ecokamin', 'sanbusinessgroup'])
                    ->orWhere('name', 'like', '%СанБизнесГруп%');
            })->exists()) {
            return;
        }

        $path = database_path('data/ecokamin_moscow_stock_2026-10-20.json');
        if (is_file($path)) {
            app(EcokaminWarehouseStockImporter::class)->importJson($path);
        }
    }

    public function down(): void
    {
        // Historical stock snapshots are operational data and are not restored on rollback.
    }
};

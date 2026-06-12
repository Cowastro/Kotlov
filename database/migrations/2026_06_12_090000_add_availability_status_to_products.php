<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'availability_status')) {
                $table->string('availability_status', 32)
                    ->default('in_stock')
                    ->after('in_stock')
                    ->index();
            }
        });

        DB::table('products')->update([
            'availability_status' => DB::raw("CASE WHEN in_stock = 1 THEN 'in_stock' ELSE 'out_of_stock' END"),
        ]);

        if (Schema::hasTable('supplier_products')) {
            DB::table('supplier_products')
                ->where('source_url', 'like', 'https://gorodkotlov.by/%')
                ->whereNotNull('product_id')
                ->orderBy('product_id')
                ->pluck('product_id')
                ->unique()
                ->chunk(500)
                ->each(function ($productIds) {
                    DB::table('products')
                        ->whereIn('id', $productIds)
                        ->where('price', '>', 0)
                        ->where('is_archived', false)
                        ->update([
                            'in_stock' => true,
                            'availability_status' => 'check',
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'availability_status')) {
                $table->dropColumn('availability_status');
            }
        });
    }
};

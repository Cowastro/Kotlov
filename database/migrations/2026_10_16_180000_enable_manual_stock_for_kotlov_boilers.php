<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('manual_stock_management')->default(false);
        });

        // Existing KOTLOV boiler stock becomes staff-managed; preserve its last
        // recorded availability, including explicit out-of-stock decisions.
        DB::table('products')
            ->whereIn('brand_id', DB::table('brands')->select('id')->whereIn('slug', ['kotlov', 'kotlov-pro']))
            ->whereIn('category_id', DB::table('categories')->select('id')->where('slug', 'tverdotoplivnye'))
            ->update(['manual_stock_management' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('manual_stock_management');
        });
    }
};

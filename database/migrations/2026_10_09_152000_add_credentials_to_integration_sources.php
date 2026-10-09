<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_sources', function (Blueprint $table) {
            $table->string('username')->nullable()->after('driver');
            $table->string('password_hash')->nullable()->after('username');
        });

        DB::table('integration_sources')->updateOrInsert(
            ['code' => 'onec'],
            [
                'name' => '1С',
                'driver' => 'commerceml',
                'is_active' => true,
                'create_products' => false,
                'update_prices' => false,
                'update_stock' => false,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('integration_sources', function (Blueprint $table) {
            $table->dropColumn(['username', 'password_hash']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('coupons')->updateOrInsert(
            ['code' => 'ONLINE3'],
            [
                'type' => 'percent',
                'value' => 3,
                'min_order_amount' => 0,
                'uses_limit' => null,
                'uses_count' => 0,
                'is_active' => true,
                'expires_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('coupons')->where('code', 'ONLINE3')->delete();
    }
};

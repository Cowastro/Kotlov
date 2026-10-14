<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')
            ->where('key', 'elicon_gas_meters')
            ->update([
                'is_active' => false,
                'last_status' => 'disabled',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('supplier_syncs')) {
            return;
        }

        DB::table('supplier_syncs')
            ->where('key', 'elicon_gas_meters')
            ->update([
                'is_active' => true,
                'last_status' => 'never',
                'updated_at' => now(),
            ]);
    }
};

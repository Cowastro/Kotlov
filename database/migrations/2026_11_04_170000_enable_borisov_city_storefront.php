<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cities')
            || ! Schema::hasTable('partner_branches')
            || ! Schema::hasTable('b2b_clients')
            || ! Schema::hasColumn('cities', 'storefront_enabled')) {
            return;
        }

        $cityId = DB::table('cities')
            ->where('slug', 'borisov')
            ->where('is_active', true)
            ->value('id');

        if (! $cityId) {
            return;
        }

        $hasApprovedBranch = DB::table('partner_branches')
            ->join('b2b_clients', 'b2b_clients.id', '=', 'partner_branches.b2b_client_id')
            ->where('partner_branches.city_id', $cityId)
            ->where('partner_branches.is_active', true)
            ->where('b2b_clients.status', 'approved')
            ->exists();

        if ($hasApprovedBranch) {
            DB::table('cities')->where('id', $cityId)->update([
                'storefront_enabled' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cities') && Schema::hasColumn('cities', 'storefront_enabled')) {
            DB::table('cities')->where('slug', 'borisov')->update([
                'storefront_enabled' => false,
                'updated_at' => now(),
            ]);
        }
    }
};

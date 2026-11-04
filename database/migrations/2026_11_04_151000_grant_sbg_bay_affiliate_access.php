<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $dealerPriceLevelId = DB::table('b2b_price_levels')->where('code', 'dealer')->value('id');
        $dealerProgramLevelId = DB::table('partner_program_levels')->where('rank', 3)->value('id');

        DB::table('b2b_clients')
            ->where(function ($query): void {
                $query->where('unp', '193265863')
                    ->orWhere('company_name', 'ООО «СБГ бай»');
            })
            ->update([
                'b2b_price_level_id' => $dealerPriceLevelId,
                'partner_program_level_id' => $dealerProgramLevelId,
                'allow_all_integration_sources' => true,
                'allow_all_integration_warehouses' => true,
                'allow_all_partner_suppliers' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Access may already be used by the affiliate company; do not revoke it automatically.
    }
};

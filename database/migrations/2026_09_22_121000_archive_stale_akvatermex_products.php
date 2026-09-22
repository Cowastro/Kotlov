<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const STALE_PRODUCT_SLUGS = [
        'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-30-v',
        'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-50-v',
        'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-80-v',
        'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-100-v',
        'vodonagrevatel-elektricheskiy-thermex-thermo-50-v-slim',
        'gazovyiy-kotel-thermex-euroelite-f24',
        'vodonagrevatel-nakopitelnyiy-elektricheskiy-garanterm-flat-30-v',
    ];

    public function up(): void
    {
        DB::table('products')->whereIn('slug', self::STALE_PRODUCT_SLUGS)->update([
            'is_active' => false,
            'is_archived' => true,
            'in_stock' => false,
            'stock_qty' => null,
            'availability_status' => 'out_of_stock',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('products')->whereIn('slug', self::STALE_PRODUCT_SLUGS)->update([
            'is_archived' => false,
            'updated_at' => now(),
        ]);

        DB::table('products')->whereIn('slug', [
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-30-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-50-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-80-v',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-thermex-como-100-v',
            'gazovyiy-kotel-thermex-euroelite-f24',
            'vodonagrevatel-nakopitelnyiy-elektricheskiy-garanterm-flat-30-v',
        ])->update([
            'is_active' => true,
            'updated_at' => now(),
        ]);
    }
};

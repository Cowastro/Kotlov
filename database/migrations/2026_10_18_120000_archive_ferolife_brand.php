<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $brandIds = DB::table('brands')->where('name', 'Феролайф')->pluck('id');

            // Keep supplier links, prices, quantities and content for recovery.
            DB::table('products')->whereIn('brand_id', $brandIds)->update([
                'is_active' => false,
                'is_archived' => true,
                'updated_at' => now(),
            ]);
            DB::table('brands')->whereIn('id', $brandIds)->update([
                'is_active' => false,
                'is_featured' => false,
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        // Do not reactivate discontinued products automatically.
    }
};

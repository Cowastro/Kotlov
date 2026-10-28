<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('is_crm_user', true)
            ->whereRaw('LOWER(TRIM(name)) = ?', ['daddy'])
            ->update([
                'name' => 'Никита Бостон',
                'first_name' => 'Никита',
                'last_name' => 'Бостон',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keep the corrected employee identity on rollback.
    }
};

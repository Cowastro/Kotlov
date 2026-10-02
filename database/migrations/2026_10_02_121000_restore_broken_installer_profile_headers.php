<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('installer_profiles')
            ->where('slug', 'ns-trade-borisov')
            ->update([
                'photo' => 'img/installers/ns-trade/logo.png',
                'logo' => 'img/installers/ns-trade/logo.png',
                'updated_at' => now(),
            ]);

        DB::table('installer_profiles')
            ->where('slug', 'ooo-otoplenie-plius')
            ->update([
                'photo' => 'img/installers/avatars/otoplenie-plus-boiler-room-cover.webp',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // These values repair broken URLs. A rollback must not reintroduce them.
    }
};

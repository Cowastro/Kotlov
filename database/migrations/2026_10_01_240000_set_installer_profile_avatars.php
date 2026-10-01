<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('installer_profiles')
            ->where('slug', 'ooo-otoplenie-plius')
            ->update([
                'photo' => 'img/installers/avatars/otoplenie-plus-avatar.png',
                'updated_at' => now(),
            ]);

        DB::table('installer_profiles')
            ->where('slug', 'up-misters')
            ->update([
                'photo' => 'img/installers/avatars/misters-avatar.png',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('installer_profiles')
            ->where('slug', 'ooo-otoplenie-plius')
            ->where('photo', 'img/installers/avatars/otoplenie-plus-avatar.png')
            ->update([
                'photo' => null,
                'updated_at' => now(),
            ]);

        DB::table('installer_profiles')
            ->where('slug', 'up-misters')
            ->where('photo', 'img/installers/avatars/misters-avatar.png')
            ->update([
                'photo' => 'img/installers/misters/bosch-boiler-room/01-heating-circuits.jpg',
                'updated_at' => now(),
            ]);
    }
};

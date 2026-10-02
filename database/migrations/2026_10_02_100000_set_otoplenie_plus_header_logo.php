<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROFILE_SLUG = 'ooo-otoplenie-plius';

    private const LOGO_PATH = 'img/installers/avatars/otoplenie-plus-logo.png';

    public function up(): void
    {
        DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->update([
                'photo' => self::LOGO_PATH,
                'logo' => self::LOGO_PATH,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->update([
                'photo' => 'img/installers/avatars/otoplenie-plus-avatar.png',
                'logo' => null,
                'updated_at' => now(),
            ]);
    }
};

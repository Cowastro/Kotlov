<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROFILE_SLUG = 'ooo-otoplenie-plius';

    private const PHOTO_PATH = 'img/installers/avatars/otoplenie-plus-boiler-room-cover.webp';

    private const PREVIOUS_LOGO_PATH = 'img/installers/avatars/otoplenie-plus-logo.png';

    public function up(): void
    {
        DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->update([
                'photo' => self::PHOTO_PATH,
                'logo' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->update([
                'photo' => self::PREVIOUS_LOGO_PATH,
                'logo' => self::PREVIOUS_LOGO_PATH,
                'updated_at' => now(),
            ]);
    }
};

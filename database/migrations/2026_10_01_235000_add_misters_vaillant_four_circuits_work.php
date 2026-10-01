<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROFILE_EMAIL = 'minskstroy2026@gmail.com';

    private const WORK_TITLE = 'Котельная Vaillant с четырьмя отопительными контурами';

    public function up(): void
    {
        $profile = DB::table('installer_profiles')
            ->where('email', self::PROFILE_EMAIL)
            ->orWhere('slug', 'up-misters')
            ->first();

        if (! $profile) {
            return;
        }

        $photos = [
            'img/installers/misters/vaillant-four-circuits/01-vaillant-boiler-room.jpg',
        ];

        $gallery = json_decode($profile->gallery ?: '[]', true);
        $gallery = array_values(array_unique(array_merge(
            is_array($gallery) ? $gallery : [],
            $photos,
        )));

        DB::table('installer_profiles')->where('id', $profile->id)->update([
            'gallery' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        DB::table('installer_works')->updateOrInsert(
            [
                'installer_profile_id' => $profile->id,
                'title' => self::WORK_TITLE,
            ],
            [
                'description' => 'Выполнен монтаж настенного газового котла Vaillant и распределение системы на четыре независимых отопительных контура с насосными группами Meibes. Медная обвязка аккуратно закреплена и промаркирована; установлены расширительный бак, запорная арматура, манометр и оборудование для защиты системы.',
                'work_type' => 'heating',
                'city' => null,
                'region' => null,
                'equipment_type' => 'Настенный газовый котёл и четыре насосные группы отопительных контуров',
                'brand' => 'Vaillant / Meibes',
                'photos' => json_encode($photos, JSON_UNESCAPED_UNICODE),
                'completed_at' => null,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $profileId = DB::table('installer_profiles')
            ->where('email', self::PROFILE_EMAIL)
            ->orWhere('slug', 'up-misters')
            ->value('id');

        if ($profileId) {
            DB::table('installer_works')
                ->where('installer_profile_id', $profileId)
                ->where('title', self::WORK_TITLE)
                ->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROFILE_EMAIL = 'minskstroy2026@gmail.com';

    private const WORK_TITLE = 'Котельная Buderus с системой водоподготовки';

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
            'img/installers/misters/buderus-water-treatment/01-boiler-and-water-treatment.jpg',
            'img/installers/misters/buderus-water-treatment/02-water-filtration.jpg',
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
                'description' => 'Смонтирована котельная с настенным газовым котлом Buderus, системой умягчения воды и магистральной фильтрацией. Выполнены коллекторная разводка инженерных сетей, подключение контуров отопления, расширительного бака, циркуляционного оборудования и контрольно-измерительных приборов. Трубопроводы из нержавеющей стали промаркированы для удобного обслуживания.',
                'work_type' => 'heating',
                'city' => null,
                'region' => null,
                'equipment_type' => 'Настенный газовый котёл, система умягчения воды, магистральные фильтры и коллекторная разводка',
                'brand' => 'Buderus',
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

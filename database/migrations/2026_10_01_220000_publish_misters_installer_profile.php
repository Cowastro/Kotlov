<?php

use App\Models\InstallerApplication;
use App\Services\InstallerApplicationConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const APPLICATION_EMAIL = 'minskstroy2026@gmail.com';

    private const APPLICATION_PHONE = '+375447011515';

    private const WORK_TITLE = 'Котельная Bosch с многоконтурной системой отопления в Минске';

    public function up(): void
    {
        $application = InstallerApplication::query()
            ->where('email', self::APPLICATION_EMAIL)
            ->orWhere('phone', self::APPLICATION_PHONE)
            ->first();

        if (! $application) {
            return;
        }

        $profile = $application->installerProfile;

        if (! $profile) {
            $profile = app(InstallerApplicationConverter::class)->convert($application, false);
        }

        $photos = [
            'img/installers/misters/bosch-boiler-room/01-heating-circuits.jpg',
            'img/installers/misters/bosch-boiler-room/02-bosch-water-heater.jpg',
            'img/installers/misters/bosch-boiler-room/03-bosch-boiler-room.jpg',
        ];

        $slug = $profile->slug;

        if (blank($slug)) {
            $slug = 'up-misters';

            if (DB::table('installer_profiles')
                ->where('slug', $slug)
                ->where('id', '!=', $profile->id)
                ->exists()) {
                $slug .= '-leonid';
            }
        }

        DB::table('installer_profiles')->where('id', $profile->id)->update([
            'contact_name' => 'Леонид',
            'phone' => self::APPLICATION_PHONE,
            'email' => self::APPLICATION_EMAIL,
            'company_name' => 'УП «МИСТЕРС»',
            'short_description' => 'Монтаж котельных и инженерных систем — 20 лет практического опыта.',
            'bio' => 'Леонид — специалист по монтажу инженерных систем с опытом 20 лет. Выполняет монтаж котельных, систем водяного отопления, тёплых полов, водоснабжения и канализации. В работе уделяет внимание аккуратной разводке трубопроводов, разделению системы на независимые контуры, удобству обслуживания оборудования и надёжной автоматике.',
            'experience_years' => 20,
            'city' => 'Минск',
            'region' => 'Минск',
            'work_regions' => json_encode(['Минск', 'Минская область'], JSON_UNESCAPED_UNICODE),
            'work_cities' => json_encode(['Минск'], JSON_UNESCAPED_UNICODE),
            'nationwide' => false,
            'specializations' => json_encode(['heating', 'heatpump'], JSON_UNESCAPED_UNICODE),
            'photo' => $photos[0],
            'gallery' => json_encode($photos, JSON_UNESCAPED_UNICODE),
            'slug' => $slug ?: Str::slug('УП МИСТЕРС Леонид'),
            'status' => 'active',
            'is_verified' => true,
            'is_published' => true,
            'updated_at' => now(),
        ]);

        DB::table('installer_works')->updateOrInsert(
            [
                'installer_profile_id' => $profile->id,
                'title' => self::WORK_TITLE,
            ],
            [
                'description' => 'Смонтирована котельная на оборудовании Bosch с ёмкостным водонагревателем, насосными группами и распределением по нескольким отопительным контурам. Выполнены коллектор тёплого пола, гидравлическая обвязка, теплоизоляция трубопроводов, подключение расширительных баков и автоматики.',
                'work_type' => 'heating',
                'city' => 'Минск',
                'region' => 'Минск',
                'equipment_type' => 'Газовый котёл, ёмкостный водонагреватель, насосные группы и коллектор тёплого пола',
                'brand' => 'Bosch',
                'photos' => json_encode($photos, JSON_UNESCAPED_UNICODE),
                'completed_at' => null,
                'is_published' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('installer_applications')->where('id', $application->id)->update([
            'installer_profile_id' => $profile->id,
            'status' => 'approved',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $profileId = DB::table('installer_profiles')
            ->where('email', self::APPLICATION_EMAIL)
            ->value('id');

        if ($profileId) {
            DB::table('installer_works')
                ->where('installer_profile_id', $profileId)
                ->where('title', self::WORK_TITLE)
                ->delete();
        }
    }
};

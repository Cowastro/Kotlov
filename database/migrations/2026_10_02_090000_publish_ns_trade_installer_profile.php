<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const USER_EMAIL = 'altera.by@mail.ru';

    private const PROFILE_SLUG = 'ns-trade-borisov';

    public function up(): void
    {
        $user = DB::table('users')->where('email', self::USER_EMAIL)->first();

        if (! $user) {
            return;
        }

        DB::table('users')->where('id', $user->id)->update([
            'role' => 'installer',
            'client_type' => 'installer',
            'phone' => '+375 29 676 99 90',
            'company_name' => 'НС Трейд',
            'is_active' => true,
            'b2b_approved' => true,
            'updated_at' => now(),
        ]);

        $profile = DB::table('installer_profiles')
            ->where('user_id', $user->id)
            ->orWhere('slug', self::PROFILE_SLUG)
            ->first();

        if (! $profile) {
            $profileId = DB::table('installer_profiles')->insertGetId([
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $profileId = $profile->id;
        }

        $photos = [
            'img/installers/ns-trade/01-gas-boiler-room.webp',
            'img/installers/ns-trade/02-dhw-cylinder-piping.webp',
            'img/installers/ns-trade/03-multicircuit-boiler-room.webp',
            'img/installers/ns-trade/04-underfloor-heating-large-room.webp',
            'img/installers/ns-trade/05-pellet-boiler.webp',
            'img/installers/ns-trade/06-solid-fuel-retrofit.webp',
            'img/installers/ns-trade/07-wall-boiler-manifolds.webp',
            'img/installers/ns-trade/08-pool-filtration.webp',
            'img/installers/ns-trade/09-combined-boiler-room.webp',
            'img/installers/ns-trade/10-underfloor-manifold.webp',
        ];

        DB::table('installer_profiles')->where('id', $profileId)->update([
            'user_id' => $user->id,
            'contact_name' => 'Павел',
            'phone' => '+375 29 676 99 90',
            'email' => self::USER_EMAIL,
            'telegram' => '@Pavel_altera',
            'whatsapp' => '+375 29 676 99 90',
            'company_name' => 'НС Трейд',
            'short_description' => 'Монтаж отопления, котельных и тёплых полов в Борисове — более 20 лет опыта.',
            'bio' => 'НС Трейд выполняет монтаж и модернизацию систем отопления в Борисове и Борисовском районе. Павел специализируется на котельных для частных домов, радиаторном отоплении, водяных тёплых полах, твердотопливных и пеллетных котлах, дымоходах, печах и каминах. Более 20 лет практического опыта позволяют брать объекты от разводки контуров и установки оборудования до настройки и запуска готовой системы.',
            'experience_years' => 20,
            'city' => 'Борисов',
            'region' => 'Минская область',
            'work_regions' => json_encode(['Минская область'], JSON_UNESCAPED_UNICODE),
            'work_cities' => json_encode(['Борисов', 'Борисовский район'], JSON_UNESCAPED_UNICODE),
            'nationwide' => false,
            'slug' => self::PROFILE_SLUG,
            'specializations' => json_encode([
                'heating',
                'radiators',
                'solid_fuel',
                'pellet',
                'underfloor',
                'chimney',
                'fireplace',
            ], JSON_UNESCAPED_UNICODE),
            'photo' => 'img/installers/ns-trade/logo.png',
            'logo' => 'img/installers/ns-trade/logo.png',
            'gallery' => json_encode($photos, JSON_UNESCAPED_UNICODE),
            'status' => 'active',
            'is_verified' => true,
            'is_published' => true,
            'updated_at' => now(),
        ]);

        $works = [
            [
                'title' => 'Газовая котельная с бойлером и тёплыми полами',
                'description' => 'Смонтирована котельная частного дома: настенный газовый котёл, накопительный водонагреватель, расширительные баки, циркуляционный насос и коллектор водяного тёплого пола. Выполнена компактная разводка трубопроводов и подготовка системы к запуску.',
                'work_type' => 'heating',
                'equipment_type' => 'Газовый котёл, бойлер косвенного нагрева, коллектор тёплого пола',
                'brand' => null,
                'photos' => [$photos[0], $photos[1]],
            ],
            [
                'title' => 'Многоконтурная котельная для частного дома',
                'description' => 'Организованы отдельные отопительные контуры, горячее водоснабжение и распределение теплоносителя. Установлены настенный котёл, накопительная ёмкость, насосные группы и коллекторы с аккуратной разводкой труб.',
                'work_type' => 'heating',
                'equipment_type' => 'Настенный котёл, накопительный водонагреватель, коллекторы и насосные группы',
                'brand' => null,
                'photos' => [$photos[2], $photos[6]],
            ],
            [
                'title' => 'Водяной тёплый пол в частном доме',
                'description' => 'Выполнена раскладка контуров водяного тёплого пола с равномерным шагом, фиксацией труб и подключением к распределительному коллектору. Система подготовлена к опрессовке и устройству стяжки.',
                'work_type' => 'underfloor',
                'equipment_type' => 'Водяной тёплый пол и распределительный коллектор',
                'brand' => null,
                'photos' => [$photos[3], $photos[9]],
            ],
            [
                'title' => 'Пеллетный котёл с автоматической подачей топлива',
                'description' => 'Установлен пеллетный котёл с внешним топливным бункером и шнековой подачей. Выполнено подключение горелки, автоматики и гидравлической части системы отопления.',
                'work_type' => 'pellet',
                'equipment_type' => 'Пеллетный котёл, бункер и шнековая система подачи',
                'brand' => 'RIZON',
                'photos' => [$photos[4]],
            ],
            [
                'title' => 'Модернизация твердотопливного котла пеллетной горелкой',
                'description' => 'Твердотопливный котёл переоборудован для автоматической работы на пеллетах. Смонтированы пеллетная горелка, система подачи топлива и контроллер управления.',
                'work_type' => 'solid_fuel',
                'equipment_type' => 'Твердотопливный котёл с пеллетной горелкой',
                'brand' => 'TIS',
                'photos' => [$photos[5]],
            ],
            [
                'title' => 'Инженерное оборудование системы бассейна',
                'description' => 'Смонтирован компактный технический узел бассейна с фильтровальной ёмкостью, насосом, переключающим клапаном и распределительным коллектором.',
                'work_type' => 'heating',
                'equipment_type' => 'Насосно-фильтровальное оборудование бассейна',
                'brand' => null,
                'photos' => [$photos[7]],
            ],
            [
                'title' => 'Комбинированная котельная с накопительной ёмкостью',
                'description' => 'Собрана комбинированная система отопления с твердотопливным котлом, большой накопительной ёмкостью, электрическим резервным источником, расширительным баком и коллекторной разводкой.',
                'work_type' => 'solid_fuel',
                'equipment_type' => 'Твердотопливный котёл, накопительная ёмкость и резервный источник тепла',
                'brand' => null,
                'photos' => [$photos[8]],
            ],
        ];

        foreach ($works as $work) {
            DB::table('installer_works')->updateOrInsert(
                [
                    'installer_profile_id' => $profileId,
                    'title' => $work['title'],
                ],
                [
                    'description' => $work['description'],
                    'work_type' => $work['work_type'],
                    'city' => 'Борисов',
                    'region' => 'Минская область',
                    'equipment_type' => $work['equipment_type'],
                    'brand' => $work['brand'],
                    'photos' => json_encode($work['photos'], JSON_UNESCAPED_UNICODE),
                    'completed_at' => null,
                    'is_published' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $profileId = DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->value('id');

        if ($profileId) {
            DB::table('installer_works')->where('installer_profile_id', $profileId)->delete();
            DB::table('installer_profiles')->where('id', $profileId)->delete();
        }
    }
};

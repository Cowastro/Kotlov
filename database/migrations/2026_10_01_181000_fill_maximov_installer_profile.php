<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PROFILE_SLUG = 'ooo-otoplenie-plius';

    public function up(): void
    {
        $profile = DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->orWhere('id', 2)
            ->first();

        if (! $profile) {
            return;
        }

        $gallery = [
            'img/blog/works/heatpump-smolevichi-kotlov-ge-r290-cover.jpg',
            'img/blog/works/heatpump-ostroshitsky-cover.jpg',
            'img/blog/works/kotlov-ge-r32-nareyki-cover.jpg',
            'img/blog/works/heat-pumps-115kw-marina-gorka-cover.jpg',
        ];

        DB::table('installer_profiles')->where('id', $profile->id)->update([
            'company_name' => 'ООО «Отопление плюс»',
            'short_description' => 'Монтаж и пусконаладка тепловых насосов и систем отопления — 25 лет практического опыта.',
            'bio' => 'Алексей Максимов — специалист по монтажу и пусконаладке инженерных систем с опытом 25 лет. Выполняет подбор, монтаж и настройку воздушных тепловых насосов, гидравлическую обвязку, подключение тёплых полов, радиаторов и горячего водоснабжения. В портфолио — частные дома и коммерческие объекты, в том числе высокотемпературные системы R290 и промышленное оборудование суммарной мощностью 230 кВт.',
            'city' => 'Минск',
            'region' => 'Минск',
            'work_regions' => json_encode(['Минск', 'Минская область'], JSON_UNESCAPED_UNICODE),
            'work_cities' => json_encode(['Минск', 'Смолевичи', 'Острошицкий Городок', 'Нарейки', 'Марьина Горка'], JSON_UNESCAPED_UNICODE),
            'specializations' => json_encode(['heating', 'heatpump', 'commissioning'], JSON_UNESCAPED_UNICODE),
            'gallery' => json_encode($gallery, JSON_UNESCAPED_UNICODE),
            'experience_years' => 25,
            'status' => 'active',
            'is_verified' => true,
            'is_published' => true,
            'updated_at' => now(),
        ]);

        $works = [
            'montazh-teplovogo-nasosa-kotlov-ge-16-kvt-r290-smolevichskiy-rayon' => [
                'title' => 'Тепловой насос KOTLOV GE 16 кВт R290 в Смолевичском районе',
                'description' => 'Комплексный монтаж воздушного теплового насоса KOTLOV GE NL-FLM50-160II/R290: наружный блок, гидравлическая обвязка, водяной тёплый пол, подготовка горячей воды и настройка автоматики.',
                'city' => 'Смолевичский район',
                'region' => 'Минская область',
                'equipment_type' => 'Воздушный тепловой насос 16 кВт, R290',
                'brand' => 'KOTLOV GE',
            ],
            'teplovoy-nasos-115-kvt-r290-ostroshitskiy-gorodok' => [
                'title' => 'Тепловой насос 11,5 кВт R290 на радиаторы в Острошицком Городке',
                'description' => 'Монтаж высокотемпературного теплового насоса для существующей радиаторной системы. Встроенная буферная ёмкость 90 литров, подача до 75 °C и беспроводное Wi-Fi-управление.',
                'city' => 'Острошицкий Городок',
                'region' => 'Минская область',
                'equipment_type' => 'Высокотемпературный тепловой насос 11,5 кВт, R290',
                'brand' => 'KOTLOV',
            ],
            'teplovoy-nasos-kotlov-ge-24-kvt-r32-nareyki' => [
                'title' => 'Система отопления с KOTLOV GE 24 кВт R32 в Нарейках',
                'description' => 'Большой частный дом с несколькими зонами: водяной тёплый пол в жилых помещениях и гараже-мастерской, радиаторы в спальнях и отдельный контур для бани. Объект показан на этапе подготовки к подключению теплового насоса.',
                'city' => 'Нарейки',
                'region' => 'Минская область',
                'equipment_type' => 'Воздушный тепловой насос 24 кВт, R32',
                'brand' => 'KOTLOV GE',
            ],
            'shef-montazh-dvuh-teplovyh-nasosov-115-kvt-marina-gorka' => [
                'title' => 'Шеф-монтаж двух тепловых насосов по 115 кВт в Марьиной Горке',
                'description' => 'Инженерное сопровождение и шеф-монтаж двух промышленных тепловых насосов суммарной мощностью 230 кВт для торгового объекта площадью более 3000 м².',
                'city' => 'Марьина Горка',
                'region' => 'Минская область',
                'equipment_type' => 'Два промышленных тепловых насоса по 115 кВт',
                'brand' => 'KOTLOV',
            ],
        ];

        foreach ($works as $slug => $data) {
            $post = DB::table('blog_posts')->where('slug', $slug)->first();

            if (! $post) {
                continue;
            }

            DB::table('installer_works')->updateOrInsert(
                [
                    'installer_profile_id' => $profile->id,
                    'blog_post_id' => $post->id,
                ],
                array_merge($data, [
                    'work_type' => 'heatpump',
                    'photos' => $post->images,
                    'completed_at' => $post->published_at ? date('Y-m-d', strtotime($post->published_at)) : null,
                    'is_published' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        $profileId = DB::table('installer_profiles')
            ->where('slug', self::PROFILE_SLUG)
            ->value('id');

        if ($profileId) {
            DB::table('installer_works')
                ->where('installer_profile_id', $profileId)
                ->whereNotNull('blog_post_id')
                ->delete();
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Stage 2 of Чистый берег: pipes and fittings of every kind, plumbing, and mounting materials.
 * Existing switched-off categories are switched on; the missing ones are added.
 */
return new class extends Migration
{
    private const ROOTS = [
        'santehnika' => ['Сантехника', 120],
        'montazhnye-materialy' => ['Монтажные материалы', 130],
    ];

    private const GROUPS = [
        'truby-i-fitingi' => [
            'polipropilenovye-truby' => ['Полипропиленовые трубы', 10],
            'fitingi-dlya-polipropilenovykh-trub' => ['Фитинги для полипропиленовых труб', 11],
            'fitingi-dlya-trub-pex' => ['Фитинги для труб PEX и PERT', 21],
            'metalloplastikovye-truby' => ['Металлопластиковые трубы', 30],
            'fitingi-dlya-metalloplastikovykh-trub' => ['Фитинги для металлопластиковых труб', 31],
            'polietilenovye-truby' => ['Полиэтиленовые трубы', 40],
            'fitingi-dlya-pe-trub' => ['Фитинги для ПЭ труб', 41],
            'truby-npvh-napornye' => ['Напорные трубы НПВХ', 45],
            'metallicheskie-truby' => ['Металлические трубы', 50],
            'stalnye-detali-truboprovodov' => ['Стальные детали трубопроводов', 51],
            'chugunnye-truby-i-fasonnye-chasti' => ['Чугунные трубы и фасонные части', 55],
            'flantsy' => ['Фланцы', 60],
            'vnutrennyaya-kanalizatsiya' => ['Внутренняя канализация', 70],
            'naruzhnaya-kanalizatsiya' => ['Наружная канализация', 71],
            'lyuki-i-dozhdepriemniki' => ['Люки и дождеприёмники', 72],
            'teploizolyatsiya-dlya-trub' => ['Теплоизоляция для труб', 80],
        ],
        'santehnika' => [
            'smesiteli' => ['Смесители', 10],
            'santekhnicheskaya-posuda' => ['Унитазы, умывальники, мойки', 20],
            'installyatsii-i-armatura-bachkov' => ['Инсталляции и арматура бачков', 30],
            'sifony-i-trapy' => ['Сифоны и трапы', 40],
            'polotentsesushiteli' => ['Полотенцесушители', 50],
            'komplektuyushchie-dlya-santekhniki' => ['Комплектующие для сантехники', 60],
        ],
        'montazhnye-materialy' => [
            'krepezh-i-metizy' => ['Крепёж и метизы', 10],
            'uplotnitelnye-materialy' => ['Уплотнительные материалы', 20],
            'peny-germetiki-i-khimiya' => ['Пены, герметики, монтажная химия', 30],
        ],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::ROOTS as $slug => [$name, $sort]) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if ($id) {
                DB::table('categories')->where('id', $id)->update(['is_active' => true, 'updated_at' => $now]);
            } else {
                DB::table('categories')->insert($this->row(0, $slug, $name, $sort, 'main', $now));
            }
        }
        foreach (self::GROUPS as $parentSlug => $children) {
            $parentId = (int) DB::table('categories')->where('slug', $parentSlug)->value('id');
            if (! $parentId) {
                continue;
            }
            foreach ($children as $slug => [$name, $sort]) {
                $id = DB::table('categories')->where('slug', $slug)->value('id');
                if ($id) {
                    DB::table('categories')->where('id', $id)->update(['is_active' => true, 'updated_at' => $now]);
                } else {
                    DB::table('categories')->insert($this->row($parentId, $slug, $name, $sort, 'child', $now));
                }
            }
        }
    }

    private function row(int $parentId, string $slug, string $name, int $sort, string $type, $now): array
    {
        return [
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'h1' => $name,
            'type' => $type,
            'sort_order' => $sort,
            'is_active' => true,
            'meta_title' => $name.' купить в %city% — цены | KOTLOV',
            'meta_description' => $name.' в наличии и под заказ: цены в BYN, характеристики, доставка по Беларуси.',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function down(): void
    {
        // Categories stay with their cards.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // BAXI ECO Compact 24 F replaced by BAXI DUO-Tec Compact 24 GA (product id=3524).
        // Old slug: baxi-eco-compact-24-f → new slug: baxi-duo-tec-compact-24.

        // ── 301 redirect ─────────────────────────────────────────────────────────
        DB::table('redirects')->insertOrIgnore([
            'from_url'    => '/gazovye/baxi-eco-compact-24-f',
            'to_url'      => '/gazovye/baxi-duo-tec-compact-24',
            'status_code' => 301,
            'is_active'   => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // ── Product update ────────────────────────────────────────────────────────
        $specs = [
            ['key' => 'Тип котла',                                       'value' => 'конденсационный', 'unit' => ''],
            ['key' => 'Количество контуров',                             'value' => 'двухконтурный',   'unit' => ''],
            ['key' => 'Способ монтажа',                                  'value' => 'настенный',       'unit' => ''],
            ['key' => 'Камера сгорания',                                 'value' => 'закрытая',        'unit' => ''],
            ['key' => 'Отвод продуктов сгорания',                       'value' => 'турбированный',   'unit' => ''],
            ['key' => 'Макс. мощность по отоплению (80/60°С)',           'value' => '20',              'unit' => 'кВт'],
            ['key' => 'Макс. мощность по отоплению (50/30°С)',           'value' => '21,8',            'unit' => 'кВт'],
            ['key' => 'Мин. мощность по отоплению (80/60°С)',            'value' => '3,4',             'unit' => 'кВт'],
            ['key' => 'Макс. мощность по ГВС',                          'value' => '24',              'unit' => 'кВт'],
            ['key' => 'КПД в режиме 75/60°С',                           'value' => '97,6',            'unit' => '%'],
            ['key' => 'КПД в режиме 50/30°С',                           'value' => '105,8',           'unit' => '%'],
            ['key' => 'КПД при нагрузке 30%',                           'value' => '107,6',           'unit' => '%'],
            ['key' => 'Производительность ГВС (ΔТ=25°С)',               'value' => '13,8',            'unit' => 'л/мин'],
            ['key' => 'Производительность ГВС (ΔТ=35°С)',               'value' => '9,8',             'unit' => 'л/мин'],
            ['key' => 'Диапазон температуры ГВС',                       'value' => '35–60',           'unit' => '°С'],
            ['key' => 'Расширительный бак',                              'value' => '7',               'unit' => 'л'],
            ['key' => 'Расход газа (природный/сжиженный)',               'value' => '2,61 / 1,92',     'unit' => 'м³/ч (кг/ч)'],
            ['key' => 'Диаметр дымохода (коаксиальный/раздельный)',     'value' => '60-100 / 80',     'unit' => 'мм'],
            ['key' => 'Макс. длина дымохода (коаксиальный/раздельный)', 'value' => '10 / 80',         'unit' => 'м'],
            ['key' => 'Мощность/напряжение питания',                     'value' => '102 / 230',       'unit' => 'Вт/В'],
            ['key' => 'Габариты (ВхШхГ)',                                'value' => '700×400×299',     'unit' => 'мм'],
            ['key' => 'Вес',                                             'value' => '34',              'unit' => 'кг'],
        ];

        DB::table('products')->where('id', 3524)->update([
            'name'              => 'Газовый котел BAXI DUO-Tec Compact 24 GA',
            'slug'              => 'baxi-duo-tec-compact-24',
            'price'             => 3680,
            'images'            => json_encode(['img/products/teplodvor/3524_0.jpg'], JSON_UNESCAPED_UNICODE),
            'specs'             => json_encode($specs, JSON_UNESCAPED_UNICODE),
            'short_description' => 'Конденсационный газовый котел BAXI DUO-Tec Compact 24 GA — двухконтурный настенный аппарат с закрытой камерой сгорания, турбированным дымоудалением и КПД до 107,6%.',
            'content'           => '<p>Конденсационный газовый котел BAXI DUO-Tec Compact 24 GA представляет собой современное решение для отопления и горячего водоснабжения. Модель отличается двухконтурной конструкцией и настенным способом монтажа. Закрытая камера сгорания и турбированный отвод продуктов сгорания обеспечивают безопасную и эффективную работу устройства.</p><p>Технические параметры: мощность по отоплению — 20 кВт, по горячему водоснабжению — 24 кВт. КПД при нагрузке 30% достигает 107,6%, в режиме 50/30°С — 105,8%. Производительность ГВС при ΔТ=25°С равна 13,8 л/мин, при ΔТ=35°С — 9,8 л/мин.</p><p>Котел оснащён расширительным баком объёмом 7 л. Диаметр коаксиального дымохода — 60-100 мм, раздельного — 80 мм, максимальная длина коаксиального — 10 м. Габариты: 700×400×299 мм, вес — 34 кг. Потребляемая мощность — 102 Вт при питании 230 В.</p>',
            'availability_status' => 'in_stock',
            'updated_at'        => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('redirects')
            ->where('from_url', '/gazovye/baxi-eco-compact-24-f')
            ->delete();

        DB::table('products')->where('id', 3524)->update([
            'name'              => 'Газовый котел BAXI ECO Compact 24 F',
            'slug'              => 'baxi-eco-compact-24-f',
            'price'             => 1508.18,
            'images'            => json_encode(['377-baxi_eco_compact.jpg', '518-baxi_eco_compact.png']),
            'specs'             => null,
            'short_description' => null,
            'content'           => null,
            'updated_at'        => now(),
        ]);
    }
};

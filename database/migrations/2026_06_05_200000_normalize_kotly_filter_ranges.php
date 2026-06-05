<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Нормализация фильтров котлов:
 * 1. Чистим грязные числовые value-атрибуты (92,113,148 — мощность; 90,125,151 — площадь)
 * 2. Заменяем старые диапазоны новыми в attribute_options
 * 3. Перепривязываем product_attribute_values к новым диапазонам
 */
return new class extends Migration
{
    // ── Конфиг новых диапазонов ──────────────────────────────────────────────

    // [attr_id_filter, attr_id_value, cat_id, ranges]
    // ranges: [ [option_name, sort, min, max], ... ]  max=null → нет верхней границы
    private function config(): array
    {
        return [
            'gas_power' => [
                'filter_attr' => 56,  'value_attr' => 92,  'cat_id' => 53,
                'ranges' => [
                    ['до 12 кВт',      1,  null, 12],
                    ['12–24 кВт',      2,  12,   24],
                    ['24–35 кВт',      3,  24,   35],
                    ['35–50 кВт',      4,  35,   50],
                    ['свыше 50 кВт',   5,  50,   null],
                ],
            ],
            'gas_area' => [
                'filter_attr' => 55,  'value_attr' => 90,  'cat_id' => 53,
                'ranges' => [
                    ['до 150 м²',      1,  null, 150],
                    ['150–250 м²',     2,  150,  250],
                    ['250–350 м²',     3,  250,  350],
                    ['свыше 350 м²',   4,  350,  null],
                ],
            ],
            'solid_power' => [
                'filter_attr' => 62,  'value_attr' => 113, 'cat_id' => 54,
                'ranges' => [
                    ['до 20 кВт',      1,  null, 20],
                    ['20–50 кВт',      2,  20,   50],
                    ['50–100 кВт',     3,  50,   100],
                    ['100–200 кВт',    4,  100,  200],
                    ['свыше 200 кВт',  5,  200,  null],
                ],
            ],
            'solid_area' => [
                'filter_attr' => 61,  'value_attr' => 125, 'cat_id' => 54,
                'ranges' => [
                    ['до 150 м²',      1,  null, 150],
                    ['150–300 м²',     2,  150,  300],
                    ['300–500 м²',     3,  300,  500],
                    ['свыше 500 м²',   4,  500,  null],
                ],
            ],
            'elec_power' => [
                'filter_attr' => 66,  'value_attr' => 148, 'cat_id' => 55,
                'ranges' => [
                    ['до 6 кВт',       1,  null, 6],
                    ['6–12 кВт',       2,  6,    12],
                    ['12–24 кВт',      3,  12,   24],
                    ['свыше 24 кВт',   4,  24,   null],
                ],
            ],
            'elec_area' => [
                'filter_attr' => 65,  'value_attr' => 151, 'cat_id' => 55,
                'ranges' => [
                    ['до 90 м²',       1,  null, 90],
                    ['90–180 м²',      2,  90,   180],
                    ['свыше 180 м²',   3,  180,  null],
                ],
            ],
        ];
    }

    // ── Нормализация текстового числового значения ───────────────────────────

    private function normalize(string $raw): ?float
    {
        $v = trim($raw);

        // Убираем единицы
        $v = preg_replace('/\s*(кВт|квт|kW|м2|м²|m2|кв\.м)/ui', '', $v);
        $v = trim($v);

        // Запятые → точки
        $v = str_replace(',', '.', $v);

        // Диапазон "10-18" или "10–18" → берём верхнюю границу
        if (preg_match('/^[\d.]+\s*[-–]\s*([\d.]+)$/', $v, $m)) {
            return (float) $m[1];
        }

        // Формат "12/24" → берём большее
        if (preg_match('/^([\d.]+)\/([\d.]+)$/', $v, $m)) {
            return (float) max($m[1], $m[2]);
        }

        // Мусор типа "16 газ / 12 твердое топливо"
        if (preg_match('/[a-zA-Zа-яА-Я]/', $v)) {
            return null; // нечисловой мусор
        }

        if (is_numeric($v) && (float)$v > 0) {
            return (float) $v;
        }

        return null;
    }

    // ── UP ───────────────────────────────────────────────────────────────────

    public function up(): void
    {
        $report = ['normalized' => 0, 'garbage' => [], 'bound' => []];

        // ── Шаг 1: нормализация value-атрибутов ─────────────────────────────
        $valueAttrs = [92, 113, 148, 90, 125, 151];

        foreach ($valueAttrs as $aid) {
            $rows = DB::table('product_attribute_values')
                ->where('attribute_id', $aid)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->get(['id', 'value']);

            foreach ($rows as $row) {
                $clean = $this->normalize($row->value);

                if ($clean === null) {
                    $report['garbage'][] = "attr=$aid id={$row->id} val=\"{$row->value}\"";
                    continue;
                }

                $cleanStr = rtrim(rtrim(number_format($clean, 2, '.', ''), '0'), '.');
                if ($cleanStr !== trim($row->value)) {
                    DB::table('product_attribute_values')
                        ->where('id', $row->id)
                        ->update(['value' => $cleanStr, 'updated_at' => now()]);
                    $report['normalized']++;
                }
            }
        }

        // ── Шаг 2: сохранить старые опции и привязки (для rollback) ─────────
        // Бэкап option_id перед перезаписью
        DB::statement("
            CREATE TABLE IF NOT EXISTS _bak_kotly_filter_options AS
            SELECT * FROM attribute_options
            WHERE attribute_id IN (55,56,61,62,65,66)
        ");
        DB::statement("
            CREATE TABLE IF NOT EXISTS _bak_kotly_pav AS
            SELECT * FROM product_attribute_values
            WHERE attribute_id IN (55,56,61,62,65,66)
        ");

        // ── Шаг 3: заменить опции и перепривязать товары ─────────────────────
        foreach ($this->config() as $key => $cfg) {
            $filterAttr = $cfg['filter_attr'];
            $valueAttr  = $cfg['value_attr'];
            $catId      = $cfg['cat_id'];

            // Удаляем старые опции
            DB::table('attribute_options')->where('attribute_id', $filterAttr)->delete();

            // Создаём новые опции, запоминаем их ID
            $optionMap = []; // [sort_order => id]
            foreach ($cfg['ranges'] as [$name, $sort, $min, $max]) {
                $id = DB::table('attribute_options')->insertGetId([
                    'attribute_id' => $filterAttr,
                    'name'         => $name,
                    'sort_order'   => $sort,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
                $optionMap[$sort] = ['id' => $id, 'min' => $min, 'max' => $max];
            }

            // Удаляем старые привязки product_attribute_values для этого attr
            DB::table('product_attribute_values')->where('attribute_id', $filterAttr)->delete();

            // Получаем нормализованные значения для товаров этой категории
            $productValues = DB::table('product_attribute_values as pav')
                ->join('products as p', 'p.id', '=', 'pav.product_id')
                ->where('pav.attribute_id', $valueAttr)
                ->where('p.category_id', $catId)
                ->where('p.is_active', true)
                ->whereNotNull('pav.value')
                ->where('pav.value', '!=', '')
                ->select('pav.product_id', 'pav.value')
                ->get();

            $inserts = [];
            foreach ($productValues as $pv) {
                $num = $this->normalize($pv->value);
                if ($num === null) continue;

                $optId = null;
                foreach ($optionMap as $rangeData) {
                    $min = $rangeData['min'];
                    $max = $rangeData['max'];
                    if ($min === null && $num <= $max) { $optId = $rangeData['id']; break; }
                    if ($max === null && $num >  $min) { $optId = $rangeData['id']; break; }
                    if ($min !== null && $max !== null && $num > $min && $num <= $max) { $optId = $rangeData['id']; break; }
                }

                if ($optId) {
                    $inserts[] = [
                        'product_id'   => $pv->product_id,
                        'attribute_id' => $filterAttr,
                        'option_id'    => $optId,
                        'value'        => '',
                        'is_checked'   => 0,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ];
                    $report['bound'][$key] = ($report['bound'][$key] ?? 0) + 1;
                }
            }

            if ($inserts) {
                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('product_attribute_values')->insert($chunk);
                }
            }
        }

        // ── Отчёт ─────────────────────────────────────────────────────────────
        echo "\n=== ОТЧЁТ МИГРАЦИИ ===\n";
        echo "Нормализовано value-записей: {$report['normalized']}\n";
        echo "Мусорные значения (не тронуты):\n";
        foreach ($report['garbage'] as $g) echo "  $g\n";
        echo "Привязано к диапазонам:\n";
        foreach ($report['bound'] as $k => $cnt) echo "  $k: $cnt товаров\n";
    }

    // ── DOWN ─────────────────────────────────────────────────────────────────

    public function down(): void
    {
        // Восстанавливаем из бэкапа
        DB::table('attribute_options')->whereIn('attribute_id', [55,56,61,62,65,66])->delete();
        DB::table('product_attribute_values')->whereIn('attribute_id', [55,56,61,62,65,66])->delete();

        DB::statement("INSERT INTO attribute_options SELECT * FROM _bak_kotly_filter_options");
        DB::statement("INSERT INTO product_attribute_values SELECT * FROM _bak_kotly_pav");

        DB::statement("DROP TABLE IF EXISTS _bak_kotly_filter_options");
        DB::statement("DROP TABLE IF EXISTS _bak_kotly_pav");
    }
};

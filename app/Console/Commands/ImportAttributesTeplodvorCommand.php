<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Populate product_attribute_values from products.specs JSON
 * for products imported from teplodvor (specs filled, attribute_values empty).
 *
 * Dry-run:  php artisan supplier:import-attributes-teplodvor --brand="Ariston"
 * Apply:    php artisan supplier:import-attributes-teplodvor --brand="Ariston" --apply
 */
class ImportAttributesTeplodvorCommand extends Command
{
    protected $signature = 'supplier:import-attributes-teplodvor
        {--brand=    : Brand name (required)}
        {--apply     : Write to DB (default: dry-run)}
        {--limit=    : Max products to process}';

    protected $description = 'Populate product_attribute_values from products.specs JSON (teplodvor imports)';

    /**
     * Curated mapping: category_id → teplodvor spec key → attribute config.
     *
     * 'attr_id' — use existing attribute row with this id.
     * 'raw'     — store full text value without digit extraction (for text-only fields).
     *
     * Only value/check types are mapped here.
     * select types require option_id lookup — excluded intentionally.
     */
    private const MAP = [
        // ── Электрические водонагреватели ─────────────────────────────────────────
        98 => [
            // teplodvor uses "Объем бака, л" — different from rusklimat "Объем"
            'Объем'                                 => ['attr_id' => 493],
            'Объем бака, л'                         => ['attr_id' => 493],
            'Максимальная температура нагрева воды' => ['attr_id' => 499],
            'Максимальное давление воды'            => ['attr_id' => 505],
            'Дисплей'                               => ['attr_id' => 506],
            'Термостат безопасности'                => ['attr_id' => 509],
            'Вес'                                   => ['attr_id' => 512],
            'Гарантия на внутренний бак'            => ['attr_id' => 513],
            'Гарантия на водонагреватель'           => ['attr_id' => 514],
            'Теплоизоляция'                         => ['attr_id' => 517],
            'Материал теплоизоляции'                => ['attr_id' => 518, 'raw' => true],
            'Антибактериальная защита'              => ['attr_id' => 587],
            // teplodvor stores dimensions in CM, not MM — map to separate attrs with suffix "см"
            'Высота'                                => ['attr_id' => 563],
            'Ширина'                                => ['attr_id' => 564],
            'Глубина'                               => ['attr_id' => 565],
            'Высота, см'                            => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'см']],
            'Ширина, см'                            => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'см']],
            'Глубина, см'                           => ['create' => ['name' => 'Глубина', 'type' => 'value', 'suffix' => 'см']],
            'Покрытие внутреннего бака'             => ['attr_id' => 503, 'raw' => true],
            'Ступени мощности нагрева, кВт'         => ['create' => ['name' => 'Мощность нагрева', 'type' => 'value', 'suffix' => 'кВт']],
        ],

        // ── Газовые котлы ─────────────────────────────────────────────────────────
        53 => [
            'Площадь обогрева'                      => ['attr_id' => 90],         // value кв.м
            'Мощность'                              => ['attr_id' => 92],         // value кВт
            'Объем расширительного бака'            => ['attr_id' => 93],         // value л
            'Расширительный бак'                    => ['attr_id' => 93],         // value л (alt key)
            'Производительность ГВС'                => ['attr_id' => 97],         // value л/мин
            'КПД'                                   => ['attr_id' => 101],        // value %
            'Расход газа (природный/сжиженный)'     => ['attr_id' => 198],        // value куб.м/час
            'Диаметр дымохода (коаксиальный/раздельный)' => ['attr_id' => 224, 'raw' => true],
            'Потребление электроэнергии'             => ['attr_id' => 261],        // value Вт
            'Вес'                                   => ['attr_id' => 110],        // value кг
            'КПД в режиме 75/60°С'                 => ['attr_id' => 101],        // value %
        ],

        // ── Газовые колонки ───────────────────────────────────────────────────────
        298 => [
            'Производительность'                    => ['attr_id' => 97],         // value л/мин (reuse)
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Коаксиальные дымоходы/фитинги ────────────────────────────────────────
        57 => [
            'Длина'                                 => ['attr_id' => 378],        // value мм
            'Вес'                                   => ['attr_id' => 910],        // value г
        ],

        // ── Электрические котлы ──────────────────────────────────────────────────
        55 => [
            'Мощность'                              => ['attr_id' => 92],         // value кВт
            'Площадь обогрева'                      => ['attr_id' => 90],         // value кв.м
            'КПД'                                   => ['attr_id' => 101],        // value %
            'Потребление электроэнергии'             => ['attr_id' => 261],        // value Вт
            'Вес'                                   => ['attr_id' => 110],        // value кг
        ],

        // ── Косвенные водонагреватели (бойлеры) ──────────────────────────────────
        100 => [
            'Объем'                                 => ['attr_id' => 493],        // value л
            'Максимальная температура нагрева воды' => ['attr_id' => 499],        // value °C
            'Максимальное давление воды'            => ['attr_id' => 505],        // value бар
            'Вес'                                   => ['attr_id' => 512],        // value кг
            'Высота'                                => ['attr_id' => 563],        // value мм
            'Ширина'                                => ['attr_id' => 564],        // value мм
            'Глубина'                               => ['attr_id' => 565],        // value мм
        ],

        // ── Термостаты/автоматика ─────────────────────────────────────────────────
        58 => [
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Насосы для повышения давления ─────────────────────────────────────────
        249 => [
            'Мощность'                              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Подача'                                => ['create' => ['name' => 'Подача', 'type' => 'value', 'suffix' => 'м³/ч']],
            'Напор'                                 => ['create' => ['name' => 'Напор', 'type' => 'value', 'suffix' => 'м']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Насосные станции / гидрофор ───────────────────────────────────────────
        251 => [
            'Мощность'                              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Подача'                                => ['create' => ['name' => 'Подача', 'type' => 'value', 'suffix' => 'м³/ч']],
            'Напор'                                 => ['create' => ['name' => 'Напор', 'type' => 'value', 'suffix' => 'м']],
            'Объем бака'                            => ['create' => ['name' => 'Объём бака', 'type' => 'value', 'suffix' => 'л']],
            'Объём бака'                            => ['create' => ['name' => 'Объём бака', 'type' => 'value', 'suffix' => 'л']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Дренажные / канализационные насосы ───────────────────────────────────
        265 => [
            'Мощность'                              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Подача'                                => ['create' => ['name' => 'Подача', 'type' => 'value', 'suffix' => 'м³/ч']],
            'Напор'                                 => ['create' => ['name' => 'Напор', 'type' => 'value', 'suffix' => 'м']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Алюминиевые радиаторы ─────────────────────────────────────────────────
        233 => [
            'Мощность секции'                       => ['create' => ['name' => 'Мощность секции', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность секции'              => ['create' => ['name' => 'Мощность секции', 'type' => 'value', 'suffix' => 'Вт']],
            'Межосевое расстояние'                  => ['create' => ['name' => 'Межосевое расстояние', 'type' => 'value', 'suffix' => 'мм']],
            'Рабочее давление'                      => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Максимальная температура теплоносителя' => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Вес секции'                            => ['create' => ['name' => 'Вес секции', 'type' => 'value', 'suffix' => 'кг']],
            'Объем секции'                          => ['create' => ['name' => 'Объём секции', 'type' => 'value', 'suffix' => 'л']],
        ],

        // ── Стальные радиаторы (включая трубчатые Zehnder) ───────────────────────
        235 => [
            'Тепловая мощность'                     => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность, Вт'                 => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Мощность'                              => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Макс. мощность'                        => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Теплоотдача при Δt = 70°C, Вт'        => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Рабочее давление'                      => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Рабочее давление, атм'                 => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Макс. рабочее давление'                => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Максимальное рабочее давление, бар'    => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Максимальная температура теплоносителя' => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Максимальная рабочая температура, °C'  => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Макс. рабочая температура'             => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Межосевое расстояние, мм'              => ['create' => ['name' => 'Межосевое расстояние', 'type' => 'value', 'suffix' => 'мм']],
            'Высота'                                => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Высота, мм'                            => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Высота радиатора, мм'                  => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина'                                => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина, мм'                            => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Длина'                                 => ['create' => ['name' => 'Длина', 'type' => 'value', 'suffix' => 'мм']],
            'Глубина'                               => ['create' => ['name' => 'Глубина', 'type' => 'value', 'suffix' => 'мм']],
            'Толщина, мм'                           => ['create' => ['name' => 'Глубина', 'type' => 'value', 'suffix' => 'мм']],
            'Толщина стали, мм'                     => ['create' => ['name' => 'Толщина стали', 'type' => 'value', 'suffix' => 'мм']],
            'Объем воды'                            => ['create' => ['name' => 'Объём воды', 'type' => 'value', 'suffix' => 'л']],
            'Объем воды, л'                         => ['create' => ['name' => 'Объём воды', 'type' => 'value', 'suffix' => 'л']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Вес, кг'                               => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Вес одной секции, кг'                  => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Диаметр подключения'                   => ['create' => ['name' => 'Диаметр подключения', 'type' => 'value', 'suffix' => 'дюйм']],
            'Диаметр подключения, дюйм'             => ['create' => ['name' => 'Диаметр подключения', 'type' => 'value', 'suffix' => 'дюйм']],
        ],

        // ── Стальные радиаторы (старая категория) ────────────────────────────────
        87 => [
            'Тепловая мощность'                     => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность, Вт'                 => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Мощность'                              => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Макс. мощность'                        => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Рабочее давление'                      => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Рабочее давление, атм'                 => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Макс. рабочее давление'                => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Максимальная температура теплоносителя' => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Макс. рабочая температура'             => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Высота'                                => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Высота, мм'                            => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина'                                => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина, мм'                            => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Длина'                                 => ['create' => ['name' => 'Длина', 'type' => 'value', 'suffix' => 'мм']],
            'Глубина'                               => ['create' => ['name' => 'Глубина', 'type' => 'value', 'suffix' => 'мм']],
            'Объем воды'                            => ['create' => ['name' => 'Объём воды', 'type' => 'value', 'suffix' => 'л']],
            'Объем воды, л'                         => ['create' => ['name' => 'Объём воды', 'type' => 'value', 'suffix' => 'л']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Вес, кг'                               => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Биметаллические радиаторы ─────────────────────────────────────────────
        236 => [
            'Мощность секции'                       => ['create' => ['name' => 'Мощность секции', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность секции'              => ['create' => ['name' => 'Мощность секции', 'type' => 'value', 'suffix' => 'Вт']],
            'Межосевое расстояние'                  => ['create' => ['name' => 'Межосевое расстояние', 'type' => 'value', 'suffix' => 'мм']],
            'Рабочее давление'                      => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Максимальная температура теплоносителя' => ['create' => ['name' => 'Макс. температура', 'type' => 'value', 'suffix' => '°C']],
            'Вес секции'                            => ['create' => ['name' => 'Вес секции', 'type' => 'value', 'suffix' => 'кг']],
            'Объем секции'                          => ['create' => ['name' => 'Объём секции', 'type' => 'value', 'suffix' => 'л']],
        ],

        // ── Конвекторы / электрические обогреватели ──────────────────────────────
        281 => [
            'Мощность'                              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Мощность, Вт'                          => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность'                     => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Площадь обогрева, м2'                  => ['create' => ['name' => 'Площадь обогрева', 'type' => 'value', 'suffix' => 'м²']],
            'Площадь обогрева'                      => ['create' => ['name' => 'Площадь обогрева', 'type' => 'value', 'suffix' => 'м²']],
            'Высота'                                => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Высота, мм'                            => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина'                                => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина, мм'                            => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Длина'                                 => ['create' => ['name' => 'Длина', 'type' => 'value', 'suffix' => 'мм']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Термостат'                             => ['create' => ['name' => 'Термостат', 'type' => 'check', 'suffix' => '']],
            'Таймер'                                => ['create' => ['name' => 'Таймер', 'type' => 'check', 'suffix' => '']],
            'Регулировка температуры'               => ['create' => ['name' => 'Регулировка температуры', 'type' => 'check', 'suffix' => '']],
            'Регулировка мощности'                  => ['create' => ['name' => 'Регулировка мощности', 'type' => 'check', 'suffix' => '']],
        ],

        // ── Водяные конвекторы (внутрипольные) ───────────────────────────────────
        324 => [
            'Тип'                                   => ['create' => ['name' => 'Тип', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип обогрева'                          => ['create' => ['name' => 'Тип обогрева', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип конвекции'                         => ['create' => ['name' => 'Тип конвекции', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Длина'                                 => ['create' => ['name' => 'Длина', 'type' => 'value', 'suffix' => 'мм']],
            'Длина, мм'                             => ['create' => ['name' => 'Длина', 'type' => 'value', 'suffix' => 'мм']],
            'Высота'                                => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Высота, мм'                            => ['create' => ['name' => 'Высота', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина'                                => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Ширина, мм'                            => ['create' => ['name' => 'Ширина', 'type' => 'value', 'suffix' => 'мм']],
            'Рабочее давление'                      => ['create' => ['name' => 'Рабочее давление', 'type' => 'value', 'suffix' => 'бар']],
            'Тепловая мощность'                     => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Тепловая мощность, Вт'                 => ['create' => ['name' => 'Тепловая мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
            'Страна'                                => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Страна производства'                   => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Гарантия мес'                          => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
            'Гарантия, мес'                         => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
        ],

        // ── Циркуляционные насосы (cat=60 и подкатегория cat=248) ────────────────
        60 => [
            'Тип насоса'                             => ['create' => ['name' => 'Тип насоса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип ротора'                             => ['create' => ['name' => 'Тип ротора', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Мощность, Вт'                           => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Мощность'                               => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Потребляемая мощность, Вт'              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Потребляемая мощность'                  => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Напряжение, В'                          => ['create' => ['name' => 'Напряжение', 'type' => 'value', 'suffix' => 'В']],
            'Напряжение в сети, В'                   => ['create' => ['name' => 'Напряжение', 'type' => 'value', 'suffix' => 'В']],
            'Максимальный напор, м'                  => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Максимальный напор'                     => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Напор'                                  => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Производительность л/мин'               => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Производительность, max (л/мин)'        => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Производительность'                     => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Монтажная длина, мм'                    => ['create' => ['name' => 'Монтажная длина', 'type' => 'value', 'suffix' => 'мм']],
            'Присоединение'                          => ['create' => ['name' => 'Присоединение', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Присоединительный размер, мм/дюйм'     => ['create' => ['name' => 'Присоединительный размер', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Присоединительный размер, мм (дюймов)' => ['create' => ['name' => 'Присоединительный размер', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Материал корпуса'                       => ['create' => ['name' => 'Материал корпуса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Материал корпуса насоса'                => ['create' => ['name' => 'Материал корпуса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип установки'                          => ['create' => ['name' => 'Тип установки', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Класс защиты'                           => ['create' => ['name' => 'Класс защиты', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Страна'                                 => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Страна производства'                    => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Гарантия мес'                           => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
            'Гарантия, мес'                          => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
            'Вес'                                    => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Циркуляционные (подкатегория) ────────────────────────────────────────
        248 => [
            'Тип насоса'                             => ['create' => ['name' => 'Тип насоса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип ротора'                             => ['create' => ['name' => 'Тип ротора', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Мощность, Вт'                           => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Мощность'                               => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Потребляемая мощность, Вт'              => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Потребляемая мощность'                  => ['create' => ['name' => 'Мощность', 'type' => 'value', 'suffix' => 'Вт']],
            'Напряжение, В'                          => ['create' => ['name' => 'Напряжение', 'type' => 'value', 'suffix' => 'В']],
            'Напряжение в сети, В'                   => ['create' => ['name' => 'Напряжение', 'type' => 'value', 'suffix' => 'В']],
            'Максимальный напор, м'                  => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Максимальный напор'                     => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Напор'                                  => ['create' => ['name' => 'Максимальный напор', 'type' => 'value', 'suffix' => 'м']],
            'Производительность л/мин'               => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Производительность, max (л/мин)'        => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Производительность'                     => ['create' => ['name' => 'Производительность', 'type' => 'value', 'suffix' => 'л/мин']],
            'Монтажная длина, мм'                    => ['create' => ['name' => 'Монтажная длина', 'type' => 'value', 'suffix' => 'мм']],
            'Присоединение'                          => ['create' => ['name' => 'Присоединение', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Присоединительный размер, мм/дюйм'     => ['create' => ['name' => 'Присоединительный размер', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Присоединительный размер, мм (дюймов)' => ['create' => ['name' => 'Присоединительный размер', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Материал корпуса'                       => ['create' => ['name' => 'Материал корпуса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Материал корпуса насоса'                => ['create' => ['name' => 'Материал корпуса', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Тип установки'                          => ['create' => ['name' => 'Тип установки', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Класс защиты'                           => ['create' => ['name' => 'Класс защиты', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Страна'                                 => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Страна производства'                    => ['create' => ['name' => 'Страна производства', 'type' => 'value', 'suffix' => ''], 'raw' => true],
            'Гарантия мес'                           => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
            'Гарантия, мес'                          => ['create' => ['name' => 'Гарантия', 'type' => 'value', 'suffix' => 'мес']],
            'Вес'                                    => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],

        // ── Прочие аксессуары (fallback) ─────────────────────────────────────────
        195 => [
            'Вес'                                   => ['create' => ['name' => 'Вес', 'type' => 'value', 'suffix' => 'кг']],
        ],
    ];

    /** @var array<int,object> */
    private array $attrCache = [];

    public function handle(): int
    {
        $brandName = (string) $this->option('brand');
        $apply     = (bool)  $this->option('apply');

        if (! $brandName) {
            $this->error('--brand is required');
            return self::FAILURE;
        }

        $brand = DB::table('brands')
            ->where('name', $brandName)
            ->orWhere('name', 'like', $brandName . '%')
            ->first();

        if (! $brand) {
            $this->error("Brand not found: {$brandName}");
            return self::FAILURE;
        }

        $this->line($apply
            ? '<fg=red;options=bold>APPLY: attribute values will be written.</>'
            : '<fg=yellow;options=bold>DRY RUN: nothing will be written.</>');

        $q = DB::table('products')
            ->where('brand_id', $brand->id)
            ->where('is_archived', false)
            ->whereNotNull('specs')
            ->where('specs', '!=', '')
            ->where('specs', '!=', '[]')
            ->orderBy('id');

        if ($this->option('limit')) {
            $q->limit((int) $this->option('limit'));
        }

        $products = $q->get(['id', 'name', 'category_id', 'specs']);
        $this->info(sprintf('Products to process: %d', $products->count()));

        $stats = ['products' => 0, 'values_created' => 0, 'attrs_created' => 0,
                  'skipped_no_map' => 0, 'skipped_no_value' => 0];

        foreach ($products as $product) {
            $specs = json_decode($product->specs, true);
            if (! is_array($specs) || $specs === []) {
                continue;
            }

            // Supports both [{key,value,unit}] array and {"key":"value"} flat object formats
            $data = [];
            foreach ($specs as $rowKey => $row) {
                if (is_array($row)) {
                    $key  = trim((string) ($row['key'] ?? ''));
                    $val  = trim((string) ($row['value'] ?? ''));
                    $unit = trim((string) ($row['unit'] ?? ''));
                } else {
                    $key  = trim((string) $rowKey);
                    $val  = trim((string) $row);
                    $unit = '';
                }
                if ($key !== '' && $val !== '') {
                    $data[$key] = $val . ($unit !== '' ? ' ' . $unit : '');
                }
            }

            $catId = (int) $product->category_id;
            $map   = self::MAP[$catId] ?? null;

            if (! $map) {
                $stats['skipped_no_map']++;
                $this->line(sprintf('  <fg=yellow>NO MAP</> cat=%d [id=%d] %s', $catId, $product->id, mb_substr($product->name, 0, 50)));
                continue;
            }

            $stats['products']++;
            $printedHeader = false;

            // Collect rows to write first, then delete+insert atomically
            $toWrite = [];

            foreach ($map as $specKey => $mapping) {
                if (! array_key_exists($specKey, $data)) {
                    continue;
                }

                $attr = $this->resolveAttribute($catId, $mapping, $apply, $stats);
                if ($attr === null) {
                    continue;
                }

                $raw = (string) $data[$specKey];
                $useRaw = ! empty($mapping['raw']);
                $parsed = $useRaw ? ($raw !== '' ? $raw : null) : $this->parseValue($raw, $attr->type, (string) ($attr->suffix ?? ''));

                if ($parsed === null) {
                    $stats['skipped_no_value']++;
                    continue;
                }

                // Dedup: same attr may match multiple spec keys — keep first only.
                // Cast id to int to avoid PHP treating int(501) and string("501") as different keys.
                $attrKey = $attr->id !== null ? (int) $attr->id : 'name:' . $attr->name;
                if (isset($toWrite[$attrKey])) {
                    continue;
                }

                $toWrite[$attrKey] = [
                    'attr'   => $attr,
                    'parsed' => $parsed,
                ];
            }

            if (empty($toWrite)) {
                continue;
            }

            if (! $printedHeader) {
                $this->newLine();
                $this->line(sprintf('<fg=cyan>id=%d</> %s', $product->id, mb_substr($product->name, 0, 56)));
                $printedHeader = true;
            }

            // Delete existing values for this product's mapped attr ids, then re-insert
            $attrIds = array_filter(array_map(fn ($k) => is_numeric($k) ? (int) $k : null, array_keys($toWrite)));
            if ($apply && ! empty($attrIds)) {
                DB::table('product_attribute_values')
                    ->where('product_id', $product->id)
                    ->whereIn('attribute_id', $attrIds)
                    ->delete();
            }

            foreach ($toWrite as $attrKey => $row) {
                $attr   = $row['attr'];
                $parsed = $row['parsed'];

                $display = $attr->type === 'check'
                    ? ($parsed === '1' ? 'Да' : 'Нет')
                    : $parsed . ($attr->suffix ? ' ' . $attr->suffix : '');
                $this->line(sprintf('    %s = %s', $attr->name, $display));

                if ($apply && $attr->id) {
                    DB::table('product_attribute_values')->insert([
                        'product_id'   => $product->id,
                        'attribute_id' => $attr->id,
                        'option_id'    => null,
                        'is_checked'   => $attr->type === 'check' ? ($parsed === '1' ? 1 : 0) : null,
                        'value'        => $attr->type === 'check' ? null : $parsed,
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
                $stats['values_created']++;
            }
        }

        $this->newLine();
        $this->table(['metric', 'count'], array_map(fn ($k, $v) => [$k, $v], array_keys($stats), array_values($stats)));

        if (! $apply) {
            $this->line('Re-run with --apply to write changes.');
        }

        return self::SUCCESS;
    }

    private function resolveAttribute(int $catId, array $mapping, bool $apply, array &$stats): ?object
    {
        if (isset($mapping['attr_id'])) {
            $id = (int) $mapping['attr_id'];
            $this->attrCache[$id] ??= DB::table('attributes')->where('id', $id)->first(['id', 'name', 'type', 'suffix']);
            return $this->attrCache[$id] ?: null;
        }

        if (isset($mapping['create'])) {
            $def = $mapping['create'];
            $existing = DB::table('attributes')
                ->where('category_id', $catId)
                ->where('name', $def['name'])
                ->first(['id', 'name', 'type', 'suffix']);
            if ($existing) {
                return $existing;
            }
            if (! $apply) {
                return (object) ['id' => null, 'name' => $def['name'], 'type' => $def['type'], 'suffix' => $def['suffix'] ?? ''];
            }
            $sort = (int) DB::table('attributes')->where('category_id', $catId)->max('sort_order') + 1;
            $id = DB::table('attributes')->insertGetId([
                'category_id'   => $catId,
                'type'          => $def['type'],
                'name'          => $def['name'],
                'suffix'        => $def['suffix'] ?? null,
                'in_product'    => true,
                'in_filter'     => false,
                'in_brief'      => false,
                'in_sort'       => false,
                'is_comparable' => false,
                'sort_order'    => $sort,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $stats['attrs_created']++;
            $this->line("  <fg=green>+ атрибут «{$def['name']}» создан</>");
            return (object) ['id' => $id, 'name' => $def['name'], 'type' => $def['type'], 'suffix' => $def['suffix'] ?? ''];
        }

        return null;
    }

    private function parseValue(string $raw, string $type, string $suffix): ?string
    {
        $raw = trim(preg_replace('/\s+/u', ' ', strip_tags($raw)) ?? '');
        if ($raw === '' || $raw === '—') {
            return null;
        }
        if ($type === 'check') {
            $low = mb_strtolower($raw);
            if (in_array($low, ['да', 'yes', 'есть', '+', 'true', '1'], true)) return '1';
            if (in_array($low, ['нет', 'no', '-', 'false', '0'], true)) return '0';
            return null;
        }
        if ($suffix !== '') {
            if (preg_match('/-?\d+(?:[.,]\d+)?/u', $raw, $m)) {
                return str_replace(',', '.', $m[0]);
            }
            return null;
        }
        if (! preg_match('/\d/u', $raw)) {
            return null;
        }
        return mb_substr(trim($raw, " \t\u{00A0}:;"), 0, 60);
    }
}

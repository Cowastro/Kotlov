<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductCreator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSbgDistinctProductsBatchCommand extends Command
{
    protected $signature = 'integration:create-sbg-distinct-products
        {--batch=chimney-priced-20261012-a : Curated product-creation batch}
        {--code=* : Limit the run to one or more 1C codes}
        {--apply : Create and link the verified cards; default is a dry run}';

    protected $description = 'Create complete product cards for curated SanBusinessGroup variants that must not be merged.';

    private const SOURCE_NAME = '1С СанБизнесГруп';

    private const PARTNER_NAME = 'ООО «СанБизнесГруп»';

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_A = [
        'УТ-00000947' => ['template' => 'KOTLOV-007993', 'fragments' => ['Труба L250', '430-0.8', 'D130']],
        'УТ-00000761' => ['template' => 'PS-009.107', 'fragments' => ['Проход перекрытия', '480х480', 'D220-260']],
        'УТ-00000319' => ['template' => 'PS-009.324', 'fragments' => ['KNSR 150/45', 'CZ2', '2сек']],
        'УТ-00000020' => ['template' => 'PS-009.330', 'fragments' => ['RP120/250', 'CZ2']],
        'УТ-00000019' => ['template' => 'PS-009.343', 'fragments' => ['RP120/1000', 'CZ2']],
        'УТ-00000007' => ['template' => 'PS-009.328', 'fragments' => ['KNSR 150/90', 'CZ2', '3 сек']],
        'УТ-00009583' => ['template' => 'PS-008.994', 'fragments' => ['Адаптер переход', '430-0.8', 'D130/140']],
        'УТ-00009578' => ['template' => 'PS-008.996', 'fragments' => ['Адаптер переход', '430-0.8', 'D160/200']],
        'УТ-00008176' => ['template' => 'PS-008.995', 'fragments' => ['Адаптер переход', '430-0.8', 'D150/180']],
        'УТ-00007477' => ['template' => 'PS-008.990', 'fragments' => ['Адаптер переход', '430-0.8', 'D100/120']],
        'УТ-00004093' => ['template' => 'PS-008.993', 'fragments' => ['Адаптер переход', '430-0.8', 'D120/150']],
        'УТ-00008868' => ['template' => 'PS-008.991', 'fragments' => ['Адаптер переход', '430-0.8', 'D110/115']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_B = [
        'УТ-00001134' => ['template' => 'PS-009.741', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D150']],
        'УТ-00000241' => ['template' => 'KOTLOV-007998', 'candidate' => 'PS-007.788', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D150']],
        'УТ-00000239' => ['template' => 'KOTLOV-007998', 'candidate' => 'PS-007.837', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D120']],
        'УТ-00008738' => ['template' => 'KOTLOV-007984', 'candidate' => 'PS-007.806', 'fragments' => ['Зонт ЗМ-Р', '430-0.5', 'D115']],
        'УТ-00006024' => ['template' => 'PS-009.741', 'candidate' => 'PS-007.954', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D110']],
        'УТ-00003598' => ['template' => 'PS-009.741', 'candidate' => 'PS-007.863', 'fragments' => ['Шибер ШМ-Р', '430-0.8', 'D130']],
        'УТ-00000911' => ['template' => 'PS-009.085', 'candidate' => 'PS-007.858', 'fragments' => ['Тройник 87', '430-0.8', 'D130']],
        'УТ-00008924' => ['template' => 'PS-009.085', 'candidate' => 'PS-007.807', 'fragments' => ['Тройник 87', '430-0.8', 'D115']],
        'УТ-00008923' => ['template' => 'PS-009.691', 'candidate' => 'PS-007.807', 'fragments' => ['Тройник 45', '430-0.8', 'D115']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_C = [
        'УТ-00009647' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.519', 'fragments' => ['Адаптер переход', '430-0.8', 'D200/220']],
        'УТ-00008323' => ['template' => 'KOTLOV-007983', 'candidate' => 'PS-007.811', 'fragments' => ['Конденсатоотвод', '430-0.5', 'D115']],
        'УТ-00008732' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.518', 'fragments' => ['Адаптер переход', '430-0.8', 'D180/200']],
        'УТ-00008832' => ['template' => 'PS-009.051', 'candidate' => 'PS-009.743', 'fragments' => ['Труба Термо L 250', '430-0.8/430', 'D115/200']],
        'УТ-00009413' => ['template' => 'PS-008.996', 'candidate' => 'PS-009.519', 'fragments' => ['Адаптер переход', '430-0.8', 'D200/250']],
    ];

    /** @var array<string, array{template:string,candidate?:string,fragments:list<string>}> */
    private const BATCH_CHIMNEY_PRICED_20261012_D = [
        'УТ-00002027' => ['template' => 'KOTLOV-022564', 'candidate' => 'PS-009.743', 'fragments' => ['Труба L250', '430-0.8', 'D115']],
        'УТ-00008915' => ['template' => 'KOTLOV-007994', 'candidate' => 'PS-003.424', 'fragments' => ['Отвод 87', '430-0.5', 'D115', '3S']],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_E = [
        'УТ-00000962' => [
            'template' => 'PS-009.331', 'fragments' => ['RP 120/500-CZ2', 'ML'],
            'name' => 'DARCO Труба дымоходная RP120/500-CZ2 (ML) Ø120, 2 мм',
            'images' => ['https://sklep.darco.pl/picture/fit-in/608x809/smart/filters:quality(75):fill(white)/ca2d77af23ce0eed739d5bb6a99e2b48'],
            'source_url' => 'https://sklep.darco.pl/ogrzewanie_kominkowe/rura_prosta_rp120500_cz2_mlsz_szara',
            'short' => 'Прямая дымоходная труба DARCO RP120/500-CZ2 (ML) из чёрной стали: Ø120 мм, длина 500 мм, стенка 2 мм.',
            'content' => '<p><strong>DARCO RP120/500-CZ2 (ML)</strong> — прямая труба системы подключения печей и каминов на твёрдом топливе. Изготовлена из чёрной стали и покрыта термостойкой краской Senotherm.</p><p>Диаметр 120 мм, длина 500 мм и толщина стенки 2 мм. Рабочая температура — до 600 °C. Цена сайта хранится отдельно от оптовой цены и остатка 1С.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Прямая дымоходная труба'], ['key' => 'Диаметр', 'value' => '120 мм'],
                ['key' => 'Длина', 'value' => '500 мм'], ['key' => 'Толщина стали', 'value' => '2 мм'],
                ['key' => 'Материал', 'value' => 'Чёрная сталь'], ['key' => 'Покрытие', 'value' => 'Термостойкая краска Senotherm'],
                ['key' => 'Рабочая температура', 'value' => 'до 600 °C'], ['key' => 'Гарантия', 'value' => '24 месяца'],
            ],
        ],
        'УТ-00000616' => [
            'template' => 'PS-009.932', 'fragments' => ['Милана', '8мм'],
            'name' => 'Царь-Печи Милана, отопительная печь 8 мм',
            'images' => [
                'https://www.r-sauna.ru/assets/images/products/9802/milana.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9802/milana-21.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9802/milana-shema.jpeg',
            ],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-milana-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Милана с топкой 8 мм для помещений объёмом 35–45 м³.',
            'content' => '<p><strong>Царь-Печи Милана 8 мм</strong> — компактная дровяная отопительная печь с конвекционными экранами и варочной поверхностью. Рассчитана на быстрый прогрев помещений объёмом 35–45 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '35–45 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '480×420×420 мм'],
                ['key' => 'Масса', 'value' => '35 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '40 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
        'УТ-00000613' => [
            'template' => 'PS-009.932', 'fragments' => ['Золовка', '8мм'],
            'name' => 'Царь-Печи Золовка, отопительная печь 8 мм',
            'images' => ['https://www.r-sauna.ru/assets/images/products/9796/zolovka.jpeg'],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-zolovka-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Золовка с топкой 8 мм для помещений объёмом 50–70 м³.',
            'content' => '<p><strong>Царь-Печи Золовка 8 мм</strong> — дровяная отопительная печь с конвекционными экранами и варочной поверхностью для помещений объёмом 50–70 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '50–70 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '480×500×550 (675) мм'],
                ['key' => 'Масса', 'value' => '60 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '45 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
        'УТ-00000611' => [
            'template' => 'PS-009.932', 'fragments' => ['Злата', '8мм'],
            'name' => 'Царь-Печи Злата, отопительная печь 8 мм',
            'images' => [
                'https://www.r-sauna.ru/assets/images/products/9795/zlata.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9795/zlata-31.jpeg',
                'https://www.r-sauna.ru/assets/images/products/9795/zlata-shema-2.jpeg',
            ],
            'source_url' => 'https://www.r-sauna.ru/catalog/otopit/otopitelnye-pechi-car-pechi/otopitelnaya-pech-zlata-8mm/',
            'short' => 'Стальная отопительная печь Царь-Печи Злата с топкой 8 мм для помещений объёмом 35–50 м³.',
            'content' => '<p><strong>Царь-Печи Злата 8 мм</strong> — дровяная отопительная печь с варочной поверхностью для дачи, дома, гаража или мастерской объёмом до 50 м³.</p><p>Топка изготовлена из стали толщиной 8 мм; диаметр дымохода — 115 мм. Цена сайта рассчитана с сохранением минимальной валовой маржи относительно оптовой цены 1С.</p>',
            'specs' => [
                ['key' => 'Объём помещения', 'value' => '35–50 м³'], ['key' => 'Размеры (Ш×Г×В)', 'value' => '560×410×480 мм'],
                ['key' => 'Масса', 'value' => '60 кг'], ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Толщина топки', 'value' => '8 мм'], ['key' => 'Максимальная длина полена', 'value' => '40 см'],
                ['key' => 'Топливо', 'value' => 'Дрова'], ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_F = [
        'УТ-00001178' => [
            'template' => 'PS-007.697', 'fragments' => ['ELCON', '520 мл', 'аэрозоль', 'черная'],
            'brand' => [
                'name' => 'ELCON',
                'slug' => 'elcon',
                'country' => 'Россия',
                'content' => '<p><strong>ELCON</strong> — российский разработчик и производитель специальных лакокрасочных материалов, включая термостойкие покрытия для печей, каминов, мангалов и дымоходов.</p>',
            ],
            'name' => 'ELCON Max Therm эмаль термостойкая чёрная, аэрозоль 520 мл, до 1000 °C',
            'images' => ['https://elcon.ru/upload/iblock/f6e/ei9ai7nmri8olju0ojfiespb506bxm6a.webp'],
            'source_url' => 'https://elcon.ru/production/emali-i-grunty-po-metallu/elcon-mangal/',
            'short' => 'Чёрная матовая термостойкая эмаль ELCON Max Therm в аэрозоле 520 мл для поверхностей, нагревающихся до 1000 °C.',
            'content' => '<p><strong>ELCON Max Therm</strong> — чёрная термостойкая аэрозольная эмаль для защитно-декоративной окраски мангалов, печей, банного оборудования, радиаторов, дымовых труб и других нагреваемых поверхностей.</p><p>Покрытие выдерживает температуру от −60 до +1000 °C, не требует предварительного грунтования и подходит для металла, бетона и кирпича. Аэрозольная упаковка объёмом 520 мл удобна для равномерного нанесения на детали сложной формы.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Термостойкая аэрозольная эмаль'],
                ['key' => 'Серия', 'value' => 'Max Therm'],
                ['key' => 'Объём', 'value' => '520 мл'],
                ['key' => 'Цвет', 'value' => 'Чёрный (RAL 9005)'],
                ['key' => 'Степень блеска', 'value' => 'Матовая'],
                ['key' => 'Термостойкость', 'value' => 'до 1000 °C'],
                ['key' => 'Температура нанесения', 'value' => 'от −30 до +40 °C'],
                ['key' => 'Расход на 1 слой', 'value' => '120–160 г/м²'],
                ['key' => 'Предварительное грунтование', 'value' => 'Не требуется'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_G = [
        'УТ-00000430' => [
            'template' => 'PS-009.119',
            'candidate' => 'PS-004.961',
            'fragments' => ['Крепление подвес', 'КП 350'],
            'name' => 'Теплов и Сухов Крепление-подвес КП 350, комплект 2 шт.',
            'images' => ['https://aquadom.info/upload/iblock/20a/yyvc6hgtzh97c17e0pkmab40cyk6l3u3.jpg'],
            'source_url' => 'https://aquadom.info/products/kreplenie-podves-kp-350/',
            'short' => 'Комплект из двух креплений-подвесов Теплов и Сухов КП 350 для фиксации дымохода на расстоянии до 350 мм от стены.',
            'content' => '<p><strong>Теплов и Сухов КП 350</strong> — комплект из двух креплений-подвесов для удержания дымохода в вертикальном положении на расстоянии до 350 мм от стены. Подвижная пластина позволяет смещать дымоход по горизонтали.</p><p>Крепление используется совместно с универсальным креплением и не является разгружающим элементом: основной вес дымохода должна принимать монтажная площадка или опора. Комплект изготовлен из нержавеющей стали AISI 430.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Крепление-подвес'],
                ['key' => 'Длина', 'value' => '350 мм'],
                ['key' => 'Комплектация', 'value' => '2 крепления'],
                ['key' => 'Материал', 'value' => 'Нержавеющая сталь'],
                ['key' => 'Марка стали', 'value' => 'AISI 430'],
                ['key' => 'Толщина стали', 'value' => '1 мм'],
                ['key' => 'Рабочая температура', 'value' => 'до 600 °C'],
                ['key' => 'Размер упаковки (Д×Ш×В)', 'value' => '350×60×50 мм'],
                ['key' => 'Масса в упаковке', 'value' => '0,775 кг'],
                ['key' => 'Артикул производителя', 'value' => 'TS.KMP.KRP.0000.66285'],
                ['key' => 'Штрихкод', 'value' => '4680205277184'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_H = [
        'УТ-00000263' => [
            'template' => 'PS-000.123',
            'fragments' => ['габбро-диабаз', 'обвал', 'кор'],
            'allow_brandless' => true,
            'name' => 'Камни для бани габбро-диабаз обвалованный, 20 кг',
            'images' => ['https://teplodar.by/images/magazin/tovar/kamni_g-d_obval.png'],
            'source_url' => 'https://teplodar.by/kamni-dlya-bani/kamni-gabbro-diabaz-obvalovannyj.html',
            'short' => 'Натуральный обвалованный габбро-диабаз для банных печей и каменок, фасовка 20 кг.',
            'content' => '<p><strong>Габбро-диабаз обвалованный</strong> — натуральный камень для банных печей и каменок. После обваловки камни получают закруглённые края, благодаря чему вода легче проходит в глубину каменки.</p><p>Камень поставляется в картонной коробке массой 20 кг. Перед первой укладкой камни необходимо промыть; в процессе эксплуатации их рекомендуется периодически перебирать и удалять растрескавшиеся элементы.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Натуральный камень для бани'],
                ['key' => 'Порода', 'value' => 'Габбро-диабаз'],
                ['key' => 'Обработка', 'value' => 'Обвалованный'],
                ['key' => 'Фасовка', 'value' => '20 кг'],
                ['key' => 'Упаковка', 'value' => 'Картонная коробка'],
                ['key' => 'Размер упаковки (Ш×Д×В)', 'value' => '400×300×150 мм'],
                ['key' => 'Происхождение', 'value' => 'Республика Карелия, Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_I = [
        'УТ-00000045' => [
            'template' => 'PS-007.853',
            'fragments' => ['Адаптер Моно М-М', '430-0.8', 'D130'],
            'brand' => [
                'name' => 'Теплов и Сухов',
                'slug' => 'teplov-i-suhov',
                'country' => 'Россия',
                'content' => '<p><strong>Теплов и Сухов</strong> — российский производитель модульных дымоходов и комплектующих из нержавеющей стали.</p>',
            ],
            'name' => 'Теплов и Сухов Адаптер Моно М-М 430-0.8 D130 (У)',
            'images' => ['https://cdn.vseinstrumenti.ru/images/goods/santehnicheskoe-oborudovanie/komplektuyuschie-i-rashodnye-materialy-dlya-santehniki/1408338/560x504/51404470.jpg'],
            'source_url' => 'https://www.vseinstrumenti.ru/product/adapter-mono-m-m-430-0-8-d130-teplovisuhov-u-ts-frt-akl-0130-32856-1408338/',
            'short' => 'Одноконтурный адаптер Моно М-М Теплов и Сухов Ø130 мм из нержавеющей стали AISI 430 толщиной 0,8 мм.',
            'content' => '<p><strong>Теплов и Сухов Адаптер Моно М-М 430-0.8 D130</strong> — одноконтурный элемент дымохода для соединения участков диаметром 130 мм. Исполнение М-М предназначено для стыковки элементов с соответствующим типом соединения.</p><p>Адаптер изготовлен из нержавеющей стали AISI 430 толщиной 0,8 мм. Перед монтажом необходимо проверить совпадение диаметра и направления сборки дымохода.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Адаптер одноконтурный'],
                ['key' => 'Система', 'value' => 'Моно'],
                ['key' => 'Тип соединения', 'value' => 'М-М'],
                ['key' => 'Диаметр', 'value' => '130 мм'],
                ['key' => 'Материал', 'value' => 'Нержавеющая сталь'],
                ['key' => 'Марка стали', 'value' => 'AISI 430'],
                ['key' => 'Толщина стали', 'value' => '0,8 мм'],
                ['key' => 'Масса нетто', 'value' => '0,4 кг'],
                ['key' => 'Артикул производителя', 'value' => 'TS.FRT.AKL.0130.32856'],
                ['key' => 'Штрихкод', 'value' => '4680205063992'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_J = [
        'УТ-00000044' => [
            'template' => 'PS-007.944',
            'fragments' => ['Адаптер Моно М-М', '430-0.8', 'D110'],
            'brand' => [
                'name' => 'Теплов и Сухов',
                'slug' => 'teplov-i-suhov',
                'country' => 'Россия',
                'content' => '<p><strong>Теплов и Сухов</strong> — российский производитель модульных дымоходов и комплектующих из нержавеющей стали.</p>',
            ],
            'name' => 'Теплов и Сухов Адаптер Моно М-М 430-0.8 D110 (У)',
            'images' => ['https://kaminchi.ru/image/cache/data/teplov-suhov/dymoxody-modulnye-iz-nerzhaveyushhej-stali/IMG17586c5773810e092068ef5f09ad9950-1024x768.jpg'],
            'source_url' => 'https://kaminchi.ru/dimohod/dymoxody-modulnye-iz-nerzhaveyushhej-stali/adapter-mono-m-m-430-0-8-d110-u',
            'short' => 'Одноконтурный адаптер Моно М-М Теплов и Сухов Ø110 мм из нержавеющей стали AISI 430 толщиной 0,8 мм.',
            'content' => '<p><strong>Теплов и Сухов Адаптер Моно М-М 430-0.8 D110</strong> — одноконтурный элемент для соединения выходного патрубка теплогенерирующего аппарата с дымоходом диаметром 110 мм.</p><p>Адаптер изготовлен из нержавеющей стали AISI 430 толщиной 0,8 мм. Исполнение М-М предназначено для стыковки элементов с соответствующим типом соединения. Перед монтажом необходимо проверить совпадение диаметра и направление сборки дымохода.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Адаптер одноконтурный'],
                ['key' => 'Система', 'value' => 'Моно'],
                ['key' => 'Тип соединения', 'value' => 'М-М'],
                ['key' => 'Диаметр', 'value' => '110 мм'],
                ['key' => 'Материал', 'value' => 'Нержавеющая сталь'],
                ['key' => 'Марка стали', 'value' => 'AISI 430'],
                ['key' => 'Толщина стали', 'value' => '0,8 мм'],
                ['key' => 'Артикул производителя', 'value' => 'TS.FRT.AKL.0110.83435'],
                ['key' => 'Штрихкод', 'value' => '4680205039478'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    private const BATCH_RESEARCHED_PRICED_20261012_K = [
        'УТ-00008043' => [
            'template' => 'PS-010.836',
            'candidate' => 'PS-011.152',
            'fragments' => ['ППШ-М', '304', 'L495', 'D115/115'],
            'name' => 'Теплов и Сухов ППШ-М пароперегреватель трубный AISI 304, L495, D115/115 (К)',
            'retail' => 399.00,
            'images' => [
                'https://kpd-ural.ru/thumb/2/qWnXfn-N0ofrsIuLPQmKrA/600r600/d/ppsh-paro-nagrevatel-trubnyy-l495mm-430-08-f120.png',
                'https://kpd-ural.ru/thumb/2/P-AjQl6mJhkgkmfJsR8A1A/600r600/d/ppsh-paro-nagrevatel-trubnyy-l495mm-430-08-f120-1.png',
            ],
            'source_url' => 'https://kpd-ural.ru/magazin/product/ppsh-m-paroperegrevatel-trubnyj-304-l495-d115/115-k',
            'short' => 'Трубный пароперегреватель Теплов и Сухов ППШ-М для дымохода Ø115 мм: AISI 304, толщина 0,8 мм, длина 495 мм.',
            'content' => '<p><strong>Теплов и Сухов ППШ-М</strong> — трубный пароперегреватель из нержавеющей стали AISI 304. Устанавливается на банную печь как стартовый элемент дымохода диаметром 115 мм и позволяет получать перегретый мелкодисперсный пар.</p><p>Для работы пароперегреватель подключают к баку-ватерпассу, который приобретается отдельно. Перед монтажом проверьте совместимость дымохода и не допускайте работу системы без воды.</p>',
            'specs' => [
                ['key' => 'Тип', 'value' => 'Пароперегреватель трубный'],
                ['key' => 'Производитель', 'value' => 'Теплов и Сухов'],
                ['key' => 'Диаметр дымохода', 'value' => '115 мм'],
                ['key' => 'Длина', 'value' => '495 мм'],
                ['key' => 'Габариты', 'value' => '200×495×200 мм'],
                ['key' => 'Материал', 'value' => 'Нержавеющая сталь'],
                ['key' => 'Марка стали', 'value' => 'AISI 304'],
                ['key' => 'Толщина стали', 'value' => '0,8 мм'],
                ['key' => 'Масса', 'value' => '4,52 кг'],
                ['key' => 'Цвет', 'value' => 'Стальной'],
                ['key' => 'Совместимое оборудование', 'value' => 'Бак-ватерпасс'],
                ['key' => 'Бак-ватерпасс в комплекте', 'value' => 'Нет'],
                ['key' => 'Артикул производителя', 'value' => 'TS.BAK.PPS.0115.79803'],
                ['key' => 'Штрихкод', 'value' => '4680205552700'],
                ['key' => 'Гарантия', 'value' => '12 месяцев'],
                ['key' => 'Страна производства', 'value' => 'Россия'],
            ],
        ],
    ];

    public function handle(IntegrationProductCreator $creator): int
    {
        $rows = match ($this->option('batch')) {
            'chimney-priced-20261012-a' => self::BATCH_CHIMNEY_PRICED_20261012_A,
            'chimney-priced-20261012-b' => self::BATCH_CHIMNEY_PRICED_20261012_B,
            'chimney-priced-20261012-c' => self::BATCH_CHIMNEY_PRICED_20261012_C,
            'chimney-priced-20261012-d' => self::BATCH_CHIMNEY_PRICED_20261012_D,
            'researched-priced-20261012-e' => self::BATCH_RESEARCHED_PRICED_20261012_E,
            'researched-priced-20261012-f' => self::BATCH_RESEARCHED_PRICED_20261012_F,
            'researched-priced-20261012-g' => self::BATCH_RESEARCHED_PRICED_20261012_G,
            'researched-priced-20261012-h' => self::BATCH_RESEARCHED_PRICED_20261012_H,
            'researched-priced-20261012-i' => self::BATCH_RESEARCHED_PRICED_20261012_I,
            'researched-priced-20261012-j' => self::BATCH_RESEARCHED_PRICED_20261012_J,
            'researched-priced-20261012-k' => self::BATCH_RESEARCHED_PRICED_20261012_K,
            default => null,
        };

        if ($rows === null) {
            $this->error('Unknown batch.');

            return self::FAILURE;
        }

        $codes = array_values(array_filter(array_map('strval', (array) $this->option('code'))));
        if ($codes !== []) {
            $rows = array_intersect_key($rows, array_flip($codes));
        }

        $apply = (bool) $this->option('apply');
        $stats = array_fill_keys(['configured', 'ready', 'created', 'brands_created', 'already', 'blocked'], 0);
        $stats['configured'] = count($rows);
        $details = [];

        foreach ($rows as $code => $rule) {
            $items = IntegrationProduct::query()->with(['source', 'product'])->where('external_code', $code)->get();
            $template = Product::query()->where('sku', $rule['template'])->first();
            $expectedCandidate = Product::query()->where('sku', $rule['candidate'] ?? $rule['template'])->first();

            if ($items->count() !== 1 || ! $template || ! $expectedCandidate) {
                $stats['blocked']++;
                $details[] = [$code, $rule['template'], 'blocked', 'missing unique 1C row or template'];

                continue;
            }

            $item = $items->first();
            $portableImages = $rule['images'] ?? $this->portableImages($template);
            if ($item->product_id) {
                if ($item->match_status === 'matched' && $item->match_method === 'manual_created') {
                    $stats['already']++;
                    $details[] = [$code, $item->product?->sku ?: '-', 'already', Str::limit($item->product?->name ?: '', 58)];
                } else {
                    $stats['blocked']++;
                    $details[] = [$code, $rule['template'], 'blocked', 'already linked by another method'];
                }

                continue;
            }

            $candidateIds = collect($item->candidates)->pluck('product_id')->map(fn ($id): int => (int) $id);
            $configuredBrand = $rule['brand'] ?? null;
            $brand = $configuredBrand ? $this->findBrand($configuredBrand) : $template->brand;
            $brandName = trim((string) ($configuredBrand['name'] ?? $brand?->name));
            $name = $rule['name'] ?? $this->productName($brandName, $item->name);
            $valid = $item->source?->name === self::SOURCE_NAME
                && $item->source?->partnerName() === self::PARTNER_NAME
                && $item->match_status === 'ambiguous'
                && (float) $item->stock_quantity > 0
                && $item->normalizedPriceByn()
                && $template->category_id
                && ($brand || $configuredBrand || ($rule['allow_brandless'] ?? false))
                && $portableImages !== []
                && $candidateIds->contains((int) $expectedCandidate->id)
                && $this->containsAll($item->name, $rule['fragments'])
                && Product::query()->where('name', $name)->doesntExist();

            if (! $valid) {
                $stats['blocked']++;
                $details[] = [$code, $rule['template'], 'blocked', 'source, identity, price, category, brand, image or candidate guard failed'];

                continue;
            }

            $stats['ready']++;
            $retail = isset($rule['retail'])
                ? (float) $rule['retail']
                : $this->retailPrice((float) $item->normalizedPriceByn(), (float) $template->price);
            if ($retail < (float) $item->normalizedPriceByn()) {
                $stats['ready']--;
                $stats['blocked']++;
                $details[] = [$code, $rule['template'], 'blocked', 'configured retail price is below supplier price'];

                continue;
            }

            if ($apply) {
                if (! $brand && $configuredBrand) {
                    $brand = $this->createBrand($configuredBrand);
                    $stats['brands_created']++;
                }

                $product = $creator->createAndLink($item, [
                    'name' => $name,
                    'category_id' => $template->category_id,
                    'retail_price' => $retail,
                    'brand_id' => $brand?->id,
                    'h1' => $name,
                    'short_description' => $rule['short'] ?? $this->shortDescription($name),
                    'content' => $rule['content'] ?? $this->content($name, (string) $item->external_sku),
                    'images' => $portableImages,
                    'specs' => $rule['specs'] ?? $this->specs($item),
                    'content_source_url' => $rule['source_url'] ?? null,
                    'meta_title' => Str::limit($name.' — купить в Беларуси | KOTLOV', 255, ''),
                    'meta_description' => Str::limit('Купить '.$name.' в KOTLOV. Характеристики, актуальная цена и наличие, доставка по Беларуси.', 255, ''),
                ]);

                if (! $product) {
                    $stats['blocked']++;
                    $details[] = [$code, $rule['template'], 'blocked', 'row changed during create'];

                    continue;
                }

                $stats['created']++;
                $details[] = [$code, $product->sku, 'created', number_format($retail, 2, '.', '').' BYN · '.$name];
            } else {
                $details[] = [$code, $rule['template'], 'ready', number_format($retail, 2, '.', '').' BYN · '.$name];
            }
        }

        $this->line($apply ? 'APPLY: verified product cards were created.' : 'DRY RUN: no database changes.');
        $this->table(['1C code', 'template/new SKU', 'status', 'detail'], $details);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        return $stats['blocked'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param array{name:string,slug:string} $config */
    private function findBrand(array $config): ?Brand
    {
        return Brand::query()
            ->where('slug', $config['slug'])
            ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($config['name'])])
            ->first();
    }

    /** @param array{name:string,slug:string,country?:string,content?:string} $config */
    private function createBrand(array $config): Brand
    {
        return Brand::query()->create([
            'name' => $config['name'],
            'slug' => $config['slug'],
            'h1' => $config['name'],
            'country' => $config['country'] ?? null,
            'content' => $config['content'] ?? null,
            'meta_title' => $config['name'].' — официальный каталог бренда | KOTLOV',
            'meta_description' => 'Товары бренда '.$config['name'].' в каталоге KOTLOV: характеристики, актуальные цены и доставка по Беларуси.',
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    private function retailPrice(float $wholesale, float $templateRetail): float
    {
        $withMargin = $wholesale / 0.70;

        return ceil(max($withMargin, $templateRetail) * 20) / 20;
    }

    /** @return list<string> */
    private function portableImages(Product $template): array
    {
        return collect($template->imagePaths())
            ->map(function (string $path, int $index) use ($template): string {
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                    || str_starts_with($path, 'product/') || str_starts_with($path, 'products/')
                    || str_starts_with($path, 'img/')) {
                    return $path;
                }

                $resolved = $template->imageUrl($index);

                return str_starts_with($resolved, '/proxy-image/')
                    ? substr($resolved, strlen('/proxy-image/'))
                    : '';
            })
            ->filter()
            ->values()
            ->all();
    }

    private function productName(string $brand, string $sourceName): string
    {
        $sourceName = trim($sourceName);

        return $brand !== '' && ! str_contains(mb_strtolower($sourceName), mb_strtolower($brand))
            ? $brand.' '.$sourceName
            : $sourceName;
    }

    private function shortDescription(string $name): string
    {
        return Str::limit($name.' — отдельная конфигурация элемента дымоходной системы. Размеры, материал и исполнение указаны в характеристиках; актуальное наличие подтверждается данными 1С.', 230, '');
    }

    private function content(string $name, string $article): string
    {
        $articleText = $article !== '' ? ' Артикул производителя: '.e($article).'.' : '';

        return '<p><strong>'.e($name).'</strong> — самостоятельная товарная конфигурация, которая отличается от близких моделей размером или исполнением.'.$articleText.'</p>'
            .'<p>Перед заказом сверяйте диаметр, длину, марку стали и толщину стенки в характеристиках. Цена и складской остаток поставщика учитываются отдельно от розничной цены сайта.</p>';
    }

    /** @return list<array{key:string,value:string}> */
    private function specs(IntegrationProduct $item): array
    {
        $name = (string) $item->name;
        $specs = [];

        if (preg_match('/\b(304|430)\s*[-\/]\s*(\d[\.,]\d)/u', $name, $match)) {
            $specs[] = ['key' => 'Марка стали', 'value' => 'AISI '.$match[1]];
            $specs[] = ['key' => 'Толщина стали', 'value' => str_replace(',', '.', $match[2]).' мм'];
        }
        if (preg_match('/\bD\s*(\d+(?:[\/-]\d+)?)/ui', $name, $match)) {
            $specs[] = ['key' => 'Диаметр', 'value' => str_replace('-', '/', $match[1]).' мм'];
        } elseif (preg_match('/\b(?:RP|KNSR?)\s*(\d+)\/(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Диаметр', 'value' => $match[1].' мм'];
        }
        if (preg_match('/\bL\s*(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Длина', 'value' => $match[1].' мм'];
        } elseif (preg_match('/\bRP\s*\d+\/(\d+)/ui', $name, $match)) {
            $specs[] = ['key' => 'Длина', 'value' => $match[1].' мм'];
        }
        if (preg_match('/\b(45|87|90)\s*\*/u', $name, $match)) {
            $specs[] = ['key' => 'Угол', 'value' => $match[1].'°'];
        } elseif (preg_match('/\bKNSR?\s*\d+\/(45|90)/ui', $name, $match)) {
            $specs[] = ['key' => 'Угол', 'value' => $match[1].'°'];
        }
        if (preg_match('/\b(\d)\s*(?:сек|S)\b/ui', $name, $match)) {
            $specs[] = ['key' => 'Количество секций', 'value' => $match[1]];
        }
        if ($item->external_sku) {
            $specs[] = ['key' => 'Артикул производителя', 'value' => (string) $item->external_sku];
        }

        return $specs;
    }

    /** @param list<string> $fragments */
    private function containsAll(string $value, array $fragments): bool
    {
        $value = mb_strtolower(str_replace([',', '–', '—', ' '], ['.', '-', '-', ''], $value));

        return collect($fragments)->every(function (string $fragment) use ($value): bool {
            $fragment = mb_strtolower(str_replace([',', '–', '—', ' '], ['.', '-', '-', ''], $fragment));

            return str_contains($value, $fragment);
        });
    }
}

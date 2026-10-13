<?php

namespace App\Console\Commands;

use App\Models\IntegrationProduct;
use App\Models\Product;
use App\Services\Integrations\IntegrationProductMatchDecision;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ResolveSbgAmbiguousBatchCommand extends Command
{
    protected $signature = 'integration:resolve-sbg-ambiguous
        {--batch=exact-20261012-a : Curated resolution batch}
        {--code=* : Limit the run to one or more 1C codes}
        {--apply : Persist the verified links; default is a dry run}';

    protected $description = 'Safely link a curated batch of exact SanBusinessGroup 1C matches.';

    private const SOURCE_NAME = '1С СанБизнесГруп';

    private const PARTNER_NAME = 'ООО «СанБизнесГруп»';

    /**
     * Each row was manually reviewed against the production ambiguous queue.
     * The command still refuses to apply it unless both names and the current
     * candidate set keep confirming the same exact product identity.
     *
     * @var array<string, array{sku:string,item:list<string>,product:list<string>,search?:bool}>
     */
    private const BATCH_EXACT_20261012_A = [
        'УТ-00000567' => ['sku' => 'KOTLOV-007963', 'item' => ['Отвод 45', '430-0.5', 'D80', '2S'], 'product' => ['Отвод 45', '430-0.5', 'D80', '2S']],
        'УТ-00000561' => ['sku' => 'KOTLOV-007972', 'item' => ['Отвод 45', '430-0.5', 'D100', '2S'], 'product' => ['Отвод 45', '430-0.5', 'D100', '2S']],
        'УТ-00000346' => ['sku' => 'PS-009.016', 'item' => ['Конденсатоотвод', '430-0.5', 'D120'], 'product' => ['Конденсато', '430', '0,5', '120']],
        'УТ-00000345' => ['sku' => 'KOTLOV-007983', 'item' => ['Конденсатоотвод', '430-0.5', 'D110'], 'product' => ['Конденсатоотвод', '430-0.5', 'D110']],
        'УТ-00000344' => ['sku' => 'KOTLOV-007975', 'item' => ['Конденсатоотвод', '430-0.5', 'D100'], 'product' => ['Конденсатоотвод', '430-0.5', 'D100']],
        'УТ-00000318' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2сек'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000032' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2 сек'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000005' => ['sku' => 'PS-009.327', 'item' => ['KNS', '130/45', 'CZ2', '2ceк'], 'product' => ['Darco', 'KNS130/45', 'CZ2', '2 сек']],
        'УТ-00000211' => ['sku' => 'PS-009.002', 'item' => ['Заглушка ревизии', '430-0.5', 'D150'], 'product' => ['Заглушка ревизии', '430', '0,5', '150']],
        'УТ-00000207' => ['sku' => 'KOTLOV-007982', 'item' => ['Заглушка ревизии', '430-0.5', 'D110'], 'product' => ['Заглушка ревизии', '430-0.5', 'D110']],
        'УТ-00001518' => ['sku' => 'KOTLOV-007974', 'item' => ['Заглушка ревизии', '430-0.5', 'D100'], 'product' => ['Заглушка ревизии', '430-0.5', 'D100']],
        'УТ-00001517' => ['sku' => 'KOTLOV-007966', 'item' => ['Конденсатоотвод', '430-0.5', 'D80'], 'product' => ['Конденсатоотвод', '430-0.5', 'D80']],
        'УТ-00002026' => ['sku' => 'KOTLOV-007965', 'item' => ['Заглушка ревизии', '430-0.5', 'D80'], 'product' => ['Заглушка ревизии', '430-0.5', 'D80']],
        'УТ-00008135' => ['sku' => 'PS-011.149', 'item' => ['Дверка ВЕЗУВИЙ', '223', 'Бронза'], 'product' => ['Дверка ВЕЗУВИЙ', '223', 'Бронза']],
        'УТ-00006301' => ['sku' => 'PS-011.839', 'item' => ['Мангал', 'Поход 1'], 'product' => ['Мангал', 'Пегас', 'Поход 1']],
        'УТ-00006157' => ['sku' => 'PS-011.852', 'item' => ['Обрезь базальта', '3кг'], 'product' => ['Обрезь базальта', '3кг']],
        'УТ-00005590' => ['sku' => 'PS-009.930', 'item' => ['Буржуйка мини', '8 мм'], 'product' => ['Буржуйка мини', '8 мм']],
        'УТ-00005218' => ['sku' => 'PS-009.931', 'item' => ['Буржуйка с конфоркой', '8 мм'], 'product' => ['Буржуйка с конфоркой', '8 мм']],
        'УТ-00004236' => ['sku' => 'PS-009.929', 'item' => ['Буржуйка мини с конфоркой', '8мм'], 'product' => ['Буржуйка мини с комфоркой', '8 мм']],
        'УТ-00003017' => ['sku' => 'PS-008.924', 'item' => ['Гидравлический разделитель', 'Г-50/2'], 'product' => ['Гидравлический разделитель', 'Г-50/2']],
        'УТ-00002857' => ['sku' => 'PS-007.059', 'item' => ['Счетчик газа', 'СГБ', '1,8', 'Элехант'], 'product' => ['Счетчик газа', 'Элехант', 'СГБ-1,8']],
        'УТ-00009513' => ['sku' => 'KOTLOV-022421', 'item' => ['КПД', 'Отвод 45', '2мм', 'ф120'], 'product' => ['КПД', 'Отвод 45', '2мм', 'ф120']],
        'УТ-00009473' => ['sku' => 'KOTLOV-022428', 'item' => ['КПД', 'Отвод 90', '2мм', 'ф120', 'ревизией'], 'product' => ['КПД', 'Отвод 90', '2мм', 'ф120', 'ревизией']],
        'УТ-00009461' => ['sku' => 'KOTLOV-022412', 'item' => ['КПД', 'Труба 500мм', '2мм', 'ф120'], 'product' => ['КПД', 'Труба 500мм', '2мм', 'ф120']],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261012_B = [
        'УТ-00001152' => ['sku' => 'PS-006.071', 'item' => ['Шланг газовый', 'Ш1/2', '1,5', 'Г1/2'], 'product' => ['Шланг для газа', 'Гранд', '1/2x1/2', '150']],
        'УТ-00001101' => ['sku' => 'PS-009.072', 'item' => ['Хомут трубный', 'на болте', 'D 260'], 'product' => ['Хомут трубный', 'Теплов и Сухов', 'на болте', '260']],
        'УТ-00000760' => ['sku' => 'PS-009.108', 'item' => ['Проход перекрытия', '500х500', 'D280-310'], 'product' => ['Проход перекрытия', 'Теплов и Сухов', '500х500', '280-310']],
        'УТ-00000610' => ['sku' => 'PS-009.932', 'item' => ['Отопительная печь', 'Буржуйка', '8мм'], 'product' => ['Отопительная печь', 'Царь-Печи', 'Буржуйка', '8 мм']],
        'УТ-00009645' => ['sku' => 'PS-011.176', 'item' => ['NL-FLM50-160ll', 'R290', '400V'], 'product' => ['KOTLOV GE', 'NL-FLM50-160II', 'R290']],
        'УТ-00009644' => ['sku' => 'PS-011.177', 'item' => ['NL-FLM30-130II', 'R290', '400V'], 'product' => ['KOTLOV GE', 'NL-FLM30-130II', 'R290']],
        'УТ-00009643' => ['sku' => 'PS-011.178', 'item' => ['NL-FLM30-100II', 'R290', '400V'], 'product' => ['KOTLOV GE', 'NL-FLM30-100II', 'R290']],
        'УТ-00009626' => ['sku' => 'PS-011.620', 'item' => ['TERMICA', 'AMET 150', 'INOX'], 'product' => ['TERMICA', 'AMET 150', 'INOX']],
        'УТ-00001188' => ['sku' => 'PS-009.324', 'item' => ['KNS', '120/45', 'CZ2', '2сек'], 'product' => ['Darco', 'KNS120/45', 'CZ2', '2 сек']],
        'УТ-00008134' => ['sku' => 'PS-009.051', 'item' => ['Труба Термо', 'L 250', '430-0.8/430', 'D250/310'], 'product' => ['Труба Теплов и Сухов термо', '430', '0.8/430', '250/310']],
        'УТ-00008102' => ['sku' => 'KOTLOV-022438', 'item' => ['КПД', 'Шибер', '250мм', '2мм', 'ф120'], 'product' => ['КПД', 'Шибер', '250мм', '2мм', 'ф120']],
        'УТ-00007817' => ['sku' => 'KOTLOV-022439', 'item' => ['КПД', 'Шибер', '250мм', '2мм', 'ф150'], 'product' => ['КПД', 'Шибер', '250мм', '2мм', 'ф150']],
        'УТ-00007816' => ['sku' => 'PS-011.848', 'item' => ['КПД', 'Розета', '0,7мм', 'ф150'], 'product' => ['КПД', 'Розета', '0,7мм', 'ф150']],
        'УТ-00007815' => ['sku' => 'KOTLOV-022457', 'item' => ['КПД', 'Гильза', 'к/о', '2мм', 'ф150'], 'product' => ['КПД', 'Гильза', 'К/О', '2мм', 'ф150']],
        'УТ-00007723' => ['sku' => 'PS-007.937', 'item' => ['Площадка монтажная', '430-0.8', 'D200'], 'product' => ['Площадка монтажная', 'FERRUM', '430/0,8', 'Ф200/280']],
        'УТ-00007498' => ['sku' => 'PS-007.917', 'item' => ['Площадка монтажная', '430-0.8', 'D180'], 'product' => ['Площадка монтажная', 'FERRUM', '430/0,8', 'Ф180/280']],
        'УТ-00006961' => ['sku' => 'KOTLOV-008120', 'item' => ['Хомут трубный', 'на болте', 'D 115'], 'product' => ['Теплов и Сухов', 'Хомут трубный', 'на болте', 'D 115']],
        'УТ-00006145' => ['sku' => 'PS-009.117', 'item' => ['Крепление универсальное', 'D 180-190'], 'product' => ['Крепление универсальное', 'Теплов и Сухов', '180']],
        'УТ-00005240' => ['sku' => 'PS-008.992', 'item' => ['Адаптер переход', '430-0.8', 'D115/120'], 'product' => ['Адаптер-переход', 'Теплов и Сухов', '430-0.8', '115/120']],
        'УТ-00005197' => ['sku' => 'PS-007.844', 'item' => ['Площадка монтажная', '430-0.8', 'D120'], 'product' => ['Площадка монтажная', 'FERRUM', '430/0,8', 'Ф120/200']],
        'УТ-00003224' => ['sku' => 'KOTLOV-008124', 'item' => ['Хомут трубный', 'на болте', 'D 80'], 'product' => ['Теплов и Сухов', 'Хомут трубный', 'на болте', 'D 80']],
        'УТ-00001396' => ['sku' => 'PS-009.001', 'item' => ['Заглушка ревизии', '430-0.5', 'D120'], 'product' => ['Заглушка ревизии', '430', '0,5', '120']],
        'УТ-00008821' => ['sku' => 'KOTLOV-008051', 'item' => ['Шибер', 'переход Моно-Термо', '430-0,8/430', 'D115/200'], 'product' => ['Теплов и Сухов', 'Шибер', 'переход Моно-Термо', 'D115/200']],
        'УТ-00008723' => ['sku' => 'KOTLOV-022456', 'item' => ['КПД', 'Гильза', 'к/о', '2мм', 'ф120'], 'product' => ['КПД', 'Гильза', 'К/О', '2мм', 'ф120']],
        'УТ-00008916' => ['sku' => 'PS-007.813', 'item' => ['Площадка монтажная', '430-0.8', 'D115'], 'product' => ['Площадка монтажная', 'FERRUM', '430/0,8', 'Ф115/200']],
        'УТ-00008425' => ['sku' => 'KOTLOV-007772', 'item' => ['Тройник Stahlmann', '20х20х20'], 'product' => ['Тройник', '20х20х20']],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261012_C = [
        'УТ-00000265' => ['sku' => 'PS-003.807', 'item' => ['Камень для саун', 'талькохлорит', 'колот'], 'product' => ['Камни', 'Талькохлорит', 'колотый', '20 кг']],
        'УТ-00000264' => ['sku' => 'PS-000.124', 'item' => ['Камень для саун', 'малиновый кварцит', 'кор'], 'product' => ['Камни', 'Малиновый кварцит', 'колотый', '20 кг']],
        'УТ-00000262' => ['sku' => 'PS-000.123', 'item' => ['Камень для саун', 'габбро-диабаз', 'кор'], 'product' => ['Камни', 'Габбро-диабаз', 'колотый', '20 кг']],
        'УТ-00003514' => ['sku' => 'PS-009.746', 'item' => ['Мастика индустриальная', 'Мастер+', '2 кг'], 'product' => ['Мастика термостойкая', 'Мастер+', '2кг']],
        'УТ-00003513' => ['sku' => 'PS-009.747', 'item' => ['Мастика индустриальная', 'Мастер+', '6 кг'], 'product' => ['Мастика термостойкая', 'Мастер+', '6кг']],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261012_D = [
        'УТ-00000051' => ['sku' => 'PS-008.991', 'item' => ['Адаптер переход', '430-0.8', 'D110/120'], 'product' => ['Теплов и Сухов', 'Адаптер-переход', '430-0.8', '110/120']],
        'УТ-00007784' => ['sku' => 'PS-011.560', 'item' => ['Топка каминная', 'ТКТ 10.0-05', '5161'], 'product' => ['Топка каминная', 'ТКТ 10.0-05', 'Аврора М']],
        'УТ-00006963' => ['sku' => 'PS-011.131', 'item' => ['Шибер-задвижка', '430-0.8', 'D120'], 'product' => ['Шибер-задвижка', 'Теплов и Сухов', 'ф120'], 'search' => true],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>,search:bool}> */
    private const BATCH_EXACT_20261012_E = [
        'УТ-00001564' => [
            'sku' => 'PS-007.486',
            'item' => ['Печь-камин', 'АОТ 7.0', 'Ритм'],
            'product' => ['Печь-камин', 'Мета-Бел', 'Ритм', 'АОТ-7'],
            'search' => true,
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>,search:bool}> */
    private const BATCH_EXACT_20261012_F = [
        'УТ-00007867' => [
            'sku' => 'PS-011.626',
            'item' => ['Проход перекрытия', 'утепленный', '590х590', 'D180-210', 'круг'],
            'product' => ['Проход перекрытия', 'утепленный', 'ППУ-У'],
            'search' => true,
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261012_G = [
        'УТ-00003031' => [
            'sku' => 'PS-008.857',
            'item' => ['Электрокотел', 'ElectroVel', '48М', 'НВ', 'клавиши'],
            'product' => ['Электрокотел', 'KOTLOV GE', 'Eko', '48М'],
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261012_H = [
        'УТ-00003027' => [
            'sku' => 'PS-008.853',
            'item' => ['Электрокотел', 'ElectroVel', '18М', 'НВ', 'клавиши'],
            'product' => ['Электрокотел', 'KOTLOV GE', 'Eko', '18М'],
        ],
        'УТ-00003026' => [
            'sku' => 'PS-008.852',
            'item' => ['Электрокотел', 'ElectroVel', '15М', 'НВ', 'Авт'],
            'product' => ['Электрокотел', 'KOTLOV GE', 'Eko', '15М'],
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>,search:bool}> */
    private const BATCH_EXACT_20261013_I = [
        'УТ-00000524' => [
            'sku' => 'PS-004.660',
            'item' => ['Насос циркуляц', 'UPC', '25-60', '180'],
            'product' => ['Насос', 'Unipump', 'UPC', '25-60', '180'],
            'search' => true,
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>,search:bool}> */
    private const BATCH_EXACT_20261013_J = [
        'УТ-00000523' => [
            'sku' => 'PS-008.471',
            'item' => ['Насос циркуляц', 'UPC', '25-40', '180'],
            'product' => ['Насос циркуляционный', 'UNIPUMP', 'UPC', '25-40', '180'],
            'search' => true,
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>}> */
    private const BATCH_EXACT_20261013_K = [
        'УТ-00009655' => [
            'sku' => 'PS-002.366',
            'item' => ['Стабилизатор напряжения', 'Solpi-m', 'SLP-500B', 'EURO'],
            'product' => ['Стабилизатор напряжения', 'Solpi-M', 'SLP-500BA', 'EURO'],
        ],
    ];

    /** @var array<string, array{sku:string,item:list<string>,product:list<string>,search:bool,refresh:array<string,mixed>}> */
    private const BATCH_EXACT_20261013_L = [
        'УТ-00007818' => [
            'sku' => 'PS-011.849',
            'item' => ['КПД', 'Труба', '250мм', '2мм', 'ф150'],
            'product' => ['КПД', 'Труба', '250мм', '2мм', 'ф150'],
            'search' => true,
            'refresh' => [
                'name' => 'КПД Труба дымоходная 250 мм, Ø150, сталь 2 мм, чёрная',
                'retail' => 42.70,
                'images' => ['img/products/generated/kpd-black-pipe-l250-d150-2mm.png'],
                'source_url' => 'https://x-kamin.ru/catalog/dymokhody/dymokhody-iz-nerzhaveyushchey-stali/odnokonturnye-dymokhody/truba-250-mm-d150-tolshchina-2-mm-kpd.html',
                'short' => 'Одностенная дымоходная труба КПД длиной 250 мм и диаметром 150 мм из котловой стали 08ПС толщиной 2 мм, окрашенная термостойкой чёрной эмалью.',
                'content' => '<p><strong>КПД Труба 250 мм Ø150</strong> — короткий прямой элемент одностенного дымохода для подключения дровяных печей и каминов.</p><p>Труба изготовлена из российской котловой стали 08ПС толщиной 2 мм и покрыта чёрной термостойкой эмалью, рассчитанной на температуру до 800 °C. Элемент предназначен для монтажа внутри помещений и не имеет теплоизоляции.</p>',
                'specs' => [
                    ['key' => 'Тип', 'value' => 'Труба дымоходная одностенная'],
                    ['key' => 'Длина', 'value' => '250 мм'],
                    ['key' => 'Диаметр', 'value' => '150 мм'],
                    ['key' => 'Материал', 'value' => 'Котловая сталь'],
                    ['key' => 'Марка стали', 'value' => '08ПС'],
                    ['key' => 'Толщина стали', 'value' => '2 мм'],
                    ['key' => 'Покрытие', 'value' => 'Термостойкая эмаль'],
                    ['key' => 'Цвет', 'value' => 'Чёрный'],
                    ['key' => 'Рабочая температура покрытия', 'value' => 'до 800 °C'],
                    ['key' => 'Изоляция', 'value' => 'Нет'],
                    ['key' => 'Вид топлива', 'value' => 'Дрова'],
                    ['key' => 'Масса', 'value' => '2 кг'],
                    ['key' => 'Гарантия производителя', 'value' => '2 года'],
                    ['key' => 'Страна производства', 'value' => 'Россия'],
                ],
            ],
        ],
    ];

    public function handle(IntegrationProductMatchDecision $decision): int
    {
        $rows = match ($this->option('batch')) {
            'exact-20261012-a' => self::BATCH_EXACT_20261012_A,
            'exact-20261012-b' => self::BATCH_EXACT_20261012_B,
            'exact-20261012-c' => self::BATCH_EXACT_20261012_C,
            'exact-20261012-d' => self::BATCH_EXACT_20261012_D,
            'exact-20261012-e' => self::BATCH_EXACT_20261012_E,
            'exact-20261012-f' => self::BATCH_EXACT_20261012_F,
            'exact-20261012-g' => self::BATCH_EXACT_20261012_G,
            'exact-20261012-h' => self::BATCH_EXACT_20261012_H,
            'exact-20261013-i' => self::BATCH_EXACT_20261013_I,
            'exact-20261013-j' => self::BATCH_EXACT_20261013_J,
            'exact-20261013-k' => self::BATCH_EXACT_20261013_K,
            'exact-20261013-l' => self::BATCH_EXACT_20261013_L,
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
        $stats = array_fill_keys(['configured', 'ready', 'applied', 'refreshed', 'already', 'missing_item', 'missing_product', 'wrong_source', 'unsafe_name', 'unsafe_refresh', 'not_candidate', 'conflict'], 0);
        $stats['configured'] = count($rows);
        $details = [];

        foreach ($rows as $code => $rule) {
            $items = IntegrationProduct::query()->with(['source', 'product'])->where('external_code', $code)->get();
            if ($items->count() !== 1) {
                $stats['missing_item']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'expected exactly one 1C row, found '.$items->count()];

                continue;
            }

            $item = $items->first();
            $product = Product::query()->where('sku', $rule['sku'])->first();
            if (! $product) {
                $stats['missing_product']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'site product not found'];

                continue;
            }

            if ($item->source?->name !== self::SOURCE_NAME || $item->source?->partnerName() !== self::PARTNER_NAME) {
                $stats['wrong_source']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'unexpected supplier/source'];

                continue;
            }

            if (! $this->containsAll($item->name, $rule['item']) || ! $this->containsAll($product->name, $rule['product'])) {
                $stats['unsafe_name']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'identity guard no longer matches'];

                continue;
            }

            $refresh = $rule['refresh'] ?? null;
            if ($refresh !== null && (! is_numeric($refresh['retail'] ?? null)
                || (float) $refresh['retail'] <= 0
                || ! is_array($refresh['images'] ?? null)
                || $refresh['images'] === []
                || collect($refresh['images'])->contains(fn ($image): bool => ! is_string($image)
                    || str_contains(mb_strtolower($image), 'pechki66.ru')))) {
                $stats['unsafe_refresh']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'public card refresh guard failed'];

                continue;
            }

            if ($item->product_id) {
                if ((int) $item->product_id === (int) $product->id && $item->match_status === 'matched') {
                    $stats['already']++;
                    $details[] = [$code, $rule['sku'], 'already', Str::limit($product->name, 64)];
                } else {
                    $stats['conflict']++;
                    $details[] = [$code, $rule['sku'], 'blocked', 'already linked to another card'];
                }

                continue;
            }

            if ($item->match_status !== 'ambiguous' || (float) $item->stock_quantity <= 0) {
                $stats['conflict']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'row is no longer an in-stock ambiguous item'];

                continue;
            }

            $candidateIds = collect($item->candidates)->pluck('product_id')->map(fn ($id): int => (int) $id);
            if (! ($rule['search'] ?? false) && ! $candidateIds->contains((int) $product->id)) {
                $stats['not_candidate']++;
                $details[] = [$code, $rule['sku'], 'blocked', 'product is no longer a current candidate'];

                continue;
            }

            $stats['ready']++;
            if ($apply) {
                $linked = ($rule['search'] ?? false)
                    ? $decision->linkSelectedProduct($item, (int) $product->id)
                    : $decision->confirmCandidate($item, (int) $product->id);
                if (! $linked) {
                    $stats['conflict']++;
                    $details[] = [$code, $rule['sku'], 'blocked', 'candidate changed during apply'];

                    continue;
                }
                if ($refresh !== null) {
                    $name = (string) ($refresh['name'] ?? $product->name);
                    $product->forceFill([
                        'name' => $name,
                        'h1' => $name,
                        'price' => (float) $refresh['retail'],
                        'short_description' => $refresh['short'] ?? $product->short_description,
                        'content' => $refresh['content'] ?? $product->content,
                        'images' => $refresh['images'],
                        'specs' => $refresh['specs'] ?? $product->specs,
                        'content_source_url' => $refresh['source_url'] ?? $product->content_source_url,
                        'meta_title' => Str::limit($name.' — купить в Беларуси | KOTLOV', 255, ''),
                        'meta_description' => Str::limit('Купить '.$name.' в KOTLOV. Характеристики, актуальная цена и наличие, доставка по Беларуси.', 255, ''),
                    ])->save();
                    $stats['refreshed']++;
                }
                $stats['applied']++;
            }
            $details[] = [$code, $rule['sku'], $apply ? ($refresh !== null ? 'linked + refreshed' : 'linked') : 'ready', Str::limit($product->name, 64)];
        }

        $this->line($apply ? 'APPLY: verified links were persisted.' : 'DRY RUN: no database changes.');
        $this->table(['1C code', 'site SKU', 'status', 'detail'], $details);
        $this->table(['metric', 'count'], collect($stats)->map(fn ($count, $metric) => [$metric, $count])->values()->all());

        $blocked = $stats['missing_item'] + $stats['missing_product'] + $stats['wrong_source']
            + $stats['unsafe_name'] + $stats['unsafe_refresh'] + $stats['not_candidate'] + $stats['conflict'];

        return $blocked === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<string> $fragments */
    private function containsAll(string $value, array $fragments): bool
    {
        $value = mb_strtolower(str_replace([',', '–', '—'], ['.', '-', '-'], $value));

        return collect($fragments)->every(function (string $fragment) use ($value): bool {
            $fragment = mb_strtolower(str_replace([',', '–', '—'], ['.', '-', '-'], $fragment));

            return str_contains($value, $fragment);
        });
    }
}

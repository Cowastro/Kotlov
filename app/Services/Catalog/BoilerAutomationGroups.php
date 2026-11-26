<?php

namespace App\Services\Catalog;

/**
 * Order in «Автоматика и запчасти для котлов»: what is not boiler automation leaves «Регуляторы» for its own section,
 * fans go to «Вентиляторы наддува», and every regulator gets a «Назначение» group to filter by. Rules match the
 * lower-cased title, first match wins.
 */
final class BoilerAutomationGroups
{
    public const SECTION = 'regulyatory';

    public const FANS = 'ventilyatory-nadduva';

    public const FILTER = 'Назначение';

    /** Title pattern => section the card belongs to (from «Регуляторы» or «Запчасти для котлов»). */
    public const MOVES = [
        '/вентилятор наддува|центробежный вентилятор наддува/u' => self::FANS,
        '/^регулятор тяги/u' => self::SECTION,
        '/головка термостатическ|термоголовка|термостатическая головка|набор термостатическ|термостатический клапан|термостатическ\w* клапан|термостат радиаторный|^сервопривод salus t30/u' => 'radiatornaya-armatura',
        '/^терморегулятор (electrolux|elektrolux) et|^терморегулятор накладной watts/u' => 'termoregulyatory-dlya-teplogo-pola',
        '/^сигнализатор загазованности/u' => 'signalizatory-zagazovannosti',
        '/^(датчик температуры(?!.*hommyn)|датчик уличной|hx 31|hx 52)/u' => 'datchiki',
        '/^датчик протечки|манипулятор шарового крана|модуль съемный управляющий hommyn|кабель соединительный hommyn/u' => 'sistemy-zashchity-ot-protechek',
        '/^клапан [23]-х ходовой|^привод поворотный|^электропривод arm/u' => 'smesitelnye-klapany-i-uzly',
        '/^комплект из двух отопительных контуров/u' => 'komplekty-podklyucheniya',
        '/панель изолирующая|термостат предохранительный|комплект трехходового крана с датчиком бойлера|^комплект подключения бойлера к электрическому котлу/u' => 'zapchasti-dlya-kotlov',
        '/^(термометр|термоманометр)/u' => 'manometry-i-termometry',
        '/^крышка датчика импульсов/u' => 'schetchiki-gaza',
        '/^радиомодуль lorawan/u' => 'schetchiki-vody-i-tepla',
        '/^шланг для газа/u' => 'gazovye-shlangi',
        '/^насос-автомат/u' => 'nasosnyie-stantsii',
        '/^(вместе с фланцем|пеллетная горелка kipi rotary)/u' => 'pelletnye-gorelki',
        '/^пульт управления термофор/u' => 'aksessuary-dlya-bani',
        '/^панель управления thermex|^комплект для подключения бойлера к одноконтурному котлу thermex/u' => 'komplektuyushchie-dlya-vodonagrevateley',
    ];

    /** How the «Назначение» options are listed in the filter: by what the automation is for, boilers first. */
    public const ORDER = [
        'Для твердотопливных котлов',
        'Для котлов с автоподачей (пеллеты, уголь)',
        'Для газовых котлов',
        'Для электрокотлов и ТЭНов',
        'Для насосов',
        'Для смесительных клапанов',
        'Для солнечных коллекторов',
        'Комнатные термостаты и программаторы',
        'Накладные и погружные термостаты',
        'GSM, Wi-Fi и удалённое управление',
        'Коммутация зон и реле',
        'Регуляторы тяги',
        'Умный дом',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Регуляторы»; first match wins. */
    public const GROUPS = [
        '/регулятор тяги/u' => 'Регуляторы тяги',
        '/hommyn|zigbee|умная розетка/u' => 'Умный дом',
        // Stand-alone remote modules; a controller or thermostat that merely has Wi-Fi goes by its purpose.
        '/^(gsm-модем|интернет|модуль (wi-fi|zota gsm|управления zota|дистанционного|удаленного|связи)|температурный gsm|прибор дистанционного|плата удаленного|умный wi-fi пульт|контроллер tech st-(500|65))/u' => 'GSM, Wi-Fi и удалённое управление',
        '/тэн|пуэ-|пэу-|для электрических котлов|пувн|c\.mg3/u' => 'Для электрокотлов и ТЭНов',
        '/солнечн|bsol|din 09/u' => 'Для солнечных коллекторов',
        '/смесительного клапана|din 13/u' => 'Для смесительных клапанов',
        '/подачей топлива|пеллетн|plum|ecomax|блок индикации/u' => 'Для котлов с автоподачей (пеллеты, уголь)',
        '/^(контроллер (котла|твердотопливного котла|вентилятора)|комплект наддува|командн?о-контроллер|комплект (автоматики )?zota turboset|комплект turboset|комплект «микропроцессор|контроллер euroster|регулятор оборотов вентилятора|блок управления с вентилятором|galmet luksus)/u' => 'Для твердотопливных котлов',
        '/центр коммутации|kl ?0[468]|kl 10|интегратор|kg-8\b|клеммная|реле|rx 10|координатор/u' => 'Коммутация зон и реле',
        '/накладн|капил|погружн/u' => 'Накладные и погружные термостаты',
        '/комнатн|программатор|терморегулятор|термостат|регулятор температуры|cr10|ct100|vrt 30|poer|ester|intellicomfort|auraton/u' => 'Комнатные термостаты и программаторы',
        '/vaillant|bosch|buderus|logamatic|calormatic|multimatic|vrc|погодозависим|bfu|модуль подключения|смесительный модуль|каскадный модуль|расширительный модуль/u' => 'Для газовых котлов',
        // «Блок автоматики» alone is the Джилекс pump control unit.
        '/насос|pc1[12]|pl 0[67]|^блок автоматики$/u' => 'Для насосов',
        '/контроллер|командо|turboset|автоматика|euroster|tech st/u' => 'Для твердотопливных котлов',
    ];

    public const PARTS = 'zapchasti-dlya-kotlov';

    /** «Назначение» options of «Запчасти для котлов», in filter order. */
    public const PART_ORDER = [
        'Бункеры для пеллет',
        'Шнековые транспортёры',
        'Запчасти для газовых котлов',
        'Дымоудаление и конденсат',
        'Розжиг и горение',
        'Уплотнители и ручки',
        'Электрика',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Запчасти для котлов». */
    public const PART_GROUPS = [
        '/бункер/u' => 'Бункеры для пеллет',
        '/шнек|транспортер|транспортёр/u' => 'Шнековые транспортёры',
        '/колпак|стабилизатор тяги|нейтрализац|конденсат/u' => 'Дымоудаление и конденсат',
        '/свеча|запальник|турбонасадк|горелк/u' => 'Розжиг и горение',
        '/шнур|уплотн|ручка/u' => 'Уплотнители и ручки',
        '/выключатель автоматический|^предохранитель\b/u' => 'Электрика',
        '/baxi|fondital|ferroli|thermex|vaillant|bosch|buderus|navien|ariston|protherm|трехходов|трёхходов|кран подпитки|подающей магистрали/u' => 'Запчасти для газовых котлов',
    ];

    public const SENSORS = 'datchiki';

    /** «Назначение» options of «Датчики», in filter order. */
    public const SENSOR_ORDER = [
        'Уличные (наружные) датчики',
        'Датчики бойлера и ГВС',
        'Датчики котла и теплоносителя',
        'Датчики дымовых газов',
        'Датчики и реле давления',
        'Термостаты перегрева',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Датчики». */
    public const SENSOR_GROUPS = [
        '/наружн|уличн|погодн|5227|qac/u' => 'Уличные (наружные) датчики',
        '/дымов|pt-1000/u' => 'Датчики дымовых газов',
        '/давлен|датчика?-реле|реле давления/u' => 'Датчики и реле давления',
        '/перегрев|тк24|термик/u' => 'Термостаты перегрева',
        '/бойлер|гвс|горячей воды|водонагрев|as1/u' => 'Датчики бойлера и ГВС',
        '/датчик/u' => 'Датчики котла и теплоносителя',
    ];

    public const GAUGES = 'manometry-i-termometry';

    /** «Назначение» options of «Манометры и термометры», in filter order. */
    public const GAUGE_ORDER = [
        'Манометры',
        'Термоманометры',
        'Термометры стрелочные (биметаллические)',
        'Термометры стеклянные',
        'Краны для манометров',
        'Бобышки, оправы и отборные устройства',
        'Переходники и присоединительные узлы',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Манометры и термометры». */
    public const GAUGE_GROUPS = [
        '/^термоманометр/u' => 'Термоманометры',
        '/^манометр|^указатель предельного давления/u' => 'Манометры',
        '/^термометр стеклянн/u' => 'Термометры стеклянные',
        '/^термометр/u' => 'Термометры стрелочные (биметаллические)',
        '/^кран.*манометр/u' => 'Краны для манометров',
        '/^(бобышка|оправа|устройство отборное)/u' => 'Бобышки, оправы и отборные устройства',
        '/^(переходник|узел присоединительный|штуцер|полусгон)/u' => 'Переходники и присоединительные узлы',
    ];

    /** Section slug => [patterns, option order] of its «Назначение» filter. */
    public static function filters(): array
    {
        return [
            self::SECTION => [self::GROUPS, self::ORDER],
            self::PARTS => [self::PART_GROUPS, self::PART_ORDER],
            self::SENSORS => [self::SENSOR_GROUPS, self::SENSOR_ORDER],
            self::GAUGES => [self::GAUGE_GROUPS, self::GAUGE_ORDER],
            self::METERS => [self::METER_GROUPS, self::METER_ORDER],
        ];
    }

    public const METERS = 'schetchiki-vody-i-tepla';

    /** «Назначение» options of «Счётчики воды и тепла», in filter order. */
    public const METER_ORDER = [
        'Счётчики холодной воды',
        'Счётчики горячей воды',
        'Универсальные счётчики',
        'Турбинные счётчики (Вольтмана)',
        'Монтажные комплекты и вставки',
        'Радиомодули',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Счётчики воды и тепла». */
    public const METER_GROUPS = [
        '/вольтмана|турбинн/u' => 'Турбинные счётчики (Вольтмана)',
        '/^счетчик горяч|^счётчик горяч/u' => 'Счётчики горячей воды',
        '/^сч[её]тчик универсальн/u' => 'Универсальные счётчики',
        '/^сч[её]тчик холодн/u' => 'Счётчики холодной воды',
        '/^(комплект монтажный|вставка ремонтная|узел присоединительный)/u' => 'Монтажные комплекты и вставки',
        '/радиомодул/u' => 'Радиомодули',
    ];

    /** The nominal sizes a meter title names, smallest first. */
    public const DIAMETERS = ['15', '20', '25', '32', '40', '50', '65', '80', '100', '150'];

    /**
     * Further filters of a section besides «Назначение»: section slug => [filter name => [patterns, option order]].
     * A card no pattern matches has no value there.
     */
    public static function extraFilters(): array
    {
        $diameters = [];
        foreach (self::DIAMETERS as $dn) {
            $diameters['/(?:ду|dn)\s*'.$dn.'(?!\d)/u'] = 'Ду '.$dn;
        }

        return [
            self::METERS => ['Диаметр (Ду)' => [$diameters, array_values($diameters)]],
            self::GAS_METERS => [
                'Серия' => [self::GAS_SERIES, self::GAS_SERIES_ORDER],
                'Типоразмер' => [self::GAS_SIZES, array_values(self::GAS_SIZES)],
                'Передача данных' => [self::GAS_DATA, array_values(array_unique(self::GAS_DATA))],
            ],
        ];
    }

    public const GAS_METERS = 'schetchiki-gaza';

    /** «Серия» options in filter order: the meters by popularity, accessories last. */
    public const GAS_SERIES_ORDER = [
        'БелОМО СГД',
        'БелОМО КАТА',
        'БелОМО СКАТ',
        'БелОМО ВЕГА',
        'БелОМО СГМН',
        'Другие производители',
        'Аксессуары',
    ];

    /** Title pattern => «Серия» inside «Счётчики газа»; accessories have their own option. */
    public const GAS_SERIES = [
        '/крышка датчика|датчик импульсов/u' => 'Аксессуары',
        '/вега/u' => 'БелОМО ВЕГА',
        '/скат/u' => 'БелОМО СКАТ',
        '/ката/u' => 'БелОМО КАТА',
        '/сгмн/u' => 'БелОМО СГМН',
        '/сгд/u' => 'БелОМО СГД',
        '/^сч[её]тчик газа/u' => 'Другие производители',
    ];

    /** Title pattern => «Типоразмер» (the G size of a gas meter). */
    public const GAS_SIZES = [
        '/g\s*1[,.]6|сгбм-1[,.]6/u' => 'G1,6',
        '/g\s*2[,.]5/u' => 'G2,5',
        '/g\s*4(?![\d,.])|сгмб-4(?!\d)/u' => 'G4',
        '/g\s*6(?![\d,.])/u' => 'G6',
        '/g\s*10(?![\d,.])/u' => 'G10',
    ];

    /** Title pattern => «Передача данных» of a gas meter. */
    public const GAS_DATA = [
        '/nb-iot/u' => 'Дистанционная (NB-IoT)',
        '/^сч[её]тчик газа/u' => 'Без дистанционной передачи',
    ];

    public static function move(string $title): ?string
    {
        return self::first(self::MOVES, $title);
    }

    public static function group(string $title, array $rules = self::GROUPS): string
    {
        return self::first($rules, $title) ?? 'Прочее';
    }

    public static function match(string $title, array $rules): ?string
    {
        return self::first($rules, $title);
    }

    private static function first(array $rules, string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        foreach ($rules as $pattern => $target) {
            if (preg_match($pattern, $title)) {
                return $target;
            }
        }

        return null;
    }
}

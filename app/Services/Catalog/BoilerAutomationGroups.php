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
        '/^(?!.*(regulus|esbe|смесительн|тр[её]хходов))(?:.*)(?:головка термостатическ|термоголовка|термостатическая головка|набор термостатическ|термостатический клапан|термостатическ\w* клапан|термостат радиаторный|^сервопривод salus t30)/u' => 'radiatornaya-armatura',
        '/^терморегулятор (electrolux|elektrolux) et|^терморегулятор накладной watts/u' => 'termoregulyatory-dlya-teplogo-pola',
        '/^сигнализатор загазованности/u' => 'signalizatory-zagazovannosti',
        '/^(датчик температуры(?!.*hommyn)|датчик уличной|hx 31|hx 52)/u' => 'datchiki',
        '/^датчик протечки/u' => 'datchiki-protechki-vody',
        '/манипулятор шарового крана/u' => 'krany-s-elektroprivodom',
        '/модуль съемный управляющий hommyn|кабель соединительный hommyn/u' => 'moduli-upravleniya-zashchitoj-ot-protechek',
        '/^(oxi колосник|колено для соединения патрубка пеллетной горелки|коммутационная плата \(часть блока управления горелкой|контроллер электронный для пеллетной горелки)/u' => 'zapchasti-dlya-pelletnyh-gorelok',
        '/^клапан [23]-х ходовой|^привод поворотный|^электропривод arm/u' => 'smesitelnye-klapany-i-uzly',
        '/^комплект из двух отопительных контуров/u' => 'komplekty-podklyucheniya',
        '/панель изолирующая|термостат предохранительный|комплект трехходового крана с датчиком бойлера|^комплект подключения бойлера к электрическому котлу/u' => 'zapchasti-dlya-kotlov',
        '/^(термометр|термоманометр)(?!.*(бан|саун))/u' => 'manometry-i-termometry',
        '/^крышка датчика импульсов/u' => 'schetchiki-gaza',
        '/^радиомодуль lorawan/u' => 'schetchiki-vody-i-tepla',
        '/^шланг для газа/u' => 'gazovye-shlangi',
        '/^насос-автомат/u' => 'nasosnyie-stantsii',
        '/^смеситель пластиковый/u' => self::FAUCETS,
        '/^система .*(защиты от потопа|контроля протечки)/u' => self::LEAKS,
        '/^датчик контроля протечки/u' => 'datchiki-protechki-vody',
        '/^(косой|т-образный \(прямой\)|[cс]етчатый промывной( поворотный)?|комбинированный промывной) фильтр .*varmega/u' => 'filtry',
        '/^комнатный термостат \(терморегулятор\) varmega/u' => self::SECTION,
        '/^аквабрайт абф/u' => 'filtry',
        '/^переходник varmega vmtf/u' => 'rezbovye-fitingi',
        '/^труба varmega pe-xa/u' => 'truby-iz-sshitogo-polietilena',
        '/^монтажный ключ varmega/u' => 'instrumenty-dlya-montazha',
        '/^(лента демпферная|плита \(мат\)).*varmega/u' => 'vodyanoy-teplyy-pol',
        '/^насос погружной «фекальник»/u' => 'drenajnyie',
        '/^(привод|патрон) тр[её]хходового клапана для котлов.*fondital/u' => self::PARTS,
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
        $pumps = [];
        foreach (self::POWERED_PUMPS as $slug) {
            $pumps[$slug] = ['Мощность двигателя' => [fn (string $title) => self::pumpPower($title), self::PUMP_POWERS]];
            // Outside the pump picker (no head/flow data there) the supply is a plain filter read from the title.
            if (in_array($slug, self::PLAIN_VOLTAGE_PUMPS, true)) {
                $pumps[$slug]['Питание'] = [self::PUMP_VOLTAGES, array_values(self::PUMP_VOLTAGES)];
            }
        }

        return [
            self::METERS => ['Диаметр (Ду)' => [$diameters, array_values($diameters)]],
            self::GAS_METERS => [
                'Серия' => [self::GAS_SERIES, self::GAS_SERIES_ORDER],
                'Типоразмер' => [self::GAS_SIZES, array_values(self::GAS_SIZES)],
                'Передача данных' => [self::GAS_DATA, array_values(array_unique(self::GAS_DATA))],
            ],
            self::RADIATOR_VALVES => [
                'Тип' => [self::RADIATOR_TYPES, self::RADIATOR_ORDER],
                'Исполнение' => [self::RADIATOR_SHAPES, array_values(array_unique(self::RADIATOR_SHAPES))],
                'Цвет' => [self::COLORS, array_values(self::COLORS)],
            ],
            self::MIXING_VALVES => [
                'Тип' => [self::MIXING_TYPES, self::MIXING_ORDER],
            ],
            self::PELLET_BURNERS => [
                'Тип' => [self::PELLET_TYPES, array_values(array_unique(self::PELLET_TYPES))],
                // Not plain «Мощность»: the catalog hides filters of that name on a parent section.
                self::PELLET_POWER_FILTER => [fn (string $title) => self::pelletPower($title), self::PELLET_POWERS],
            ],
            self::LEAKS => [
                'Тип' => [self::LEAK_TYPES, self::LEAK_ORDER],
                'Диаметр' => [self::LEAK_SIZES, ['1/2"', '3/4"', '1"', '1 1/4"', '1 1/2"', '2"']],
                'Напряжение привода' => [self::LEAK_VOLTAGES, array_values(self::LEAK_VOLTAGES)],
            ],
            self::CONNECTION_KITS => [
                'Тип' => [self::KIT_TYPES, self::KIT_ORDER],
                'Исполнение' => [self::RADIATOR_SHAPES, array_values(array_unique(self::RADIATOR_SHAPES))],
                'Цвет' => [self::COLORS, array_values(self::COLORS)],
            ],
            self::GAS_ALARMS => [
                'Какой газ' => [self::ALARM_GASES, self::ALARM_ORDER],
            ],
            self::FANS => [
                'Производительность' => [fn (string $title) => self::fanFlow($title), self::FAN_FLOWS],
                'Для котла мощностью' => [fn (string $title) => self::fanBoilerPower($title), self::FAN_BOILER_POWERS],
            ],
            ...$pumps,
            self::BATH_ACCESSORIES => [
                'Тип' => [self::BATH_TYPES, array_values(array_unique(self::BATH_TYPES))],
            ],
            self::PUMP_PARTS => [
                'Тип' => [self::PUMP_PART_TYPES, self::PUMP_PART_ORDER],
            ],
            self::PLUMBING_PARTS => [
                'Тип' => [self::PLUMBING_TYPES, array_values(array_unique(self::PLUMBING_TYPES))],
            ],
            self::SEWER => [
                'Тип' => [self::SEWER_TYPES, self::SEWER_ORDER],
                'Диаметр' => [fn (string $title) => self::sewerDiameter($title), array_map(fn (int $mm) => $mm.' мм', self::SEWER_DIAMETERS)],
                'Длина трубы' => [fn (string $title) => self::sewerPipeLength($title), array_map(fn (int $mm) => $mm.' мм', self::SEWER_LENGTHS)],
                'Малошумные' => [['/малошум|skolan|stilte/u' => 'Малошумные (Skolan, Stilte)'], ['Малошумные (Skolan, Stilte)']],
            ],
            self::MANIFOLDS => [
                'Тип' => [self::MANIFOLD_TYPES, self::MANIFOLD_ORDER],
                'Число выходов' => [fn (string $title) => self::manifoldOutlets($title), array_map(fn (int $n) => self::outletsLabel($n), range(2, 16))],
            ],
            self::PEX_FITTINGS => [
                'Тип' => [self::PEX_TYPES, self::PEX_ORDER],
                'Материал' => [self::PEX_MATERIALS, array_values(self::PEX_MATERIALS)],
                'Резьба' => [self::PEX_THREADS, array_values(array_unique(self::PEX_THREADS))],
                'Диаметр' => [fn (string $title) => self::pipeDiameter($title, self::PEX_DIAMETERS), array_map(fn (int $mm) => $mm.' мм', self::PEX_DIAMETERS)],
            ],
            self::PE_FITTINGS => [
                'Тип' => [self::PE_TYPES, self::PE_ORDER],
                'Способ соединения' => [self::PE_JOINTS, array_values(array_unique(self::PE_JOINTS))],
                'Резьба' => [self::PE_THREADS, array_values(self::PE_THREADS)],
                'Диаметр' => [fn (string $title) => self::pipeDiameter($title, self::PE_DIAMETERS), array_map(fn (int $mm) => $mm.' мм', self::PE_DIAMETERS)],
            ],
            self::FAUCETS => [
                'Назначение' => [self::FAUCET_USES, self::FAUCET_ORDER],
                'Управление' => [self::FAUCET_CONTROLS, array_values(array_unique(self::FAUCET_CONTROLS))],
                'Цвет' => [self::FAUCET_COLORS, array_values(self::FAUCET_COLORS)],
                'Кран для питьевой воды' => [['/пит\\.? ?вод|питьев/u' => 'С краном для питьевой воды'], ['С краном для питьевой воды']],
            ],
            self::BALL_VALVES => [
                'Присоединение' => [self::BALL_JOINTS, array_values(array_unique(self::BALL_JOINTS))],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Ручка' => [self::BALL_HANDLES, array_values(array_unique(self::BALL_HANDLES))],
                'Для газа' => [self::BALL_GAS, array_values(self::BALL_GAS)],
            ],
            self::PP_FITTINGS => [
                'Тип' => [self::PP_TYPES, array_values(array_unique(self::PP_TYPES))],
                'Диаметр' => [fn (string $title) => self::pipeDiameter($title, self::PP_DIAMETERS), array_map(fn (int $mm) => $mm.' мм', self::PP_DIAMETERS)],
                'Соединение' => [self::PP_JOINTS, array_values(array_unique(self::PP_JOINTS))],
                'Цвет' => [self::PP_COLORS, array_values(self::PP_COLORS)],
            ],
            self::BUFFER_TANKS => [
                'Тип' => [self::BUFFER_TYPES, array_values(self::BUFFER_TYPES)],
            ],
            self::AIR_CONDITIONERS => [
                'Тип' => [self::AC_TYPES, self::AC_ORDER],
                'Площадь помещения' => [fn (string $title) => self::acArea($title), self::AC_AREAS],
                'Компрессор' => [fn (string $title) => self::acCompressor($title), ['Инверторный', 'On/Off']],
            ],
            self::PRESS_FITTINGS => [
                'Тип' => [self::PRESS_TYPES, self::PRESS_ORDER],
                'Материал' => [self::PRESS_MATERIALS, array_values(array_unique(self::PRESS_MATERIALS))],
                'Диаметр' => [fn (string $title) => self::pressDiameter($title), array_map(fn (int $mm) => $mm.' мм', self::PRESS_DIAMETERS)],
            ],
            self::GAS_HOSES => [
                'Тип' => [self::HOSE_TYPES, array_values(self::HOSE_TYPES)],
                'Длина' => [self::HOSE_LENGTHS, array_values(self::HOSE_LENGTHS)],
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

    public const RADIATOR_VALVES = 'radiatornaya-armatura';

    /** «Тип» options of «Радиаторная арматура», in filter order. */
    public const RADIATOR_ORDER = [
        'Термостатические клапаны',
        'Термостатические головки и приводы',
        'Комплекты и наборы',
        'Узлы нижнего подключения',
        'Запорные клапаны',
        'Клапаны ручной регулировки',
        'Воздухоотводчики',
        'Кронштейны и крепления',
        'Заглушки, переходники и аксессуары',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Радиаторная арматура»; the first match wins. */
    public const RADIATOR_TYPES = [
        '/^(комплект|набор) (термостатическ|ручной|кранов|радиаторн)/u' => 'Комплекты и наборы',
        '/головк|термоголовк|термостат радиаторный|сервопривод|электротермическ|varmega vm115/u' => 'Термостатические головки и приводы',
        '/узел нижнего|н-образн|блочн|вентильный блок/u' => 'Узлы нижнего подключения',
        '/^ключ для/u' => 'Заглушки, переходники и аксессуары',
        '/(клапан|вентиль|кран).*(термостат(\.|ическ)|под термоголов|с термоголов)|термостатическ\w* (радиаторный )?клапан|тр[её]хходов\w* термостатическ/u' => 'Термостатические клапаны',
        '/воздухоотводчик|маевского/u' => 'Воздухоотводчики',
        '/кронштейн|крепление|стойка напольн|настенные регулируем/u' => 'Кронштейны и крепления',
        '/заглушк|переходник|удлинител|ниппел|прокладк|фитинг|ключ для|комплект монтажн|набор присоединит|розетка|тнбр|отражател|решетка/u' => 'Заглушки, переходники и аксессуары',
        '/запорн|отсечн|отсечен|отключения радиатора|обратную подводку/u' => 'Запорные клапаны',
        '/вентиль|клапан|кран/u' => 'Клапаны ручной регулировки',
        '/./u' => 'Прочее',
    ];

    /** Title pattern => «Исполнение» (how the valve body goes) of a radiator valve; mounts and plugs have none. */
    public const RADIATOR_SHAPES = [
        '/^узел.*универсальн/u' => 'Универсальный',
        '/^(?!кронштейн|переходник|заглушк|удлинител|крепление).*(?<![а-я])углов/u' => 'Угловой',
        '/^(?!кронштейн|переходник|заглушк|удлинител|крепление).*(?<![а-я])(прям(ой|ые|ая)|проходн)/u' => 'Прямой',
        '/^(?!кронштейн|переходник|заглушк|удлинител|крепление).*осев/u' => 'Осевой',
    ];

    /** Title pattern => «Цвет» of a designer valve, head or bracket. */
    public const COLORS = [
        '/матов\w* никел/u' => 'Матовый никель',
        '/хром/u' => 'Хром',
        '/(?<![а-я])бел(ый|ая|ое|ые)(?![а-я])/u' => 'Белый',
        '/(?<![а-я])(черн|чёрн)(ый|ая|ое|ые)(?![а-я])/u' => 'Чёрный',
        '/(?<![а-я])сер(ый|ая|ое|ые)(?![а-я])/u' => 'Серый',
        '/серебрист|металлик/u' => 'Серебристый',
    ];

    public const MIXING_VALVES = 'smesitelnye-klapany-i-uzly';

    /** «Тип» options of «Смесительные клапаны и узлы», in filter order. */
    public const MIXING_ORDER = [
        'Трёхходовые клапаны',
        'Четырёх- и шестиходовые клапаны',
        'Двухходовые клапаны',
        'Термостатические клапаны для котлов (антиконденсат)',
        'Термостатические смесители (ГВС, тёплый пол)',
        'Насосно-смесительные группы и узлы',
        'Приводы и сервоприводы',
        'Балансировочные клапаны',
        'Запасные термоэлементы',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Смесительные клапаны и узлы»; the first match wins. */
    public const MIXING_TYPES = [
        '/^(сервопривод|привод|электропривод|переходники для приводов)/u' => 'Приводы и сервоприводы',
        '/термостатическ\w* элемент/u' => 'Запасные термоэлементы',
        '/насосн\w* групп|насосно-смесительн|смесительн\w* узел|узел смесительн|mix-unit|laddomat|смесительное устройство/u' => 'Насосно-смесительные группы и узлы',
        '/балансировочн/u' => 'Балансировочные клапаны',
        '/(?<![a-z])vta|atm |атм |am63c|aquamix|r156|tvmix|tsv-tv|regulus tv /u' => 'Термостатические смесители (ГВС, тёплый пол)',
        '/regulus tsv|(?<![a-z])atv |термический для котлов|vtc511/u' => 'Термостатические клапаны для котлов (антиконденсат)',
        '/четыр[её]хходов|шестиходов|vrg 141|arv 484/u' => 'Четырёх- и шестиходовые клапаны',
        '/двухходов|2-х ходов/u' => 'Двухходовые клапаны',
        '/тр[её]хходов|3-х ходов|3-ходов|vrg 131|lk ?840|trv-3|приоритета|cv316/u' => 'Трёхходовые клапаны',
        '/./u' => 'Прочее',
    ];

    /** Sections whose filters also cover the cards of their subsections (the parent page lists them all). */
    public const WITH_SUBSECTIONS = [self::PELLET_BURNERS, self::LEAKS, self::BATH_ACCESSORIES];

    public const PELLET_BURNERS = 'pelletnye-gorelki';

    /** Title pattern => «Тип» inside «Пеллетные горелки» and its parts subsection; the first match wins. */
    public const PELLET_TYPES = [
        '/полный комплект|с подающим устройством/u' => 'Горелки в полном комплекте',
        '/^(пеллетная горелка|kotlov xo)/u' => 'Пеллетные горелки',
        '/шнек|шнеков|пневматической подачи/u' => 'Шнеки и системы подачи',
        '/бункер/u' => 'Бункеры',
        '/./u' => 'Запчасти и датчики',
    ];

    public const PELLET_POWER_FILTER = 'Мощность горелки';

    /** «Мощность горелки» options, smallest first. */
    public const PELLET_POWERS = ['До 25 кВт', '26–50 кВт', '51–100 кВт', '101–300 кВт', 'Свыше 300 кВт'];

    /** The power band of a burner from its title (the top of a «15-70 кВт» range); null for anything but a burner. */
    public static function pelletPower(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! preg_match('/^(пеллетная горелка|kotlov xo)/u', $title)) {
            return null;
        }
        $kw = match (true) {
            (bool) preg_match('/(\d+)\s*[-–]\s*(\d+)\s*квт/u', $title, $m) => (int) $m[2],
            (bool) preg_match('/(\d+)\s*квт/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(?:comfort r?|atmos a|g2 - |uni )(\d+)/u', $title, $m) => (int) $m[1],
            default => null,
        };

        return match (true) {
            $kw === null => null,
            $kw <= 25 => 'До 25 кВт',
            $kw <= 50 => '26–50 кВт',
            $kw <= 100 => '51–100 кВт',
            $kw <= 300 => '101–300 кВт',
            default => 'Свыше 300 кВт',
        };
    }

    public const LEAKS = 'sistemy-zashchity-ot-protechek';

    /** «Тип» options of «Системы защиты от протечек», in filter order. */
    public const LEAK_ORDER = [
        'Готовые комплекты и системы',
        'Краны с электроприводом',
        'Датчики протечки',
        'Модули управления и аксессуары',
    ];

    /** Title pattern => «Тип» inside «Системы защиты от протечек» and its subsections; the first match wins. */
    public const LEAK_TYPES = [
        '/^(модуль|кабель|источник бесперебойного)/u' => 'Модули управления и аксессуары',
        '/^(система|neptun ×|комплект)/u' => 'Готовые комплекты и системы',
        '/кран|манипулятор/u' => 'Краны с электроприводом',
        '/датчик/u' => 'Датчики протечки',
    ];

    /** Title pattern => «Диаметр» (the pipe thread) of a leak protection kit or valve. */
    public const LEAK_SIZES = [
        '/(?<![\d\/])1 1\/4(?!\d)/u' => '1 1/4"',
        '/(?<![\d\/])1 1\/2(?!\d)/u' => '1 1/2"',
        '/(?<![\d\/])1\/2(?!\d)/u' => '1/2"',
        '/(?<![\d\/])3\/4(?!\d)/u' => '3/4"',
        '/\dв 1$/u' => '1"',
        '/\dв 2$/u' => '2"',
    ];

    /** Title pattern => «Напряжение привода» of a motorised valve. */
    public const LEAK_VOLTAGES = [
        '/(?<!\d)12\s*в/u' => '12 В',
        '/(?<!\d)2[23]0\s*[вv]/u' => '220–230 В',
    ];

    public const CONNECTION_KITS = 'komplekty-podklyucheniya';

    /** «Тип» options of «Комплекты подключения», in filter order. */
    public const KIT_ORDER = [
        'Узлы нижнего подключения',
        'Наборы дизайн-вентилей',
        'Комплекты радиаторных клапанов',
        'Монтажные комплекты',
        'Кронштейны и крепления',
        'Комплекты шаровых кранов',
        'Для котлов и бойлеров',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Комплекты подключения»; the first match wins. */
    public const KIT_TYPES = [
        '/дизайн-вентил/u' => 'Наборы дизайн-вентилей',
        '/узел (нижнего )?подключения|набор нижнего подключения/u' => 'Узлы нижнего подключения',
        '/кронштейн|крепление/u' => 'Кронштейны и крепления',
        '/комплект монтажный|монтажный комплект/u' => 'Монтажные комплекты',
        '/шаров\w* кран/u' => 'Комплекты шаровых кранов',
        '/бойлер|котла|адаптер моноблочный|отопительных контуров/u' => 'Для котлов и бойлеров',
        '/радиатор|присоединительный набор|комплект подключения termica|регулировочный короб/u' => 'Комплекты радиаторных клапанов',
        '/./u' => 'Прочее',
    ];

    public const GAS_ALARMS = 'signalizatory-zagazovannosti';

    /** «Какой газ» options of «Сигнализаторы загазованности», in filter order. */
    public const ALARM_ORDER = [
        'Угарный газ (CO)',
        'Природный газ (метан)',
        'Комбинированные (CO + природный газ)',
    ];

    /** Title pattern => the gas an alarm detects (titles mix Latin and Cyrillic «CO»/«СО»). */
    public const ALARM_GASES = [
        '/[cс][oо]\s*\+\s*[cс][hн]|[cс][hн]4\s*,\s*[cс][oо]|комбинирован|два газа|загазованности и угарного/u' => 'Комбинированные (CO + природный газ)',
        '/[cс][hн]4|природн|сгг|сжиженн/u' => 'Природный газ (метан)',
        '/(?<![a-zа-я])[cс][oо](?![a-zа-я])|угарн/u' => 'Угарный газ (CO)',
    ];

    /** «Производительность» options of a blower, smallest first. */
    public const FAN_FLOWS = ['До 250 м³/ч', '251–400 м³/ч', '401–600 м³/ч', 'Свыше 600 м³/ч'];

    /** The airflow band of a blower from «(285 м³/ч)» or «490 м3/час» in its title. */
    public static function fanFlow(string $title): ?string
    {
        if (! preg_match('/(\d+)\s*м(?:³|3)\s*\/\s*ч/u', mb_strtolower($title), $m)) {
            return null;
        }
        $flow = (int) $m[1];

        return match (true) {
            $flow <= 250 => 'До 250 м³/ч',
            $flow <= 400 => '251–400 м³/ч',
            $flow <= 600 => '401–600 м³/ч',
            default => 'Свыше 600 м³/ч',
        };
    }

    /** «Для котла мощностью» options, smallest first. */
    public const FAN_BOILER_POWERS = ['До 25 кВт', '26–75 кВт', '76–150 кВт'];

    /** The boiler size a blower is for, from «(до 70 кВт» or «(к котлам 25-75 кВт)» (the top of the range). */
    public static function fanBoilerPower(string $title): ?string
    {
        if (! preg_match('/(?:до|котлам)\s*(?:\d+\s*[-–]\s*)?(\d+)\s*квт/u', mb_strtolower($title), $m)) {
            return null;
        }
        $kw = (int) $m[1];

        return match (true) {
            $kw <= 25 => 'До 25 кВт',
            $kw <= 75 => '26–75 кВт',
            default => '76–150 кВт',
        };
    }

    /** Pump sections whose cards carry no specs: the motor power comes from the title. */
    public const POWERED_PUMPS = ['mnogostupenchatye-nasosy', 'konsolnye-i-monoblochnye-nasosy', 'in-lain-nasosy'];

    public const PLAIN_VOLTAGE_PUMPS = ['konsolnye-i-monoblochnye-nasosy', 'in-lain-nasosy'];

    /** Title pattern => «Питание» of a pump (the same readings PumpCatalog takes from a title). */
    public const PUMP_VOLTAGES = [
        '/(?:3\s*[xх~]\s*)?(?:380|400)\s*[вv](?![a-zа-я])|[-_](?:380|400)(?:[-_]|$)/u' => '380–415 В',
        '/(?:220|230)\s*[вv](?![a-zа-я])|[-_](?:220|230)(?:[-_]|$)/u' => '220–240 В',
    ];

    /** «Мощность двигателя» options, smallest first. */
    public const PUMP_POWERS = ['До 1,5 кВт', 'Св. 1,5 до 4 кВт', 'Св. 4 до 11 кВт', 'Св. 11 до 30 кВт', 'Свыше 30 кВт'];

    /**
     * The motor power band of a pump from its title: «2.2кВт», «3kW», WELLMIX «-22.0/2-380-», «_15T_380_» and
     * UNIPUMP «FS65-50-125-4.0», PURITY «PSTG 80-160/220» (22 kW). PURITY PVT/PVS and IMP titles name no power.
     */
    public static function pumpPower(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        $kw = null;
        // Pattern => divisor: PURITY «PSTG 80-160/220», «PT 65-400/75» give the power in tenths of a kW.
        foreach ([
            '/(\d+(?:[.,]\d+)?)\s*(?:квт|kw)(?![a-zа-я])/u' => 1,
            '/-\s*(\d+(?:[.,]\d+)?)\/[24]-(?:220|380)-/u' => 1,
            '/_(\d+(?:[.,]\d+)?)t?_(?:220|380)_/u' => 1,
            '/\bf[sc]\s*\d+-\d+(?:-\d+)?-(\d+(?:[.,]\d+)?)$/u' => 1,
            '/^(?=.*purity).*(?<![a-z])(?:pstg|pstc|pz|pt)\s*\d+-\d+\/(\d+)/u' => 10,
        ] as $pattern => $divisor) {
            if (preg_match($pattern, $title, $m)) {
                $kw = (float) str_replace(',', '.', $m[1]) / $divisor;
                break;
            }
        }

        return match (true) {
            $kw === null || $kw <= 0 => null,
            $kw <= 1.5 => 'До 1,5 кВт',
            $kw <= 4 => 'Св. 1,5 до 4 кВт',
            $kw <= 11 => 'Св. 4 до 11 кВт',
            $kw <= 30 => 'Св. 11 до 30 кВт',
            default => 'Свыше 30 кВт',
        };
    }

    public const PRESS_FITTINGS = 'press-fitingi';

    /** «Тип» options of «Пресс-фитинги», in filter order. */
    public const PRESS_ORDER = [
        'Муфты, соединители и переходники',
        'Угольники и отводы',
        'Тройники',
        'Крестовины',
        'Заглушки',
        'Обводы',
        'Распределительные узлы',
        'Трубки для подключения радиатора',
        'Трубы',
        'Инструмент, насадки и кольца',
    ];

    /**
     * Title pattern => «Тип» inside «Пресс-фитинги»; the first match wins. Varmega Inox Press cards named only by
     * article («Varmega VM719181518 18x15x18») get a type where the series is known; the rest stay without one.
     */
    public const PRESS_TYPES = [
        '/^(комплектующие и аксессуары|уплотнительное кольцо|кольцо)|насадк|инструмент|гильз|пресс-кольц|^varmega (vm59[89]|vm79\d)/u' => 'Инструмент, насадки и кольца',
        '/^трубка|^varmega vm52[56]/u' => 'Трубки для подключения радиатора',
        '/^varmega vm(700|501)|^(универсальная )?труба/u' => 'Трубы',
        '/крестовин|^varmega vm72[45]/u' => 'Крестовины',
        '/заглушк|^varmega vm722/u' => 'Заглушки',
        '/тройник|^varmega vm7(18|19|20|21)/u' => 'Тройники',
        '/обвод|^varmega vm733/u' => 'Обводы',
        '/узел распределительн/u' => 'Распределительные узлы',
        '/угольник|отвод|угол|колено|водорозетк/u' => 'Угольники и отводы',
        '/муфт|соединител|соединение|переход|ниппел|^varmega (vm70[15]|217)/u' => 'Муфты, соединители и переходники',
    ];

    /** Title pattern => «Материал» of a press fitting; Varmega VM700–739 is the Inox Press (stainless) series. */
    public const PRESS_MATERIALS = [
        '/нерж|inox|^varmega vm7[0-3]\d/u' => 'Нержавеющая сталь',
        '/медн|медь/u' => 'Медь',
        '/бронз/u' => 'Бронза',
        '/латун|slide-fit/u' => 'Латунь',
        '/оцинк|стальн|(?<![a-z])steel(?![a-z])/u' => 'Оцинкованная сталь',
    ];

    /** The pipe sizes a press fitting is listed by, mm. */
    public const PRESS_DIAMETERS = [12, 14, 15, 16, 18, 20, 22, 25, 26, 28, 32, 35, 40, 42, 50, 54, 63, 64, 66, 76, 88, 108];

    /** The first pipe size of a press fitting title («22х3/4"», «15ax1/2"», «28 под пайку», «16 мм»). */
    public static function pressDiameter(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (preg_match_all('/(?<![\d\w.,\/])(\d{2,3})a?(?=\s*(?:[xх×*\/,]|мм|-\d|\s(?:под|пресс|с\s)|\s[a-z]|$|\s*\())/u', $title, $m)) {
            foreach ($m[1] as $mm) {
                if (in_array((int) $mm, self::PRESS_DIAMETERS, true)) {
                    return (int) $mm.' мм';
                }
            }
        }

        return null;
    }

    public const AIR_CONDITIONERS = 'kondicionery';

    /** «Тип» options of «Кондиционеры», in filter order. */
    public const AC_ORDER = [
        'Настенные сплит-системы',
        'Кассетные',
        'Канальные',
        'Напольно-потолочные',
        'Внутренние блоки мульти-сплит',
        'Наружные блоки мульти-сплит',
    ];

    /** Title pattern => «Тип» of an air conditioner; SHUFT SFLC (commercial, form unknown) gets none. */
    public const AC_TYPES = [
        '/^внешний блок|\/out$|(?<![a-z])eaco\/i|(?<![a-z])sfmo/u' => 'Наружные блоки мульти-сплит',
        '/^внутренний блок/u' => 'Внутренние блоки мульти-сплит',
        '/кассетн|(?<![a-z])eacc-/u' => 'Кассетные',
        '/канальн|(?<![a-z])eacd-/u' => 'Канальные',
        '/напольно-потолочн|(?<![a-z])eacu-/u' => 'Напольно-потолочные',
        '/^(?!shuft sflc)./u' => 'Настенные сплит-системы',
    ];

    /** «Площадь помещения» options, smallest first (the usual 07/09/12/18/24 classes). */
    public const AC_AREAS = ['До 20 м²', 'До 25 м²', 'До 35 м²', 'До 50 м²', 'До 70 м²', 'Свыше 70 м²'];

    /**
     * The room size an indoor unit suits, from its class in the title: BTU thousands (Electrolux «EACS/I-09», SHUFT,
     * Ballu, Royal Thermo, Toshiba «RAS-B13», Haier «HSU-12», «AS12…») or Haier kW tenths («AS25…» = 2.5 kW).
     * Outdoor multi-split units serve several rooms and get none.
     */
    public static function acArea(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (self::match($title, self::AC_TYPES) === 'Наружные блоки мульти-сплит') {
            return null;
        }
        $btu = null;
        if (preg_match('/(?<![a-z])(?:eacs\/i|eacs|eacc|eacd|eacu|bsgri|rtb|rtsi|sflc_cf?|sftoi|ace|hsu|ras-b?)-?\s?(\d{2})(?!\d)/u', $title, $m)) {
            $btu = (int) $m[1];
        } elseif (preg_match('/(?<![a-z])as(\d{2})(?!\d)/u', $title, $m)) {
            $n = (int) $m[1];
            $btu = match (true) {
                in_array($n, [7, 9, 12, 18, 24], true) => $n,
                $n <= 22 => 7,
                $n <= 27 => 9,
                $n <= 36 => 12,
                $n <= 53 => 18,
                $n <= 73 => 24,
                default => 30,
            };
        }

        return match (true) {
            $btu === null || $btu < 5 => null,
            $btu <= 7 => 'До 20 м²',
            $btu <= 9 => 'До 25 м²',
            $btu <= 13 => 'До 35 м²',
            $btu <= 18 => 'До 50 м²',
            $btu <= 24 => 'До 70 м²',
            default => 'Свыше 70 м²',
        };
    }

    /**
     * Inverter or on/off from the title: «on-off», Electrolux without «/I» or «DC» and Haier HSU are on/off; «/I»,
     * «DC», «Inverter», Haier AS, Ballu BSGRI, Royal Thermo RTSI, SHUFT SFTOI/SFMO and Toshiba KV are inverters.
     */
    public static function acCompressor(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));

        return match (true) {
            (bool) preg_match('/on-off|on\/off|(?<![a-z])hsu-/u', $title) => 'On/Off',
            (bool) preg_match('/inverter|инвертор|(?<![a-z])dc(?![a-z])|eacs\/i|eaco\/i|(?<![a-z])as\d{2}|bsgri|rtsi|sftoi|sfmo\/i|ras-b\w*kv/u', $title) => 'Инверторный',
            (bool) preg_match('/(?<![a-z])eac[scdu]-/u', $title) => 'On/Off',
            default => null,
        };
    }

    public const BUFFER_TANKS = 'bufernye-emkosti';

    /** Title pattern => «Тип» inside «Буферные ёмкости»: Drazice NADO holds a DHW tank inside, S-TANK CT stores cold. */
    public const BUFFER_TYPES = [
        '/холодоаккумулятор/u' => 'Холодоаккумуляторы',
        '/(?<![a-z])nado(?![a-z])/u' => 'Со встроенным баком ГВС',
        '/./u' => 'Буферные ёмкости и теплоаккумуляторы',
    ];

    public const PP_FITTINGS = 'fitingi-dlya-polipropilenovykh-trub';

    /** Title pattern => «Тип» inside «Фитинги для полипропиленовых труб», in filter order; the first match wins. */
    public const PP_TYPES = [
        '/кран|вентиль|клапан/u' => 'Краны и вентили',
        '/^(?:п\/п )?тройник|тройной/u' => 'Тройники',
        '/крестовин/u' => 'Крестовины',
        '/отвод|колено|угол|уголок/u' => 'Отводы и колена',
        '/обвод/u' => 'Обводы',
        '/муфт|американк|ниппел|соединение|евроконус|переход/u' => 'Муфты, американки и переходники',
        '/заглушк/u' => 'Заглушки',
        '/компенсатор/u' => 'Компенсаторы',
        '/фильтр/u' => 'Фильтры',
        '/фланец|бурт/u' => 'Фланцы и бурты',
        '/коллектор|узел/u' => 'Коллекторы и узлы',
        '/седло/u' => 'Сёдла',
        '/опора|планка|креплен/u' => 'Опоры и крепления',
        '/./u' => 'Инструмент и прочее',
    ];

    /** Title pattern => «Соединение» of a PP fitting: union, thread or plain socket weld. */
    public const PP_JOINTS = [
        '/американк|разборн|накидн|евроконус/u' => 'Разборное (американка)',
        '/внутренн|(?<![а-я])вн[.\/-]|(?<![а-я])вр(?![а-я])/u' => 'С внутренней резьбой',
        '/наружн|(?<![а-я])нар[.\/-]|(?<![а-я])нр(?![а-я])/u' => 'С наружной резьбой',
        '/^(?!.*(опора|планка|зачистк|кольцо|коллектор|узел|комплект|фланец|бурт))./u' => 'Под сварку',
    ];

    /** Title pattern => «Цвет» of a PP fitting. */
    public const PP_COLORS = [
        '/(?<![а-я])бел(ый|ая|ое|ые|\.|,|\s|$)/u' => 'Белый',
        '/(?<![а-я])сер(ый|ая|ое|ые|\.|,|\s|$)/u' => 'Серый',
    ];

    /** The PP pipe sizes, mm. */
    public const PP_DIAMETERS = [16, 20, 25, 32, 40, 50, 63, 75, 90, 110, 125, 160];

    /** The first pipe size of a fitting title out of $sizes: «25х3/4"», «d25», «Ду 20», «Заглушка 40», «32х20х32». */
    public static function pipeDiameter(string $title, array $sizes): ?string
    {
        $title = preg_replace('/(?<![\w])(?:d|ду\s?)(?=\d)/u', ' ', mb_strtolower(trim(html_entity_decode($title))));
        if (preg_match_all('/(?<![\w.,\/])(\d{2,3})(?=\s*(?:[xх×*\/,]|-[a-zа-я]|мм|$|\s|\())/u', $title, $m)) {
            foreach ($m[1] as $mm) {
                if (in_array((int) $mm, $sizes, true)) {
                    return (int) $mm.' мм';
                }
            }
        }

        return null;
    }

    public const BALL_VALVES = 'sharovye-krany';

    /** Title pattern => «Присоединение» of a ball valve, in filter order; the first match wins. */
    public const BALL_JOINTS = [
        '/фланц|ф\/ф|кшцф|кшшс/u' => 'Фланцевое',
        '/приварк|кшцп|с\/с/u' => 'Под приварку',
        '/(?<![а-я])пп(?![а-я])|ppr|для пэ|пресс/u' => 'Под пластиковую или пресс-трубу',
        '/американк|разборн|накидн|гайк|сгон|giacoqest/u' => 'С американкой, гайкой или сгоном',
        '/нар\.?-нар|нар\.? х [^ ]+ нар|(?<!\/)наружной резьбой(?!.*внутр)|(?<![а-яa-z])(?:нн|рр)(?![а-яa-z])/u' => 'Наружная — наружная резьба',
        '/нар\.?-вн|вн\.?-нар|внутренней\/наружной|наружной\/внутренней|(?<![а-яa-z])(?:нв|мр)(?![а-яa-z])|муфта-цапка/u' => 'Внутренняя — наружная резьба',
        '/вн\.?-вн|внутренней резьбой|(?<![а-яa-z])(?:вв|мм)(?![а-яa-z])|муфтов/u' => 'Внутренняя — внутренняя резьба',
    ];

    /** Title pattern => «Ручка» of a ball valve. */
    public const BALL_HANDLES = [
        '/бабочк|(?<![а-я])баб\./u' => 'Бабочка',
        '/флажок/u' => 'Флажок',
        '/рычаг|(?<![а-я])рыч\.|ручка(?!-)/u' => 'Рычаг',
    ];

    /** Title pattern => «Для газа». */
    public const BALL_GAS = ['/(?<![а-я])газ(?![а-я])/u' => 'Для газа'];

    /** The nominal sizes a ball valve is listed by. */
    public const BALL_DNS = [10, 15, 20, 25, 32, 40, 50, 65, 80, 100, 125, 150, 200];

    public static function dnLabel(int $dn): string
    {
        $inch = [10 => '3/8"', 15 => '1/2"', 20 => '3/4"', 25 => '1"', 32 => '1 1/4"', 40 => '1 1/2"', 50 => '2"'][$dn] ?? null;

        return 'Ду '.$dn.($inch ? ' ('.$inch.')' : '');
    }

    /**
     * The nominal size of a ball valve: «Ду 25» (the first of «Ду 25/20»), an inch thread («1 1/4"») or the outside
     * diameter of a PP/PE pipe («ПП 32» = Ду 25).
     */
    public static function ballValveDn(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        $dn = null;
        $pipe = [16 => 10, 18 => 15, 20 => 15, 25 => 20, 32 => 25, 40 => 32, 50 => 40, 63 => 50];
        if (preg_match('/пресс-соединения ду\s*(\d{2})/u', $title, $m) && isset($pipe[(int) $m[1]])) {
            $dn = $pipe[(int) $m[1]];
        } elseif (preg_match('/(?:ду|dn)\s*(\d{2,3})/u', $title, $m)) {
            $dn = (int) $m[1];
        } elseif (preg_match('/(?:(?<![а-я])пп(?![а-я])|ppr)\D{0,25}?(?<![\d\/])(16|20|25|32|40|50|63)(?!\d)|(?<![\d\/.,])(16|20|25|32|40|50|63)(?:\s*х[^()]*)?\s*\(для пэ/u', $title, $m)) {
            $dn = $pipe[(int) ($m[1] !== '' ? $m[1] : $m[2])];
        } elseif (preg_match('/(?<![\d\/.,])(1 1\/4|1 1\/2|1\/2|3\/4|3\/8|1|2)(?=["″”]|\s*(?:нар|вн|х|x)|\s*$)/u', $title, $m)) {
            $dn = ['3/8' => 10, '1/2' => 15, '3/4' => 20, '1' => 25, '1 1/4' => 32, '1 1/2' => 40, '2' => 50][$m[1]];
        } elseif (preg_match('/(?<![\d\/.,])(1\/2|3\/4|3\/8)(?=\s)/u', $title, $m)) {
            $dn = ['3/8' => 10, '1/2' => 15, '3/4' => 20][$m[1]];
        }

        return $dn !== null && in_array($dn, self::BALL_DNS, true) ? self::dnLabel($dn) : null;
    }

    public const FAUCETS = 'smesiteli';

    /** «Назначение» options of «Смесители», in filter order. */
    public const FAUCET_ORDER = [
        'Для кухни',
        'Для умывальника',
        'Для ванны',
        'Для душа',
        'Для биде',
        'Универсальные (мойка и умывальник)',
        'Душевые системы и стойки',
        'Комплектующие (аэраторы, картриджи, буксы)',
    ];

    /** Title pattern => «Назначение» of a faucet; the first match wins. */
    public const FAUCET_USES = [
        '/^(аэратор|картридж|головка|кран-букса|излив|сифон)/u' => 'Комплектующие (аэраторы, картриджи, буксы)',
        '/душев\w* (систем|стойк|набор)|^(система|стойка|набор) душев|^душевой набор/u' => 'Душевые системы и стойки',
        '/(мойки|кухни) и умывальник|умывальника и мойки|ванны и умывальник|универсальн/u' => 'Универсальные (мойка и умывальник)',
        '/для кухни|для мойки/u' => 'Для кухни',
        '/для умывальник|для раковин/u' => 'Для умывальника',
        '/для ванн/u' => 'Для ванны',
        '/для душа/u' => 'Для душа',
        '/для биде/u' => 'Для биде',
    ];

    /** Title pattern => «Управление» of a faucet: a lever cartridge (D25/D35/D40) or two valves. */
    public const FAUCET_CONTROLS = [
        '/термостат/u' => 'Термостатический',
        '/сенсорн/u' => 'Сенсорный',
        '/вентильн|двухвентил|двуручн|2-руч|тип i(?![a-z])/u' => 'Двухвентильный',
        '/одноручн|однорычаж|1-руч|(?<![a-z])d\s?(25|26|35|40)(?!\d)|моно-смеситель|хирургическ|локтев|uno-/u' => 'Однорычажный',
    ];

    /** Title pattern => «Цвет» of a faucet; two-tone ones get their first colour. */
    public const FAUCET_COLORS = [
        '/хром/u' => 'Хром',
        '/(?<![а-я])(черн|чёрн)\w*|black/u' => 'Чёрный',
        '/(?<![а-я])бел(ый|ая|ое)(?![а-я])|white/u' => 'Белый',
        '/золот|gold/u' => 'Золото',
        '/бронз/u' => 'Бронза',
        '/сатин|матов\w* никел|нержав|sus 304/u' => 'Сатин и нержавеющая сталь',
        '/гранит|графит|сер(ый|ая)(?![а-я])/u' => 'Серый и графит',
    ];

    public const PE_FITTINGS = 'fitingi-dlya-pe-trub';

    /** «Тип» options of «Фитинги для ПЭ труб», in filter order. */
    public const PE_ORDER = [
        'Муфты и переходники',
        'Отводы и колена',
        'Тройники',
        'Заглушки',
        'Сёдла (врезки)',
        'Краны',
        'Фланцы и втулки под фланец',
        'Опоры',
        'Резьбовые пластиковые фитинги',
    ];

    /** Title pattern => «Тип» inside «Фитинги для ПЭ труб»; the first match wins. */
    public const PE_TYPES = [
        '/^кран/u' => 'Краны',
        '/(?<![а-я])пп(?![а-я]).*кор\.|^врезка пп|^футорка/u' => 'Резьбовые пластиковые фитинги',
        '/седло/u' => 'Сёдла (врезки)',
        '/опор/u' => 'Опоры',
        '/фланец|втулка под фланец/u' => 'Фланцы и втулки под фланец',
        '/тройник/u' => 'Тройники',
        '/отвод|колено|угольник/u' => 'Отводы и колена',
        '/заглушк/u' => 'Заглушки',
        '/муфт|ниппел|адаптер|соединение|переход/u' => 'Муфты и переходники',
    ];

    /** Title pattern => «Способ соединения» of a PE fitting: compression is the default for the push-fit brands. */
    public const PE_JOINTS = [
        '/электросвар|electrofusion/u' => 'Электросварные',
        '/литой|сварной|стыков|патрубок|втулка под фланец/u' => 'Под стыковую сварку',
        '/фланцев|фланец/u' => 'Фланцевые',
        '/(?<![а-я])пп(?![а-я]).*кор\.|^врезка пп|^футорка/u' => 'Резьбовые',
        '/пэ \d|для пэ|для п\/э|компрессион|обжимн|unidelta|lammin|valfex|седло/u' => 'Компрессионные (обжимные)',
    ];

    /** Title pattern => «Резьба» of a PE fitting with a threaded end. */
    public const PE_THREADS = [
        '/внутренн|(?<![а-я])вн\.|(?<![а-я])вр(?![а-я])|обойма-внутренн/u' => 'С внутренней резьбой',
        '/наружн|(?<![а-я])нар\.|(?<![а-я])нр(?![а-я])/u' => 'С наружной резьбой',
    ];

    /** The PE pipe sizes, mm. */
    public const PE_DIAMETERS = [16, 20, 25, 32, 40, 50, 63, 75, 90, 110, 125, 160, 200, 225, 250, 315, 400];

    public const PEX_FITTINGS = 'fitingi-dlya-trub-pex';

    /** «Тип» options of «Фитинги для труб PEX и PERT», in filter order. */
    public const PEX_ORDER = [
        'Муфты, соединители и переходники',
        'Тройники',
        'Угольники и отводы',
        'Евроконусы и адаптеры',
        'Заглушки',
        'Гильзы, кольца и монтаж',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Фитинги для труб PEX и PERT»; the first match wins. */
    public const PEX_TYPES = [
        '/тройник/u' => 'Тройники',
        '/гильз|втулк|кольцо|фиксатор|шина|плитка|профиль|кронштейн|гайка для|крюк|лента|планка|пластин|скоба|хомут|дуга|прокладк|розетка cu|зонд/u' => 'Гильзы, кольца и монтаж',
        '/евроконус|конус/u' => 'Евроконусы и адаптеры',
        '/заглушк/u' => 'Заглушки',
        '/угольник|отвод|колено|90°|углов|соединение радиатора|водорозетк/u' => 'Угольники и отводы',
        '/муфт|соединител|соединение|переход|ниппел|адаптер|фитинг прямой/u' => 'Муфты, соединители и переходники',
        '/./u' => 'Прочее',
    ];

    /** Title pattern => «Материал» of a PEX fitting. */
    public const PEX_MATERIALS = [
        '/ppsu|пластик/u' => 'Пластик (PPSU)',
        '/латун|giacoqest|goldfix|(?<![a-z])gx(?![a-z])|рехау|rautitan|rautherm|для труб pex|евроконус|ниппел|водорозетк|переходник прямой|(?<![a-z])far(?![a-z])/u' => 'Латунь',
    ];

    /** Title pattern => «Резьба» of a PEX fitting; the first match wins. */
    public const PEX_THREADS = [
        '/евроконус|конус/u' => 'Евроконус',
        '/накидн/u' => 'Накидная гайка',
        '/внутренн|(?<![а-я])вн\.|(?<![а-я])вр(?![а-я])|(?<![a-z])f(?![a-z])/u' => 'Внутренняя резьба',
        '/наружн|(?<![а-я])нар\.|(?<![а-я])нр(?![а-я])|(?<![a-z])m(?![a-z])/u' => 'Наружная резьба',
    ];

    /** The PEX/PERT pipe sizes, mm. */
    public const PEX_DIAMETERS = [14, 16, 17, 18, 20, 25, 32, 40, 50, 63];

    public const MANIFOLDS = 'grebenki';

    /** «Тип» options of «Гребенки», in filter order. */
    public const MANIFOLD_ORDER = [
        'Коллекторные группы с расходомерами',
        'Коллекторы и гребёнки',
        'Насосно-смесительные узлы',
        'Коллекторные шкафы',
        'Комплектующие для коллекторов',
    ];

    /** Title pattern => «Тип» inside «Гребенки»; the first match wins. */
    public const MANIFOLD_TYPES = [
        '/^шкаф/u' => 'Коллекторные шкафы',
        '/насосн|смесительн\w* узел|узел насосно/u' => 'Насосно-смесительные узлы',
        '/^(кронштейн|комплект кронштейн|заглушк|концевая|расходомер|термовставк|комплект|электротермическ|сервопривод|модульный блок|узел для|элемент|расширительный контур|кран|удлинитель|настенные|т-соединительн|varmega vm1)/u' => 'Комплектующие для коллекторов',
        '/расходомер|теплого пола|от пола|fbh 63/u' => 'Коллекторные группы с расходомерами',
        '/гребенк|коллектор/u' => 'Коллекторы и гребёнки',
    ];

    public static function outletsLabel(int $n): string
    {
        return $n.' '.match (true) {
            $n % 10 === 1 && $n % 100 !== 11 => 'выход',
            in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) => 'выхода',
            default => 'выходов',
        };
    }

    /**
     * The number of outlets (circuits) of a manifold from its title: «на 5 контуров», «7 выходов», «(11 конт.)»,
     * «- 3» (Varmega), TERMICA «MEX05», Afriso «EF-3», PROFLine «XF 20102-12», Bosch «HKV 3/25/32». Parts get none.
     */
    public static function manifoldOutlets(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (self::match($title, self::MANIFOLD_TYPES) === 'Комплектующие для коллекторов' || str_starts_with($title, 'шкаф')) {
            return null;
        }
        $n = null;
        foreach ([
            '/(\d{1,2})\s*(?:выход|вых\.|контур|конт\.|зон)/u',
            '/\s-\s?(\d{1,2})$/u',
            '/mex(\d{2})/u',
            '/ef-(\d{1,2})/u',
            '/xf\s?\d{5}-(\d{1,2})/u',
            '/hkv\s?(\d)\/\d/u',
        ] as $pattern) {
            if (preg_match($pattern, $title, $m)) {
                $n = (int) $m[1];
                break;
            }
        }

        return $n !== null && $n >= 2 && $n <= 16 ? self::outletsLabel($n) : null;
    }

    public const SEWER = 'vnutrennyaya-kanalizatsiya';

    /** «Тип» options of «Внутренняя канализация», in filter order. */
    public const SEWER_ORDER = [
        'Трубы',
        'Отводы и колена',
        'Тройники',
        'Крестовины',
        'Переходы и редукции',
        'Муфты',
        'Заглушки',
        'Ревизии',
        'Трапы и воронки',
        'Манжеты и уплотнения',
        'Вентиляция (клапаны, зонты)',
        'Хомуты и крепёж',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Внутренняя канализация»; the first match wins. */
    public const SEWER_TYPES = [
        '/^(труба|патрубок)/u' => 'Трубы',
        '/крестовин/u' => 'Крестовины',
        '/тройник/u' => 'Тройники',
        '/отвод|колено/u' => 'Отводы и колена',
        '/переход|редукц/u' => 'Переходы и редукции',
        '/муфт/u' => 'Муфты',
        '/заглушк/u' => 'Заглушки',
        '/ревизи/u' => 'Ревизии',
        '/трап|воронк/u' => 'Трапы и воронки',
        '/манжет|кольцо|уплотн/u' => 'Манжеты и уплотнения',
        '/клапан|зонт|грибок|аэратор/u' => 'Вентиляция (клапаны, зонты)',
        '/хомут|крепл|опора/u' => 'Хомуты и крепёж',
        '/./u' => 'Прочее',
    ];

    /** The sewer pipe sizes, mm. */
    public const SEWER_DIAMETERS = [32, 40, 50, 75, 90, 100, 110, 125, 160, 200];

    /** The pipe lengths sewer pipes come in, mm. */
    public const SEWER_LENGTHS = [150, 250, 315, 500, 750, 1000, 1500, 2000, 3000];

    /** The first sewer size of a title («Ду 110х50», «Дн 110», «d32», «110/50х87»); Stilte «58» is the 50 line. */
    public static function sewerDiameter(string $title): ?string
    {
        $title = preg_replace('/(?<![\w])(?:дн|ду|d)\s?(?=\d)/u', ' ', mb_strtolower(trim(html_entity_decode($title))));
        if (preg_match_all('/(?<![\w.,\/])(\d{2,3})(?=\s*(?:[xх×*\/,]|мм|$|\s|\())/u', $title, $m)) {
            foreach ($m[1] as $mm) {
                $mm = ['58' => 50, '78' => 75][$mm] ?? (int) $mm;
                if (in_array($mm, self::SEWER_DIAMETERS, true)) {
                    return $mm.' мм';
                }
            }
        }

        return null;
    }

    /** The length of a sewer pipe from «110х2,2х1500» / «32х1,8х 250мм»; fittings get none. */
    public static function sewerPipeLength(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! str_starts_with($title, 'труба') || ! preg_match('/\d+\s*[xх]\s*\d+(?:[.,]\d+)?\s*[xх]\s*(\d{3,4})/u', $title, $m)) {
            return null;
        }

        return in_array((int) $m[1], self::SEWER_LENGTHS, true) ? (int) $m[1].' мм' : null;
    }

    public const PLUMBING_PARTS = 'komplektuyushchie-dlya-santekhniki';

    /** Title pattern => «Тип» inside «Комплектующие для сантехники», in filter order; the first match wins. */
    public const PLUMBING_TYPES = [
        '/^шланг душев/u' => 'Душевые шланги',
        '/лейк|верхний душ/u' => 'Душевые лейки и верхний душ',
        '/^душевая стойка|штанга/u' => 'Душевые стойки и штанги',
        '/^излив/u' => 'Изливы',
        '/картридж|вентильн\w* головк|маховик|кран-букс|ручк/u' => 'Картриджи, головки и ручки',
        '/аэратор|сетка-фильтр|^фильтр/u' => 'Аэраторы и фильтры',
        '/впускн|выпускн\w* механизм|спускн|сливной механизм|бачк|смывн|кнопк|кнопочн|инсталляц|писсуар|поплавочн|клапан плоский|скорости смыва/u' => 'Арматура бачков, писсуаров и инсталляции',
        '/сиденье/u' => 'Сиденья для унитаза',
        '/^экран|панель фронтальная/u' => 'Экраны под ванну',
        '/полотенцесуш|для п\/с/u' => 'Подключение полотенцесушителей',
        '/выпуск|гофр|гибкая труба|трап|водослив|перелив|пробк|^колено|манжет|сифон|соединение гибкое|адаптеров канализац|заглушка для отверстия/u' => 'Сифоны, выпуски, гофры и трапы',
        '/американк|эксцентрик|узел присоединит|адаптер|чашка декоратив|гайка/u' => 'Подключение смесителей',
        '/креплен|держател|крепеж|подвес|комплект монтажн|набор монтажн|комплект для акриловой/u' => 'Крепёж и держатели',
        '/проклад|уплотнит|кольцо|набор ремонтн/u' => 'Прокладки и ремкомплекты',
        '/./u' => 'Прочее',
    ];

    public const PUMP_PARTS = 'komplektuyushchie-dlya-nasosov';

    /** «Тип» options of «Комплектующие для насосов», in filter order. */
    public const PUMP_PART_ORDER = [
        'Автоматика, реле и частотники',
        'Мембраны для гидроаккумуляторов',
        'Кабели',
        'Шланги и рукава',
        'Обратные клапаны',
        'Фитинги и соединения',
        'Монтаж насоса и скважины',
        'Запчасти для насосов',
        'Манометры и приборы',
        'Фильтры',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Комплектующие для насосов»; the first match wins. */
    public const PUMP_PART_TYPES = [
        '/кабел/u' => 'Кабели',
        '/мембран|баллон/u' => 'Мембраны для гидроаккумуляторов',
        '/фильтр|аквабрайт|картридж/u' => 'Фильтры',
        '/винтовое соединение|гаек/u' => 'Фитинги и соединения',
        '/трубная муфта|оголовок|трос|кронштейн|хомут|сливной клапан|комплект монтажн|стенка|емкость/u' => 'Монтаж насоса и скважины',
        '/реле|блок управлени|блок автоматики|автоматик|частотн|сухого хода|поплавков|выключател|контроллер|прессконтрол|турби|тискотрон|e\.sytwin/u' => 'Автоматика, реле и частотники',
        '/шланг|рукав/u' => 'Шланги и рукава',
        '/обратн/u' => 'Обратные клапаны',
        '/манометр|тест-насос/u' => 'Манометры и приборы',
        '/уплотнени|рабочий винт|колесо рабочее|рабочее колесо|в обойме|в сборе|запчаст|запасн|кольцо|консоль|подшипник/u' => 'Запчасти для насосов',
        '/тройник|соедин|колено|адаптер|коллектор|гайк|заглушк|штуцер|муфт|uniot/u' => 'Фитинги и соединения',
        '/./u' => 'Прочее',
    ];

    public const BATH_ACCESSORIES = 'aksessuary-dlya-bani';

    /** Title pattern => «Тип» inside «Комплектующие и аксессуары для бани» and its subsections, in filter order. */
    public const BATH_TYPES = [
        '/^(камни|камень|кирпич)|керамические камни/u' => 'Камни для бани',
        '/сетка для камней|сетка-каменка|сэндвич-сетка|сетка на трубу|^каменка (натрубная|навесная|к печи)|царь-печи каменка|короб для камней|отопитель натрубн/u' => 'Сетки и каменки для камней',
        '/^шайк|ведро|ковш|черпак|обливн|запарник|ушат/u' => 'Шайки, ковши и обливные устройства',
        '/абажур|светильник|лампа/u' => 'Абажуры и светильники',
        '/пульт|блок управлени|^пу для/u' => 'Пульты и блоки управления',
        '/вентиляц|клапан/u' => 'Вентиляция',
        '/бак |^бак|регистр|теплообменник|экономайзер|теплосъемник|съемник тепла|теплоаккумулятор/u' => 'Баки, регистры и теплообменники',
        '/парогенератор|турбопар/u' => 'Парогенераторы',
        '/портал|облицовк|плитк|кожух|короб для облицовки|экран|комплект защитный|декор|рамка|адаптер №/u' => 'Порталы, облицовка и экраны',
        '/огнеупорн|вермикулит|базальт|фольг|skamol|картон|термозащит|герметик|уплотнитель|огнезащит|izosil|кальций-силикат|стекломагниев|изоляц|лента/u' => 'Огнезащита и теплоизоляция',
        '/тэн|электрокаменк/u' => 'Комплектующие для электрокаменок',
        '/термометр|гигрометр|часы/u' => 'Приборы: термометры и часы',
        '/шибер|задвижк|^труба|модуль трубы|нижняя труба|удлинитель тоннеля|приставка/u' => 'Шиберы, задвижки и трубы',
        '/казан|сковород|коптильн|мангал|костров|grill|стейк|тренога|зухра|gurman/u' => 'Казаны, мангалы и коптильни',
        '/дровниц|набор каминный|кочерг|совок|щетка|сумка для дров/u' => 'Каминные принадлежности и дровницы',
        '/^(печь|закрытая каменка|чугунная печь|камин )/u' => 'Печи и каменки',
        '/двер|конфорк|кружок|крышка чугунная|колосник|плита усиленная|стекло/u' => 'Чугунное литьё: дверки, плиты, конфорки',
        '/./u' => 'Коврики, отделка и прочее',
    ];

    public const GAS_HOSES = 'gazovye-shlangi';

    /** Title pattern => «Тип» inside «Газовые шланги». */
    public const HOSE_TYPES = [
        '/^вставка диэлектрическ/u' => 'Диэлектрические вставки',
        '/cemflex|zurflex|нерж|сильфон|металл/u' => 'Металлические (нержавеющая сталь)',
        '/шланг/u' => 'Резиновые и полимерные',
    ];

    /** Title pattern => «Длина» of a gas hose («1,5 м» or «150см»). */
    public const HOSE_LENGTHS = [
        '/(?<![\d,.])(?:0[,.]\d+|1(?:[,.]0)?)\s*м(?![а-я])|(?<![\d,.])\d{2}\s*см/u' => 'До 1 м',
        '/(?<![\d,.])1[,.][1-9]\s*м(?![а-я])|(?<![\d,.])1[1-9]\d\s*см/u' => '1,2–1,5 м',
        '/(?<![\d,.])[23](?:[,.]\d)?\s*м(?![а-я])|(?<![\d,.])[23]\d\d\s*см/u' => '2–3 м',
    ];

    public static function move(string $title): ?string
    {
        return self::first(self::MOVES, $title);
    }

    public static function group(string $title, array $rules = self::GROUPS): string
    {
        return self::first($rules, $title) ?? 'Прочее';
    }

    /** @param  array<string, string>|\Closure(string): ?string  $rules */
    public static function match(string $title, array|\Closure $rules): ?string
    {
        return $rules instanceof \Closure ? $rules($title) : self::first($rules, $title);
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

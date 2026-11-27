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
        '/^фильтр для очистки воды electrolux/u' => 'filtry',
        '/хоммет/u' => 'installyatsii-i-armatura-bachkov',
        '/^(blist|ермак) стекло термостойк/u' => 'stekla-dlya-pechey-i-kaminov',
        '/^мангал вертикальный большой/u' => 'mangalyi',
        '/^ермак (бак навесной|катализатор|конвектор дымохода|модуль закрытой каменки|парообразователь|сетка-каменка|тэн|теплообменник|удлинитель тоннеля)/u' => 'aksessuary-dlya-bani',
        '/вентилятор наддува|центробежный вентилятор наддува/u' => self::FANS,
        '/^регулятор тяги/u' => self::SECTION,
        '/^(?!.*(regulus|esbe|смесительн|тр[её]хходов|насосн))(?:.*)(?:головка термостатическ|термоголовка|термостатическая головка|набор термостатическ|термостатический клапан|термостатическ\w* клапан|термостат радиаторный|^сервопривод salus t30)/u' => 'radiatornaya-armatura',
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
        '/^шланг гибкий гофрированный .*для газа/u' => self::GAS_HOSES,
        '/^шланг душевой/u' => self::PLUMBING_PARTS,
        '/^master flash/u' => 'krovelnye-prohodki-master-flash',
        '/^лампа для бани/u' => 'abazhury-dlya-bani',
        '/energofloor/u' => 'vodyanoy-teplyy-pol',
        '/^(дюбель-хомут|скоба теплого пола)|пешель\) защитная/u' => 'vodyanoy-teplyy-pol',
        '/ливнев|лив\. канал|ливнесток/u' => self::OUTDOOR_SEWER,
        '/^(sanicubic|sanifloor|sanidouche|sanivite|saniaccess)|sanialarm|^канализационная насосная установка|^шиберная задвижка .*sfa/u' => 'kanalizatsionnye-nasosy-sukhoy-ustanovki',
        '/^(печь-камин|чугунная печь-камин|камин\s+этна)/u' => 'pechi-kaminy',
        '/^(закрытая каменка|печь-каменка скала)/u' => 'drovianye-peci-bannye',
        '/^(печь отопительная бв-|печь сибирь)/u' => 'peci-drovianye-otopitelnye',
        '/^печь портативная горный алтай/u' => 'burzhuiki-pechi',
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
            $diameters['/(?:ду|dn)\s*'.$dn.'(?!\d)/u'] = self::dnLabel((int) $dn);
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
            // The same labels and order as the valves: «Краны и запорная арматура» merges them into one list.
            self::METERS => ['Диаметр (Ду)' => [$diameters, array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)]],
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
            self::WATER_FLOOR => [
                'Тип' => [self::WATER_FLOOR_TYPES, array_values(array_unique(self::WATER_FLOOR_TYPES))],
                'Диаметр трубы' => [fn (string $title) => preg_match('/^труба (?!\(пешель).*?(?<!\d)(16|20|25)\s?[x×х]/u', mb_strtolower($title), $m) ? $m[1].' мм' : null, ['16 мм', '20 мм', '25 мм']],
            ],
            self::SHOWER_SYSTEMS => [
                'Управление' => [self::FAUCET_CONTROLS, array_values(array_unique(self::FAUCET_CONTROLS))],
                'Цвет' => [self::FAUCET_COLORS, array_values(self::FAUCET_COLORS)],
            ],
            self::FLANGES => [
                'Тип' => [['/воротников|тип 11/u' => 'Воротниковый (тип 11)', '/плоск|тип 01/u' => 'Плоский приварной (тип 01)'], ['Плоский приварной (тип 01)', 'Воротниковый (тип 11)']],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Давление (Ру)' => [['/ру 6(?!\d)/u' => 'Ру 6', '/ру 10(?!\d)/u' => 'Ру 10', '/ру 16(?!\d)/u' => 'Ру 16', '/ру 25(?!\d)/u' => 'Ру 25'], ['Ру 6', 'Ру 10', 'Ру 16', 'Ру 25']],
            ],
            self::GLOBE_VALVES => [
                'Присоединение' => [['/фланцев|15с65нж/u' => 'Фланцевое', '/(?<![а-я])пп(?![а-я])/u' => 'Под пластиковую или пресс-трубу', '/муфтов|15б\d|скрытого монтажа/u' => 'Внутренняя — внутренняя резьба'], ['Внутренняя — внутренняя резьба', 'Под пластиковую или пресс-трубу', 'Фланцевое']],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Материал' => [['/(?<![а-я])пп(?![а-я])/u' => 'Полипропилен (ПП)', '/15кч|чугун/u' => 'Чугун', '/15с|стальн|benarmo/u' => 'Сталь', '/^(?!прокладк).*(15б\d|латун|bakimay)/u' => 'Латунь'], ['Латунь', 'Полипропилен (ПП)', 'Сталь', 'Чугун']],
            ],
            self::CORRUGATED_FITTINGS => [
                'Тип' => [['/^муфта/u' => 'Муфты', '/^тройник/u' => 'Тройники', '/^угольник/u' => 'Угольники', '/^гайка/u' => 'Гайки накидные'], ['Муфты', 'Тройники', 'Угольники', 'Гайки накидные']],
                'Резьба' => [self::CORRUGATED_THREADS, ['Внутренняя резьба', 'Наружная резьба', 'Без резьбы (труба — труба)']],
                'Диаметр трубы' => [fn (string $title) => preg_match('/(?<![\d\/])(15|20|25|32|40|50)\s*[xх×]/u', $title, $m) ? 'Ду '.$m[1] : null, ['Ду 15', 'Ду 20', 'Ду 25', 'Ду 32', 'Ду 40', 'Ду 50']],
                'Для газа' => [['/газ/u' => 'Для газа'], ['Для газа']],
            ],
            self::HEATING_CABLES => [
                'Мощность' => [fn (string $title) => self::cableSpec($title)[0], self::CABLE_POWERS],
                'Длина кабеля' => [fn (string $title) => self::cableSpec($title)[1], self::CABLE_LENGTHS],
            ],
            self::HUMIDIFIERS => [
                'Тип' => [self::HUMIDIFIER_TYPES, array_values(array_unique(self::HUMIDIFIER_TYPES))],
            ],
            self::HEATING_MATS => [
                'Площадь обогрева' => [fn (string $title) => self::matSpec($title)[0], self::MAT_AREAS],
                'Удельная мощность' => [fn (string $title) => self::matSpec($title)[1], ['150 Вт/м²', '160 Вт/м²', '180 Вт/м²', '200 Вт/м²']],
            ],
            self::PVC_PIPES => [
                'Тип' => [self::PVC_TYPES, array_values(array_unique(self::PVC_TYPES))],
                'Диаметр' => [fn (string $title) => preg_match('/(?<!\d)(110|125|160|225|315)(?!\d)/u', str_ireplace('НПВХ125', 'НПВХ', $title), $m) ? $m[1].' мм' : null, ['110 мм', '125 мм', '160 мм', '225 мм', '315 мм']],
                'Рабочее давление' => [['/ру 0,6|sdr41/u' => 'PN 6 (0,6 МПа)', '/ру 1(,0)? m?мпа|ру 1(,0)? mпа|sdr26/u' => 'PN 10 (1,0 МПа)'], ['PN 6 (0,6 МПа)', 'PN 10 (1,0 МПа)']],
            ],
            self::CHECK_VALVES => [
                'Конструкция' => [self::CHECK_VALVE_TYPES, array_values(array_unique(self::CHECK_VALVE_TYPES))],
                'Присоединение' => [self::CHECK_VALVE_JOINTS, ['Внутренняя — внутренняя резьба', 'Под пластиковую или пресс-трубу', 'Фланцевое', 'Межфланцевое']],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Материал' => [self::CHECK_VALVE_MATERIALS, ['Латунь', 'Полипропилен (ПП)', 'Сталь', 'Нержавеющая сталь', 'Чугун']],
            ],
            self::DRY_SEWAGE_PUMPS => [
                'Тип' => [self::DRY_SEWAGE_TYPES, array_values(array_unique(self::DRY_SEWAGE_TYPES))],
                'Мощность двигателя' => [fn (string $title) => self::wellmixPower($title), ['До 1,5 кВт', '2,2–4 кВт', '5,5–7,5 кВт', '11–15 кВт', '18,5–22 кВт']],
                'Диаметр выхода (Ду)' => [fn (string $title) => preg_match('/wellmix (\d{2,3}) wl/u', mb_strtolower($title), $m) ? 'Ду '.$m[1] : null, ['Ду 50', 'Ду 65', 'Ду 80', 'Ду 100', 'Ду 150', 'Ду 200', 'Ду 250', 'Ду 300']],
            ],
            self::GRILLS => [
                'Тип' => [self::GRILL_TYPES, array_values(array_unique(self::GRILL_TYPES))],
                'Конструкция' => [self::GRILL_BUILDS, array_values(array_unique(self::GRILL_BUILDS))],
                'Крыша' => [['/^(мангал|пп мангал).*(крыш|навес)|^мангал-беседка/u' => 'С крышей или навесом'], ['С крышей или навесом']],
                'Толщина стали' => [['/сталь 2\s?мм/u' => '2 мм', '/сталь 3\s?мм/u' => '3 мм', '/сталь 5\s?мм/u' => '5 мм'], ['2 мм', '3 мм', '5 мм']],
            ],
            self::HOUSEHOLD_FANS => [
                'Тип' => [self::HOUSEHOLD_FAN_TYPES, array_values(array_unique(self::HOUSEHOLD_FAN_TYPES))],
                'Диаметр' => [['/^(ballu baf|royal thermo raf)\S*[- ]?(100)\b/u' => '100 мм', '/^(ballu baf|royal thermo raf)\S*[- ]?(120)\b/u' => '120 мм', '/^(ballu baf|royal thermo raf)\S*[- ]?(150)\b/u' => '150 мм'], ['100 мм', '120 мм', '150 мм']],
            ],
            self::FIREWOOD_RACKS => [
                'Тип' => [self::FIREWOOD_TYPES, array_values(array_unique(self::FIREWOOD_TYPES))],
            ],
            self::TOOLS => [
                'Тип' => [self::TOOL_TYPES, self::TOOL_ORDER],
            ],
            self::INSTALLATIONS => [
                'Тип' => [self::INSTALLATION_TYPES, array_values(array_unique(self::INSTALLATION_TYPES))],
                'Подводка воды' => [['/бок\.|боков/u' => 'Боковая', '/ниж\.|нижн/u' => 'Нижняя'], ['Боковая', 'Нижняя']],
                'Режим смыва' => [['/двойн|двухрежимн/u' => 'Двойной', '/однорежимн/u' => 'Одинарный'], ['Двойной', 'Одинарный']],
                'Цвет кнопки' => [self::FLUSH_BUTTON_COLORS, array_values(array_unique(self::FLUSH_BUTTON_COLORS))],
            ],
            self::MANHOLES => [
                'Тип' => [self::MANHOLE_TYPES, array_values(array_unique(self::MANHOLE_TYPES))],
                'Класс нагрузки' => [fn (string $title) => self::loadClass($title), self::LOAD_CLASSES],
                'Материал' => [self::MANHOLE_MATERIALS, array_values(array_unique(self::MANHOLE_MATERIALS))],
                'Цвет люка' => [['/^люк.*зел[её]н/u' => 'Зелёный', '/^люк.*ч[её]рн/u' => 'Чёрный', '/^люк.*сер/u' => 'Серый', '/^люк.*красн/u' => 'Красный'], ['Зелёный', 'Чёрный', 'Серый', 'Красный']],
            ],
            self::FIREPLACE_GRILLES => [
                'Тип' => [self::GRILLE_TYPES, array_values(array_unique(self::GRILLE_TYPES))],
                'Размер' => [fn (string $title) => self::grilleSize($title), self::GRILLE_SIZES],
                'Цвет' => [self::GRILLE_COLORS, array_values(array_unique(self::GRILLE_COLORS))],
                'Жалюзи' => [['/жалюзи/u' => 'С жалюзи'], ['С жалюзи']],
            ],
            self::HYDRO_SEPARATORS => [
                'Тип' => [self::HYDRO_SEPARATOR_TYPES, array_values(array_unique(self::HYDRO_SEPARATOR_TYPES))],
                'Число контуров' => [fn (string $title) => self::hydroCircuits($title), array_map(fn (int $n) => self::circuitsLabel($n), range(2, 12))],
            ],
            self::SANITARY_WARE => [
                'Тип' => [self::SANITARY_TYPES, array_values(array_unique(self::SANITARY_TYPES))],
                'Материал' => [self::SANITARY_MATERIALS, array_values(array_unique(self::SANITARY_MATERIALS))],
                'Выпуск унитаза' => [['/^унитаз-компакт.*(косой|кос\.|диагональн)/u' => 'Косой', '/^унитаз-компакт.*(горизонтальн|гор\.)/u' => 'Горизонтальный'], ['Косой', 'Горизонтальный']],
                'Цвет мойки' => [self::SINK_COLORS, array_values(array_unique(self::SINK_COLORS))],
            ],
            self::PUMP_GROUPS => [
                'Тип' => [self::PUMP_GROUP_TYPES, array_values(array_unique(self::PUMP_GROUP_TYPES))],
                'Контур' => [fn (string $title) => self::pumpGroupCircuit($title), ['Прямой', 'Смесительный', 'Защита обратки', 'Гелиосистема']],
                'Насос в комплекте' => [fn (string $title) => self::pumpGroupPump($title), ['С насосом', 'Без насоса']],
                'Присоединение' => [fn (string $title) => self::pumpGroupDn($title), ['Ду 20 (3/4")', 'Ду 25 (1")', 'Ду 32 (1 1/4")', 'Ду 40 (1 1/2")']],
            ],
            self::MP_FITTINGS => [
                'Тип' => [self::PRESS_TYPES, self::PRESS_ORDER],
                'Способ соединения' => [self::MP_JOINTS, array_values(array_unique(self::MP_JOINTS))],
                'Резьба' => [self::MP_THREADS, array_values(array_unique(self::MP_THREADS))],
                'Диаметр трубы' => [fn (string $title) => self::mpDiameter($title), ['16 мм', '18 мм', '20 мм', '25 мм', '26 мм', '28 мм', '32 мм']],
                'Материал' => [self::MP_MATERIALS, array_values(array_unique(self::MP_MATERIALS))],
            ],
            self::SIPHONS => [
                'Назначение' => [self::SIPHON_USES, array_values(array_unique(self::SIPHON_USES))],
                'Конструкция' => [self::SIPHON_KINDS, array_values(array_unique(self::SIPHON_KINDS))],
                'Материал' => [['/металл|хром|нерж\\. сталь,|^сифон для сбора конденсата под/u' => 'Металлический', '/^(сифон|донный)/u' => 'Пластиковый'], ['Металлический', 'Пластиковый']],
                'Отвод для стиральной машины' => [['/стир\\. ?маш|стиральной машин|с\\/м/u' => 'С отводом для стиральной машины'], ['С отводом для стиральной машины']],
            ],
            self::PIPE_INSULATION => [
                'Тип' => [self::INSULATION_TYPES, ['Трубки из вспененного полиэтилена', 'Цилиндры из минеральной ваты', 'Трубки из вспененного каучука', 'Ленты и аксессуары', 'Шумоизоляция канализации', 'Прочее']],
                'Диаметр трубы' => [fn (string $title) => self::insulationSize($title, 0), ['До 18 мм', '20–28 мм', '32–42 мм', '45–64 мм', '76–114 мм']],
                'Толщина изоляции' => [fn (string $title) => self::insulationSize($title, 1), ['4 мм', '6 мм', '9 мм', '13 мм', '20 мм', '30 мм', '40 мм', '50 мм', '60 мм']],
                'Цвет' => [['/^(трубка|теплоизоляция).*красн/u' => 'Красная', '/^(трубка|теплоизоляция).*син/u' => 'Синяя', '/^(трубка|теплоизоляция).*ч[её]рн/u' => 'Чёрная'], ['Красная', 'Синяя', 'Чёрная']],
            ],
            self::SAUNA_DOORS => [
                'Цвет стекла' => [self::DOOR_GLASS, ['Бронза', 'Бронза матовая', 'Графит', 'Графит матовый', 'Сатин (матовое)', 'Прозрачное']],
                'Высота' => [fn (string $title) => self::doorSize($title, 0), ['170 см', '180 см', '190 см', '200 см', '210 см']],
                'Ширина' => [fn (string $title) => self::doorSize($title, 1), ['60 см', '70 см', '80 см', '90 см']],
            ],
            self::CAST_PIPES => [
                'Тип' => [self::CAST_PIPE_TYPES, array_values(array_unique(self::CAST_PIPE_TYPES))],
                'Назначение' => [self::CAST_PIPE_USES, array_values(self::CAST_PIPE_USES)],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title)
                    ?? (preg_match('/^труба[^\d]*(50|100|150)\s*[xх]\s*\d{3,4}/u', mb_strtolower($title), $m) ? self::dnLabel((int) $m[1]) : null),
                    array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
            ],
            self::SEALANTS => [
                'Тип' => [self::SEALANT_TYPES, self::SEALANT_ORDER],
                'Диаметр (Ду)' => [fn (string $title) => preg_match('/^прокладк/u', mb_strtolower(trim($title))) ? self::ballValveDn($title) : null, array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Материал прокладки' => [self::GASKET_MATERIALS, array_values(self::GASKET_MATERIALS)],
            ],
            self::PIPE_CLAMPS => [
                'Тип' => [self::CLAMP_TYPES, self::CLAMP_ORDER],
                'Диаметр трубы' => [fn (string $title) => self::clampPipeSize($title), ['До 20 мм', '21–32 мм', '33–50 мм', '51–76 мм', '77–114 мм', 'Свыше 114 мм']],
            ],
            self::PP_PIPES => [
                'Армирование' => [self::PP_PIPE_REINFORCEMENT, array_values(self::PP_PIPE_REINFORCEMENT)],
                'Диаметр' => [fn (string $title) => self::pipeDiameter($title, self::PP_DIAMETERS), array_map(fn (int $mm) => $mm.' мм', self::PP_DIAMETERS)],
                'Давление' => [fn (string $title) => preg_match('/(?:pn|ру)\s?(10|16|20|25)(?!\d)/u', mb_strtolower($title), $m) ? 'PN '.$m[1] : null, ['PN 10', 'PN 16', 'PN 20', 'PN 25']],
                'Цвет' => [self::PP_COLORS, array_values(self::PP_COLORS)],
                'Длина' => [fn (string $title) => preg_match('/(?<![\d.,])([1234])\s?м(?![а-ям])/u', mb_strtolower($title), $m) ? $m[1].' м' : null, ['1 м', '2 м', '3 м', '4 м']],
            ],
            self::PEX_PIPES => [
                'Материал' => [fn (string $title) => self::pexPipeMaterial($title), ['PE-Xa', 'PE-Xb', 'PE-Xc', 'PE-X (тип не указан)', 'PE-RT', 'Металлополимерные (PE-Xa/AL/PE)']],
                'Диаметр' => [fn (string $title) => self::pexPipeDiameter($title), ['14 мм', '16 мм', '17 мм', '18 мм', '20 мм', '25 мм', '32 мм']],
                'Кислородный барьер' => [fn (string $title) => self::pexPipeBarrier($title), ['С кислородным барьером', 'Без барьера']],
                'Длина' => [fn (string $title) => self::pexPipeLength($title), ['До 10 м', '11–50 м', '51–100 м', '101–200 м', 'Свыше 200 м']],
            ],
            self::STEEL_FITTINGS => [
                'Тип' => [self::STEEL_TYPES, array_values(array_unique(self::STEEL_TYPES))],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Исполнение' => [['/бесшовн/u' => 'Бесшовные', '/шовн/u' => 'Шовные'], ['Бесшовные', 'Шовные']],
                'Покрытие' => [self::STEEL_FINISHES, ['Оцинкованные', 'Без покрытия (чёрные)']],
            ],
            self::WATER_FILTERS => [
                'Тип' => [self::WATER_FILTER_TYPES, array_values(array_unique(self::WATER_FILTER_TYPES))],
                'Диаметр (Ду)' => [fn (string $title) => self::match($title, self::WATER_FILTER_TYPES) === 'Картриджи и фильтрующие элементы' ? null : self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Размер картриджа' => [fn (string $title) => self::cartridgeSize($title), ['10" Slim Line', '10" Big Blue', '20" Big Blue', '5"']],
            ],
            self::FASTENERS => [
                'Тип' => [self::FASTENER_TYPES, array_values(array_unique(self::FASTENER_TYPES))],
                'Размер резьбы' => [fn (string $title) => self::fastenerThread($title), array_map(fn (int $m) => 'М'.$m, self::FASTENER_THREADS)],
            ],
            self::STOVE_CASTINGS => [
                'Тип' => [self::CASTING_TYPES, array_values(array_unique(self::CASTING_TYPES))],
                'Покрытие' => [self::CASTING_FINISHES, array_values(self::CASTING_FINISHES)],
            ],
            self::OUTDOOR_SEWER => [
                'Тип' => [self::SEWER_TYPES, self::SEWER_ORDER],
                'Диаметр' => [fn (string $title) => self::sewerDiameter($title, self::OUTDOOR_SEWER_DIAMETERS), array_map(fn (int $mm) => $mm.' мм', self::OUTDOOR_SEWER_DIAMETERS)],
                'Класс жёсткости' => [['/sn\s?2(?!\d)/u' => 'SN2', '/sn\s?4(?!\d)/u' => 'SN4', '/sn\s?6(?!\d)/u' => 'SN6', '/sn\s?8(?!\d)/u' => 'SN8'], ['SN2', 'SN4', 'SN6', 'SN8']],
                'Материал' => [self::OUTDOOR_SEWER_MATERIALS, array_values(self::OUTDOOR_SEWER_MATERIALS)],
                'Длина трубы' => [fn (string $title) => self::outdoorPipeLength($title), ['До 1 м', '1–2 м', '3 м', '6 м']],
            ],
            self::FLEX_HOSES => [
                'Назначение' => [self::FLEX_USES, array_values(self::FLEX_USES)],
                'Присоединение' => [self::FLEX_JOINTS, array_values(self::FLEX_JOINTS)],
                'Резьба' => [self::FLEX_THREADS, ['1/2"', '3/4"', '1"']],
                'Оплётка' => [self::FLEX_BRAIDS, array_values(self::FLEX_BRAIDS)],
                'Длина' => [fn (string $title) => self::flexLength($title), self::FLEX_LENGTHS],
            ],
            self::SAFETY_VALVES => [
                'Тип' => [self::SAFETY_TYPES, array_values(array_unique(self::SAFETY_TYPES))],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
            ],
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
                'Присоединение' => [self::BALL_JOINTS, self::BALL_JOINT_ORDER],
                'Диаметр (Ду)' => [fn (string $title) => self::ballValveDn($title), array_map(fn (int $dn) => self::dnLabel($dn), self::BALL_DNS)],
                'Ручка' => [self::BALL_HANDLES, array_values(array_unique(self::BALL_HANDLES))],
                'Для газа' => [self::BALL_GAS, array_values(self::BALL_GAS)],
                'Материал' => [fn (string $title) => self::ballValveMaterial($title), ['Латунь', 'Полипропилен (ПП)', 'Пластик (для ПЭ труб)', 'Сталь', 'Нержавеющая сталь', 'Чугун']],
            ],
            self::PP_FITTINGS => [
                'Тип' => [self::PP_TYPES, self::PP_ORDER],
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
        'Балансировочные и регулирующие клапаны',
        'Запасные термоэлементы',
        'Прочее',
    ];

    /** Title pattern => «Тип» inside «Смесительные клапаны и узлы»; the first match wins. */
    public const MIXING_TYPES = [
        '/^(сервопривод|привод|электропривод|переходники для приводов)/u' => 'Приводы и сервоприводы',
        '/термостатическ\w* элемент/u' => 'Запасные термоэлементы',
        '/насосн\w* групп|насосно-смесительн|смесительн\w* узел|узел смесительн|mix-unit|laddomat|смесительное устройство/u' => 'Насосно-смесительные группы и узлы',
        '/балансировочн/u' => 'Балансировочные и регулирующие клапаны',
        '/(?<![a-z])vta|atm |атм |am63c|aquamix|r156|tvmix|tsv-tv|regulus tv /u' => 'Термостатические смесители (ГВС, тёплый пол)',
        '/regulus tsv|(?<![a-z])atv |термический для котлов|vtc511/u' => 'Термостатические клапаны для котлов (антиконденсат)',
        '/четыр[её]хходов|шестиходов|vrg 141|arv 484/u' => 'Четырёх- и шестиходовые клапаны',
        '/двухходов|2-х ходов/u' => 'Двухходовые клапаны',
        '/тр[её]хходов|3-х ходов|3-ходов|vrg 131|lk ?840|trv-3|приоритета|cv316/u' => 'Трёхходовые клапаны',
        '/./u' => 'Прочее',
    ];

    /** Sections whose filters also cover the cards of their subsections (the parent page lists them all). */
    public const WITH_SUBSECTIONS = [self::PELLET_BURNERS, self::LEAKS, self::BATH_ACCESSORIES, self::SAFETY_VALVES];

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

    /** «Тип» options of «Фитинги для полипропиленовых труб», the most asked-for first. */
    public const PP_ORDER = [
        'Муфты, американки и переходники',
        'Тройники',
        'Отводы и колена',
        'Заглушки',
        'Обводы',
        'Крестовины',
        'Краны и вентили',
        'Компенсаторы',
        'Фильтры',
        'Фланцы и бурты',
        'Опоры и крепления',
        'Коллекторы и узлы',
        'Сёдла',
        'Инструмент и прочее',
    ];

    /** Title pattern => «Тип» inside «Фитинги для полипропиленовых труб»; the first match wins. */
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

    /** «Присоединение» options of a ball valve, the household threads first. */
    public const BALL_JOINT_ORDER = [
        'Внутренняя — внутренняя резьба',
        'Внутренняя — наружная резьба',
        'Наружная — наружная резьба',
        'С американкой, гайкой или сгоном',
        'Под пластиковую или пресс-трубу',
        'Фланцевое',
        'Под приварку',
    ];

    /** Title pattern => «Присоединение» of a ball valve; the first match wins. */
    public const BALL_JOINTS = [
        '/фланц|ф\/ф|кшцф|кшшс/u' => 'Фланцевое',
        '/приварк|кшцп|с\/с/u' => 'Под приварку',
        '/(?<![а-я])пп(?![а-я])|ppr|для пэ|пресс/u' => 'Под пластиковую или пресс-трубу',
        '/американк|разборн|накидн|гайк|сгон|giacoqest/u' => 'С американкой, гайкой или сгоном',
        '/нар\.?-нар|нар\.? х [^ ]+ нар|(?<!\/)наружной резьбой(?!.*внутр)|(?<![а-яa-z])(?:нн|рр)(?![а-яa-z])/u' => 'Наружная — наружная резьба',
        '/нар\.?-вн|вн\.?-нар|внутренней\/наружной|наружной\/внутренней|(?<![а-яa-z])(?:нв|мр)(?![а-яa-z])|муфта-цапка/u' => 'Внутренняя — наружная резьба',
        '/вн\.?-вн|внутренней резьбой|(?<![а-яa-z])(?:вв|мм)(?![а-яa-z])|муфтов/u' => 'Внутренняя — внутренняя резьба',
    ];

    /**
     * The body material of a ball valve: named in the title, or by series (КШЦ/КШТ/КШШС steel, 11Б27 brass); a plain
     * threaded or union valve without a word for it is a brass one.
     */
    public static function ballValveMaterial(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (! preg_match('/кран/u', $t)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/нерж/u', $t) => 'Нержавеющая сталь',
            (bool) preg_match('/чугун/u', $t) => 'Чугун',
            (bool) preg_match('/(?<![а-я])пп(?![а-я])|ppr|полипропилен/u', $t) => 'Полипропилен (ПП)',
            (bool) preg_match('/пластик|для пэ труб/u', $t) => 'Пластик (для ПЭ труб)',
            (bool) preg_match('/стальн|кшц|кшт|кшшс|бивал|рунак|приварк|фланц/u', $t) => 'Сталь',
            (bool) preg_match('/латун|(?<![а-я])лат\.|11б27|giacoqest|пресс-соедин|крдш|кран крн/u', $t) => 'Латунь',
            in_array(self::match($t, self::BALL_JOINTS), ['Внутренняя — внутренняя резьба', 'Внутренняя — наружная резьба', 'Наружная — наружная резьба', 'С американкой, гайкой или сгоном'], true) => 'Латунь',
            default => null,
        };
    }

    /** Title pattern => «Ручка» of a ball valve. */
    public const BALL_HANDLES = [
        '/бабочк|(?<![а-я])баб\./u' => 'Бабочка',
        '/флажок/u' => 'Флажок',
        '/рычаг|(?<![а-я])рыч\.|ручка(?!-)/u' => 'Рычаг',
    ];

    /** Title pattern => «Для газа». */
    public const BALL_GAS = ['/(?<![а-я])газ(?![а-я])/u' => 'Для газа'];

    /** The nominal sizes a ball valve is listed by. */
    public const BALL_DNS = [10, 15, 20, 25, 32, 40, 50, 65, 80, 100, 125, 150, 200, 250, 300, 400, 500];

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
        $title = str_replace('\\', '/', mb_strtolower(trim(html_entity_decode($title))));
        $dn = null;
        $pipe = [16 => 10, 18 => 15, 20 => 15, 25 => 20, 32 => 25, 40 => 32, 50 => 40, 63 => 50];
        if (preg_match('/пресс-соединения ду\s*(\d{2})/u', $title, $m) && isset($pipe[(int) $m[1]])) {
            $dn = $pipe[(int) $m[1]];
        } elseif (preg_match('/(?:ду|dn)\s*(\d{2,3})/u', $title, $m)) {
            $dn = (int) $m[1];
        } elseif (preg_match('/(?:trv|ктз|ск-11)-?\s?(\d{2,3})(?!\d)|rdt-[\d.]+-(\d{2,3})/u', $title, $m)) {
            $dn = (int) ($m[1] !== '' ? $m[1] : $m[2]);
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
        '/заглушк|^крышк/u' => 'Заглушки',
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
    public static function sewerDiameter(string $title, array $sizes = self::SEWER_DIAMETERS): ?string
    {
        $title = preg_replace('/(?<![\w])(?:дн|ду|d)\s?(?=\d)/u', ' ', mb_strtolower(trim(html_entity_decode($title))));
        if (preg_match_all('/(?<![\w.,\/])(\d{2,3})(?=\s*(?:[xх×*\/,]|мм|$|\s|\())/u', $title, $m)) {
            foreach ($m[1] as $mm) {
                $mm = ['58' => 50, '78' => 75][$mm] ?? (int) $mm;
                if (in_array($mm, $sizes, true)) {
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
        '/сетка для камней|сетка-каменка|сэндвич-сетка|сетка на трубу|^каменка (натрубная|навесная|к печи)|модуль закрытой каменки|царь-печи каменка|короб для камней|отопитель натрубн/u' => 'Сетки и каменки для камней',
        '/^шайк|ведро|ковш|черпак|обливн|запарник|ушат/u' => 'Шайки, ковши и обливные устройства',
        '/абажур|светильник|лампа/u' => 'Абажуры и светильники',
        '/пульт|блок управлени|^пу для/u' => 'Пульты и блоки управления',
        '/вентиляц|клапан/u' => 'Вентиляция',
        '/бак |^бак|регистр|теплообменник|конвектор дымохода|экономайзер|теплосъемник|съемник тепла|теплоаккумулятор/u' => 'Баки, регистры и теплообменники',
        '/парогенератор|турбопар|парообразовател/u' => 'Парогенераторы',
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

    public const SAFETY_VALVES = 'predokhranitelnaya-i-reguliruyushchaya-armatura';

    /**
     * Title pattern => «Тип» inside «Предохранительная и регулирующая арматура» and «Группы безопасности», in filter
     * order. Varmega VM166/VM167 cards named by article and set pressure are safety valves.
     */
    public const SAFETY_TYPES = [
        '/группа безопасности/u' => 'Группы безопасности',
        '/предохранит|аварийн\w* клапан|клапан аварийн|^varmega vm16[67]/u' => 'Предохранительные клапаны',
        '/редуктор|регулятор давления/u' => 'Редукторы давления',
        '/перепад|перепускн/u' => 'Регуляторы перепада и перепускные клапаны',
        '/балансировочн|клапан регулирующ/u' => 'Балансировочные и регулирующие клапаны',
        '/обратн/u' => 'Обратные клапаны',
        '/воздухоотводчик|развоздуш|сепаратор/u' => 'Воздухоотводчики и сепараторы',
        '/электромагнитн|отсекающ|термозапорн/u' => 'Электромагнитные и отсекающие клапаны',
        '/электропривод/u' => 'Приводы и сервоприводы',
        '/sani|sololift|канализационная насосная|аварийная сигнализация/u' => 'Канализационные насосные установки (SFA)',
        '/задвижк/u' => 'Задвижки',
        '/3-х|приоритет|подпиточн|компенсатор|клапан/u' => 'Прочие клапаны',
        '/./u' => 'Комплектующие',
    ];

    public const FLEX_HOSES = 'gibkaya-podvodka-dlya-vody';

    /** Title pattern => «Назначение» of a water connection hose; the first match wins. */
    public const FLEX_USES = [
        '/стирал|посудомоеч/u' => 'Для стиральных и посудомоечных машин',
        '/станций водоснабжения/u' => 'Для насосных станций',
        '/иголка|м10|m10|ml10|смесител/u' => 'Для смесителей (иголка М10)',
        '/./u' => 'Универсальная (резьба — резьба)',
    ];

    /** Title pattern => «Присоединение» of a water connection hose. */
    public const FLEX_JOINTS = [
        '/иголка|м10|m10|ml10/u' => 'Иголка М10 — гайка',
        '/гайка\s*\d[^-–]*[-–]\s*гайка|гайка\/гайка|вр[–-]g?1\/2″?\s*вр|внутренняя\/внутренняя|внутренняя резьба мм|(?<![а-яa-z])[мm][мm]\s+(?=[\df])|г\/г/u' => 'Гайка — гайка (ВР–ВР)',
        '/штуцер|(?<![а-я])нр(?![а-я])|наружн|(?<![а-я])мр(?![а-я])/u' => 'Гайка — штуцер (ВР–НР)',
    ];

    /** Title pattern => «Резьба» of a water connection hose (the larger thread of the two). */
    public const FLEX_THREADS = [
        '/(?<![\d\/])1"(?!\d)/u' => '1"',
        '/3\/4/u' => '3/4"',
        '/1\/2|3\/8/u' => '1/2"',
    ];

    /** Title pattern => «Оплётка» of a water connection hose. */
    public const FLEX_BRAIDS = [
        '/(?<![a-z])sus(?![a-z])|нерж|s\.s|сильфон/u' => 'Нержавеющая сталь',
        '/nylon|нейлон/u' => 'Нейлон',
        '/пвх/u' => 'ПВХ',
    ];

    /** «Длина» options of a water connection hose. */
    public const FLEX_LENGTHS = ['До 50 см', '60–80 см', '90–120 см', '150 см', '200 см и длиннее'];

    /** The length band of a hose from «80см», «L= 150 см», «500 мм» or «1,2 м». */
    public static function flexLength(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        $cm = match (true) {
            (bool) preg_match('/(\d{2,3})\s*[cс]м/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(\d{3,4})\s*мм/u', $title, $m) => (int) round($m[1] / 10),
            (bool) preg_match('/(?<![\d.,])(\d(?:[.,]\d)?)\s*м(?![а-ям])/u', $title, $m) => (int) round((float) str_replace(',', '.', $m[1]) * 100),
            default => null,
        };

        return match (true) {
            $cm === null => null,
            $cm <= 50 => 'До 50 см',
            $cm <= 80 => '60–80 см',
            $cm <= 120 => '90–120 см',
            $cm <= 150 => '150 см',
            default => '200 см и длиннее',
        };
    }

    public const OUTDOOR_SEWER = 'naruzhnaya-kanalizatsiya';

    /** The outdoor sewer pipe sizes, mm. */
    public const OUTDOOR_SEWER_DIAMETERS = [90, 110, 125, 160, 200, 250, 315, 400, 500, 630];

    /** Title pattern => «Материал» of an outdoor sewer part; the first match wins. */
    public const OUTDOOR_SEWER_MATERIALS = [
        '/гофрирован|двухслойн|prokan|корсис/u' => 'Гофрированные (двухслойные)',
        '/полипропилен|(?<![а-я])пп(?![а-я])/u' => 'Полипропилен (ПП)',
        '/пвх|(?<![а-я])нк(?![а-я])/u' => 'ПВХ',
    ];

    /** The length band of an outdoor sewer pipe from «110х3,2х3000», «НК 110х2000мм» or «(6 м)»; fittings get none. */
    public static function outdoorPipeLength(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! str_starts_with($title, 'труба')) {
            return null;
        }
        $mm = match (true) {
            (bool) preg_match('/\d+\s*[xх]\s*\d+(?:[.,]\d+)?\s*[xх]\s*(\d{3,4})/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/\d{2,3}\s*[xх]\s*(\d{3,4})\s*мм/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/\((\d+(?:[.,]\d+)?)\s*м\)/u', $title, $m) => (int) round((float) str_replace(',', '.', $m[1]) * 1000),
            default => null,
        };

        return match (true) {
            $mm === null => null,
            $mm <= 1000 => 'До 1 м',
            $mm <= 2000 => '1–2 м',
            $mm <= 3500 => '3 м',
            default => '6 м',
        };
    }

    public const STOVE_CASTINGS = 'pechnoe-i-kaminnoe-lite';

    /** Title pattern => «Тип» inside «Печное и каминное литьё», in filter order; the first match wins. */
    public const CASTING_TYPES = [
        '/^стекло/u' => 'Стёкла для дверок',
        '/поддувал|зольн/u' => 'Поддувальные дверки и зольники',
        '/прочистн/u' => 'Прочистные дверки',
        '/каминн|панорамн/u' => 'Каминные дверки',
        '/дверк|дверц|двер[ьи]/u' => 'Топочные (печные) дверки',
        '/тоннел/u' => 'Монтажные тоннели',
        '/колосник/u' => 'Колосники',
        '/плит|конфорк/u' => 'Плиты и конфорки',
        '/задвижк|шибер/u' => 'Задвижки',
        '/духов/u' => 'Духовые шкафы',
        '/мангал|гриль|очаг/u' => 'Мангалы и очаги-гриль',
        '/^труба|проходной элемент|фартук/u' => 'Трубы и проходные элементы',
        '/./u' => 'Фурнитура и прочее',
    ];

    /** Title pattern => «Покрытие» of a cast door (Везувий and the like name it in brackets). */
    public const CASTING_FINISHES = [
        '/бронз/u' => 'Бронза',
        '/антрацит/u' => 'Антрацит',
        '/не крашен|некрашен/u' => 'Не крашенная',
    ];

    public const FASTENERS = 'krepezh-i-metizy';

    /** Title pattern => «Тип» inside «Крепёж и метизы», in filter order; the first match wins. */
    public const FASTENER_TYPES = [
        '/анкер|дюбел|цанга/u' => 'Анкеры и дюбели',
        '/шпильк/u' => 'Шпильки и шуруп-шпильки',
        '/^(болт|винт(?!-шуруп))/u' => 'Болты и винты',
        '/^гайк|муфта .*прутк/u' => 'Гайки',
        '/^шайб|подпятник/u' => 'Шайбы',
        '/саморез|шуруп|винт-шуруп/u' => 'Саморезы и шурупы',
        '/профил|консол|стойка монтаж|уголок|соединитель профиля|rapidrail|опора кровельн|t-соединит|заглушка пластиков|пластин|соединитель|угол|планка|шиногиб|гипсокартон/u' => 'Монтажные профили и консоли',
        '/клипс|держател|скоба|крепеж|хомут/u' => 'Клипсы, скобы и держатели',
        '/лента|стяжк/u' => 'Ленты и стяжки',
        '/./u' => 'Прочее',
    ];

    /** The metric threads fasteners are listed by. */
    public const FASTENER_THREADS = [4, 5, 6, 8, 10, 12, 14, 16, 20, 24];

    /** The thread of a fastener from «М10», «M8х70», «М10-20/24»; plastic plugs and tapes get none. */
    public static function fastenerThread(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (preg_match('/(?<![a-zа-я])[мm]\s?(\d{1,2})(?=\D|$)/u', $title, $m) && in_array((int) $m[1], self::FASTENER_THREADS, true)) {
            return 'М'.(int) $m[1];
        }

        return null;
    }

    public const WATER_FILTERS = 'filtry';

    /** Title pattern => «Тип» inside «Фильтры», in filter order; the first match wins. */
    public const WATER_FILTER_TYPES = [
        '/^фильтр для очистки воды|обратн\w* осмос|osmoprof/u' => 'Системы очистки питьевой воды',
        '/промывн|самоочищ/u' => 'Промывные фильтры',
        '/шламоуловит|магнитн/u' => 'Шламоуловители и магнитные фильтры',
        '/грязевик|фланцев|чугунн/u' => 'Грязевики и фланцевые фильтры',
        '/косой|y-образн|т-образн|сетчат|осадочный (ду|для)|фильтр осадочный|^фильтр латунный|^фильтр косой|^фильтр универсальн/u' => 'Сетчатые (косые) фильтры грубой очистки',
        '/картридж|фильтрующий элемент/u' => 'Картриджи и фильтрующие элементы',
        '/корпус|колба|аквабрайт|модуль|unifilter/u' => 'Корпуса (колбы) фильтров',
        '/накип|protector|bwt/u' => 'Фильтры от накипи',
        '/ballu|air master|воздухоочист/u' => 'Фильтры для очистителей воздуха',
        '/./u' => 'Комплектующие',
    ];

    /** The cartridge (or housing) size: 10" Slim Line, 10"/20" Big Blue, 5". */
    public static function cartridgeSize(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! preg_match('/картридж|фильтрующий элемент|корпус|колба|аквабрайт|модуль|unifilter|набор/u', $title)) {
            return null;
        }
        $bb = (bool) preg_match('/big blue|(?<![a-z])bb(?![a-z])|\dbb|ббб?|ббб|бб-/u', $title);
        $inches = preg_match('/(?<!\d)(5|10|20)\s*(?:"|″|”|дюйм)/u', $title, $m) ? (int) $m[1]
            : (preg_match('/(?:bb|sl|бб-?)(10|20)|(10|20)(?:bb|sl|бб)/u', $title, $m) ? (int) ($m[1] !== '' ? $m[1] : $m[2]) : null);

        return match (true) {
            $inches === 5 => '5"',
            $inches === 20 => '20" Big Blue',
            $inches === 10 && $bb => '10" Big Blue',
            $inches === 10 => '10" Slim Line',
            default => null,
        };
    }

    public const STEEL_FITTINGS = 'stalnye-detali-truboprovodov';

    /** Title pattern => «Тип» inside «Стальные детали трубопроводов», in filter order; the first match wins. */
    public const STEEL_TYPES = [
        '/^переход/u' => 'Переходы',
        '/^отвод/u' => 'Отводы',
        '/^тройник/u' => 'Тройники',
        '/^заглушк/u' => 'Заглушки',
        '/компенсатор/u' => 'Компенсаторы',
        '/./u' => 'Опоры, уголок и прочее',
    ];

    /** Title pattern => «Покрытие» of a steel fitting: galvanised, otherwise black (the welded fittings). */
    public const STEEL_FINISHES = [
        '/оцинк/u' => 'Оцинкованные',
        '/^(переход|отвод|тройник|заглушк)/u' => 'Без покрытия (чёрные)',
    ];

    public const PEX_PIPES = 'truby-iz-sshitogo-polietilena';

    /** A PEX pipe title in Latin letters: the titles mix Cyrillic «РЕ-Х», «PE-Xа» with Latin «PE-Xa». */
    private static function pexTitle(string $title): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! preg_match('/^(уценка )?труба(?! \(пешель)/u', $title)) {
            return null;
        }

        return strtr($title, ['р' => 'p', 'е' => 'e', 'х' => 'x', 'а' => 'a', 'с' => 'c', 'в' => 'b']);
    }

    public static function pexPipeMaterial(string $title): ?string
    {
        $t = self::pexTitle($title);

        return match (true) {
            $t === null => null,
            (bool) preg_match('/stabil|\/al\/|\/ai\/|platinum/u', $t) => 'Металлополимерные (PE-Xa/AL/PE)',
            (bool) preg_match('/pe-?xa|rautitan|rautherm|therm s|flex 16/u', $t) => 'PE-Xa',
            (bool) preg_match('/pe-?xb/u', $t) => 'PE-Xb',
            (bool) preg_match('/pe-?xc|teceflex|multifit-pex/u', $t) => 'PE-Xc',
            (bool) preg_match('/pe-?rt/u', $t) => 'PE-RT',
            (bool) preg_match('/pe-?x|pex/u', $t) => 'PE-X (тип не указан)',
            default => null,
        };
    }

    /** The outside diameter: «16х2», «16,2х2,6» (16), «Ø 17х2», «DN 16х2,0»; inch-sized Giacoqest pipes get none. */
    public static function pexPipeDiameter(string $title): ?string
    {
        $t = self::pexTitle($title);
        if ($t === null || ! preg_match('/(?<![\d.,])(14|16|17|18|20|25|32)(?:[.,]\d)?\s*[xх]\s*\d/u', $t, $m)) {
            return null;
        }

        return $m[1].' мм';
    }

    public static function pexPipeBarrier(string $title): ?string
    {
        $t = self::pexTitle($title);

        return match (true) {
            $t === null => null,
            (bool) preg_match('/evoh|evon|kиcлopoд|кислород|антидиффуз|aнтидиффуз|5s|пятислойн|многослойн|stabil|\/al\/|\/ai\//u', mb_strtolower(trim(html_entity_decode($title)))) => 'С кислородным барьером',
            (bool) preg_match('/однослойн/u', mb_strtolower($title)) => 'Без барьера',
            default => null,
        };
    }

    /** The coil or cut length: «(отрезок 33 м)», «бухта 200 м», «(240 м)». */
    public static function pexPipeLength(string $title): ?string
    {
        $t = self::pexTitle($title);
        if ($t === null || ! preg_match('/(\d{1,3})\s*[мm](?![a-zа-я])/u', $t, $m)) {
            return null;
        }
        $length = (int) $m[1];

        return match (true) {
            $length <= 10 => 'До 10 м',
            $length <= 50 => '11–50 м',
            $length <= 100 => '51–100 м',
            $length <= 200 => '101–200 м',
            default => 'Свыше 200 м',
        };
    }

    public const PP_PIPES = 'polipropilenovye-truby';

    /** Title pattern => «Армирование» of a PP pipe; the first match wins. */
    public const PP_PIPE_REINFORCEMENT = [
        '/алюм|фольг|stabi/u' => 'Алюминием (фольгой)',
        '/стекловолок|fiber|basalt|faser/u' => 'Стекловолокном (базальтом)',
        '/^(п\/п )?труба/u' => 'Без армирования',
    ];

    public const PIPE_CLAMPS = 'krepleniya-dlya-trub';

    /** «Тип» options of «Крепления для труб», the most asked-for first. */
    public const CLAMP_ORDER = [
        'Металлические хомуты',
        'Пластиковые клипсы и опоры',
        'Хомуты-дюбели и крюки',
        'Фиксаторы поворота',
        'Червячные хомуты',
        'Хомуты-врезки для ПЭ труб',
        'Крепёж для тёплого пола',
        'Кронштейны и прочее',
    ];

    /** Title pattern => «Тип» inside «Крепления для труб»; the first match wins. */
    public const CLAMP_TYPES = [
        '/(для пэ|п\/э) труб|хомут \d+ х/u' => 'Хомуты-врезки для ПЭ труб',
        '/червячн/u' => 'Червячные хомуты',
        '/хомут-дюбель|дюбель-крюк|крюк/u' => 'Хомуты-дюбели и крюки',
        '/фиксатор поворота/u' => 'Фиксаторы поворота',
        '/теплого пола|такер|шина фиксирующ/u' => 'Крепёж для тёплого пола',
        '/клипс|пластик/u' => 'Пластиковые клипсы и опоры',
        '/хомут|ктр/u' => 'Металлические хомуты',
        '/./u' => 'Кронштейны и прочее',
    ];

    /** The pipe outside diameter a clamp takes: the top of «25-28 мм», «d20», «Ду 108 … 105-114 мм», «для труб 20 мм». */
    public static function clampPipeSize(string $title): ?string
    {
        if (self::match($title, self::CLAMP_TYPES) === 'Кронштейны и прочее') {
            return null;
        }
        $title = mb_strtolower(trim(html_entity_decode($title)));
        $mm = match (true) {
            (bool) preg_match('/(\d{2,3})\s*[-–]\s*(\d{2,3})\s*мм/u', $title, $m) => (int) $m[2],
            (bool) preg_match('/(?<![a-zа-я])d\s?(\d{2,3})(?!\d)/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(?:для труб|клипса для теплого пола)\s*(\d{2,3})\s*мм/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(?:хомут|опора\)?(?: вк)?(?: \(хомут\))?)\s+(\d{2,3})(?!\d)/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(?<![\d.,])(\d{1,3})\s*[-–]\s*(\d{1,3})(?![\d.,])/u', $title, $m) => (int) $m[2],
            ! str_starts_with($title, 'дюбель-крюк') && preg_match('/(?<![\d.,])(\d{2,3})\s*мм/u', $title, $m) => (int) $m[1],
            (bool) preg_match('/(?:ду|dn)\s?(\d{2,3})(?!\d)/u', $title, $m) => (int) $m[1],
            default => null,
        };

        return match (true) {
            $mm === null => null,
            $mm <= 20 => 'До 20 мм',
            $mm <= 32 => '21–32 мм',
            $mm <= 50 => '33–50 мм',
            $mm <= 76 => '51–76 мм',
            $mm <= 114 => '77–114 мм',
            default => 'Свыше 114 мм',
        };
    }

    public const SEALANTS = 'uplotnitelnye-materialy';

    /** «Тип» options of «Уплотнительные материалы», the most asked-for first. */
    public const SEALANT_ORDER = [
        'Прокладки для резьбовых соединений',
        'Фланцевые прокладки',
        'Лён сантехнический',
        'Пасты для льна и резьбы',
        'Уплотнительные нити',
        'Фум-лента',
        'Анаэробные герметики',
        'Наборы и ремкомплекты',
        'Паронит и техпластина (листы)',
        'Замазки, набивки, ткани и прочее',
    ];

    /** Title pattern => «Тип» inside «Уплотнительные материалы»; the first match wins. */
    public const SEALANT_TYPES = [
        '/^набор|ремонтн|набор колец/u' => 'Наборы и ремкомплекты',
        '/^паста|паста для льна/u' => 'Пасты для льна и резьбы',
        '/^л[её]н(?!т)|каболка/u' => 'Лён сантехнический',
        '/^нить/u' => 'Уплотнительные нити',
        '/фум|ptfe|tape/u' => 'Фум-лента',
        '/анаэробн|^гель/u' => 'Анаэробные герметики',
        '/^прокладк.*(фланцев|ру \d|гост 15180)/u' => 'Фланцевые прокладки',
        '/^прокладк|^кольцо/u' => 'Прокладки для резьбовых соединений',
        '/паронит|техпластин/u' => 'Паронит и техпластина (листы)',
        '/./u' => 'Замазки, набивки, ткани и прочее',
    ];

    /** Title pattern => «Материал прокладки». */
    public const GASKET_MATERIALS = [
        '/^прокладк.*паронит/u' => 'Паронит',
        '/^прокладк.*силикон/u' => 'Силикон',
        '/^прокладк.*прозрачн/u' => 'Прозрачные (полимер)',
        '/^прокладк.*резин/u' => 'Резина',
    ];

    public const CAST_PIPES = 'chugunnye-truby-i-fasonnye-chasti';

    /** Title pattern => «Тип» inside «Чугунные трубы и фасонные части», in filter order; the first match wins. */
    public const CAST_PIPE_TYPES = [
        '/^труба/u' => 'Трубы',
        '/^(отвод|колено)/u' => 'Отводы и колена',
        '/^тройник/u' => 'Тройники',
        '/^крестовин/u' => 'Крестовины',
        '/^муфт/u' => 'Муфты и муфты-фланцы',
        '/^(переход|патрубок|фланец)/u' => 'Переходы, патрубки и фланцы',
        '/^заглушк/u' => 'Заглушки',
        '/^хомут/u' => 'Ремонтные хомуты',
        '/^седло/u' => 'Сёдла (врезки)',
        '/^трап/u' => 'Трапы',
        '/./u' => 'Уплотнения и прочее',
    ];

    /** Title pattern => «Назначение»: sewer (incl. SML), pressure (flanged/socket), repair; the first match wins. */
    public const CAST_PIPE_USES = [
        '/ремонтн|универсальн|седло|для пэ и пвх/u' => 'Ремонт и соединение труб',
        '/канализац|(?<![a-z])sml(?![a-z])|^трап/u' => 'Канализация',
        '/фланц|раструб|тайтон|ру \d|надвижн|компенсацион/u' => 'Напорные трубопроводы (водопровод)',
    ];

    public const SAUNA_DOORS = 'dveri-dlya-ban-i-saun';

    /** Title pattern => «Цвет стекла» of a sauna door; the matt shades before the plain ones. */
    public const DOOR_GLASS = [
        '/бронз\w* матов/u' => 'Бронза матовая',
        '/графит\w* матов/u' => 'Графит матовый',
        '/сатин|бесцветн\w* матов|матов\w* бесцветн/u' => 'Сатин (матовое)',
        '/прозрачн|бесцветн/u' => 'Прозрачное',
        '/бронз/u' => 'Бронза',
        '/графит/u' => 'Графит',
    ];

    /** The door height ($part 0) or width ($part 1), cm, from «190 х 70», «1800*700», «190×71,5». */
    public static function doorSize(string $title, int $part): ?string
    {
        $title = mb_strtolower(trim(html_entity_decode($title)));
        if (! str_contains($title, 'дверь') || ! preg_match('/(\d{3,4})\s*[xх×*]\s*(\d{2,3})(?:[.,]\d)?/u', $title, $m)) {
            return null;
        }
        $height = (int) $m[1];
        $height = $height > 1000 ? intdiv($height, 10) : $height;
        $width = (int) $m[2];
        $width = $width > 100 ? intdiv($width, 10) : $width;
        $value = $part === 0 ? $height : (int) (round($width / 10) * 10);

        return $value.' см';
    }

    public const PIPE_INSULATION = 'teploizolyatsiya-dlya-trub';

    /** Title pattern => «Тип» inside «Теплоизоляция для труб», in filter order; the first match wins. */
    public const INSULATION_TYPES = [
        '/шумоизол|acoustic|tonlos/u' => 'Шумоизоляция канализации',
        '/k-flex/u' => 'Трубки из вспененного каучука',
        '/трубк|теплоизоляция для труб/u' => 'Трубки из вспененного полиэтилена',
        '/цилиндр|paroc/u' => 'Цилиндры из минеральной ваты',
        '/^лента/u' => 'Ленты и аксессуары',
        '/./u' => 'Прочее',
    ];

    /**
     * The pipe diameter ($part 0) or the wall thickness ($part 1) of pipe insulation: Energoflex «35/9»,
     * mineral wool «Ц100/А-1200.22.40», PAROC «48-30-115», Порифлекс «15 мм (толщина 9 мм)», ACOUSTIC «110».
     */
    public static function insulationSize(string $title, int $part): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        [$d, $w] = match (true) {
            (bool) preg_match('/-\d{3,4}\.(\d{2,3})\.(\d{2})/u', $t, $m) => [(int) $m[1], (int) $m[2]],
            (bool) preg_match('/paroc.*?(\d{2,3})-(\d{2})-\d+/u', $t, $m) => [(int) $m[1], (int) $m[2]],
            (bool) preg_match('/(?<![\d.,])(\d{2,3})\/(\d{1,2})(?![\d,.])/u', $t, $m) && str_contains($t, 'energoflex') => [(int) $m[1], (int) $m[2]],
            (bool) preg_match('/(\d{2,3})\s*мм\s*\(толщина\s*(\d{1,2})/u', $t, $m) => [(int) $m[1], (int) $m[2]],
            (bool) preg_match('/acoustic\S*\s+(\d{2,3})/u', $t, $m) => [(int) $m[1], null],
            default => [null, null],
        };
        if ($part === 1) {
            return $w !== null && in_array($w, [4, 6, 9, 13, 20, 30, 40, 50, 60], true) ? $w.' мм' : null;
        }

        return match (true) {
            $d === null => null,
            $d <= 18 => 'До 18 мм',
            $d <= 28 => '20–28 мм',
            $d <= 42 => '32–42 мм',
            $d <= 64 => '45–64 мм',
            default => '76–114 мм',
        };
    }

    public const SIPHONS = 'sifony-i-trapy';

    /** Title pattern => «Назначение» inside «Сифоны и трапы», in filter order; the first match wins. */
    public const SIPHON_USES = [
        '/умывальник/u' => 'Для умывальника',
        '/кухн|мойк/u' => 'Для мойки (кухни)',
        '/ванн/u' => 'Для ванны',
        '/душев|поддон/u' => 'Для душевого поддона',
        '/биде/u' => 'Для биде',
        '/писсуар/u' => 'Для писсуара',
        '/стиральн\w* машин/u' => 'Для стиральной машины',
        '/конденсат/u' => 'Для конденсата',
        '/желоб|трап/u' => 'Желоба и трапы',
        '/донный клапан/u' => 'Донные клапаны',
        '/./u' => 'Прочее',
    ];

    /** Title pattern => «Конструкция» of a siphon; the first match wins. */
    public const SIPHON_KINDS = [
        '/автомат|click|клик/u' => 'Автомат (click-clack)',
        '/бутыл/u' => 'Бутылочный',
        '/прямоточн/u' => 'Прямоточный',
        '/трубн|u-образн|s-образн|u-форм/u' => 'Трубный (U/S-образный)',
        '/гофр/u' => 'Гофрированный',
    ];

    public const MP_FITTINGS = 'fitingi-dlya-metalloplastikovykh-trub';

    /** Title pattern => «Способ соединения» of a fitting for multilayer pipe; the first match wins. */
    public const MP_JOINTS = [
        '/push|вставн/u' => 'Вставной (push)',
        '/обжимн|^м\/п|конусн|евроконус|для труб многослойных/u' => 'Обжимной (резьбовой)',
        '/пресс|ppsu|pexfit|press|^(тройник|отвод|муфта|заглушка)\b/u' => 'Пресс',
    ];

    /** Title pattern => «Резьба» of a fitting; plain pipe-to-pipe fittings get «Без резьбы». */
    public const MP_THREADS = [
        '/накидн|евроконус/u' => 'Накидная гайка',
        '/внутренн|(?<![а-я])вр(?![а-я])/u' => 'Внутренняя резьба',
        '/наружн|(?<![а-я])нр(?![а-я])|g\s?\d/u' => 'Наружная резьба',
        '/^(?!кольцо|элемент)/u' => 'Без резьбы',
    ];

    /** The pipe size of a fitting for multilayer pipe: «Пресс-муфта 16 арт. …», «1/2"х16х16», «20х3/4"». */
    public static function mpDiameter(string $title): ?string
    {
        if (($size = self::pressDiameter($title)) !== null) {
            return $size;
        }

        return preg_match('/(?<![\d\/.,])(16|18|20|25|26|28|32)(?![\d,.])/u', mb_strtolower($title), $m) ? $m[1].' мм' : null;
    }

    /** Title pattern => «Материал» of a fitting for multilayer pipe. */
    public const MP_MATERIALS = [
        '/ppsu/u' => 'Пластик PPSU',
        '/бронз/u' => 'Бронза',
        '/^(кольцо|элемент уплотнительный)/u' => 'Уплотнения',
        '/./u' => 'Латунь',
    ];

    public const PUMP_GROUPS = 'gruppy-bystrogo-montazha-kotelnyx';

    /** Title pattern => «Тип» inside «Гидрострелки, насосные группы быстрого монтажа»; the first match wins. */
    public const PUMP_GROUP_TYPES = [
        '/^(крепление|комплект (консолей|адаптеров)|соединитель)/u' => 'Крепления и комплектующие',
        '/группа безопасности/u' => 'Группы безопасности котла',
        '/^(гидрострелка|гидравлическ)/u' => 'Гидрострелки и гидроразделители',
        '/коллектор/u' => 'Распределительные коллекторы',
        '/насосн|групп\w* (быстрого|автономной)|насосно-смесительн/u' => 'Насосные группы',
        '/контроллер|датчик|сервопривод|сервомотор|реле/u' => 'Автоматика и приводы',
        '/клапан|смеситель/u' => 'Клапаны и смесители',
    ];

    private static function isPumpGroup(string $t): bool
    {
        return (bool) preg_match('/насосн|групп\w* (быстрого|автономной)|насосно-смесительн/u', $t) && ! str_contains($t, 'коллектор');
    }

    /** The heating circuit of a pump group: direct (UK), mixing (MK), return protection or solar. */
    public static function pumpGroupCircuit(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (! self::isPumpGroup($t)) {
            return null;
        }

        return match (true) {
            (bool) preg_match('/sol|солн|srs\d/u', $t) => 'Гелиосистема',
            (bool) preg_match('/обратн|fwr|mtre|thermix/u', $t) => 'Защита обратки',
            (bool) preg_match('/смесит|(?<![a-z])mk(?:re)?(?![a-z])|ход|термостат|сервопривод|mix|smt|kombimix|promo|pf mb|cse/u', $t) => 'Смесительный',
            (bool) preg_match('/прям|(?<![a-z])uk(?![a-z])|sa 125|fbr/u', $t) => 'Прямой',
            default => null,
        };
    }

    public static function pumpGroupPump(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (! self::isPumpGroup($t)) {
            return null;
        }

        return match (true) {
            str_contains($t, 'без насоса') => 'Без насоса',
            (bool) preg_match('/насосом|grundfos|wilo|upso?\s?\d/u', $t) => 'С насосом',
            default => null,
        };
    }

    /** The connection size of a pump group, manifold or safety group: «DN 32», «Ду 25», «1"», «3/4"». */
    public static function pumpGroupDn(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (preg_match('/(?:dn|ду)\s*-?\s*(20|25|32|40)\b/u', $t, $m)) {
            $dn = (int) $m[1];
        } elseif (preg_match('/(?<![\d\/])(1 1\/2|1 1\/4|3\/4|1)\s*(?:"|”)/u', $t, $m)) {
            $dn = ['3/4' => 20, '1' => 25, '1 1/4' => 32, '1 1/2' => 40][$m[1]];
        } else {
            return null;
        }

        return ['Ду 20 (3/4")', 'Ду 25 (1")', 'Ду 32 (1 1/4")', 'Ду 40 (1 1/2")'][[20 => 0, 25 => 1, 32 => 2, 40 => 3][$dn]];
    }

    public const SANITARY_WARE = 'santekhnicheskaya-posuda';

    /** Title pattern => «Тип» inside «Унитазы, умывальники, мойки», in filter order; the first match wins. */
    public const SANITARY_TYPES = [
        '/^унитаз-компакт/u' => 'Унитазы-компакт',
        '/^унитаз подвесн/u' => 'Подвесные унитазы',
        '/^умывальник/u' => 'Умывальники',
        '/^пьедестал/u' => 'Пьедесталы',
        '/^мойка/u' => 'Кухонные мойки',
        '/^поддон/u' => 'Душевые поддоны',
        '/^ванна/u' => 'Ванны',
        '/генуя/u' => 'Чаши «Генуя»',
        '/^писсуар/u' => 'Писсуары',
        '/^(бачок|чаша для унитаза)/u' => 'Бачки и чаши унитазов',
    ];

    /** Title pattern => «Материал» of sanitary ware; whatever is not stone, steel or cast iron is ceramic. */
    public const SANITARY_MATERIALS = [
        '/искусственного камня/u' => 'Искусственный камень',
        '/нерж/u' => 'Нержавеющая сталь',
        '/стальн/u' => 'Эмалированная сталь',
        '/чугун/u' => 'Чугун',
        '/^(унитаз|умывальник|пьедестал|писсуар|бачок|чаша для унитаза)/u' => 'Керамика',
    ];

    /** Title pattern => «Цвет мойки» of a stone kitchen sink. */
    public const SINK_COLORS = [
        '/^мойка.*бел/u' => 'Белый',
        '/^мойка.*песочн/u' => 'Песочный',
        '/^мойка.*гранит/u' => 'Гранит',
        '/^мойка.*ч[её]рн/u' => 'Чёрный',
        '/^мойка.*оникс/u' => 'Оникс',
        '/^мойка.*олово/u' => 'Олово',
        '/^мойка.*серебро/u' => 'Серебро',
    ];

    public const HYDRO_SEPARATORS = 'gidravlicheskie-razdeliteli-i-kollektory';

    /** Title pattern => «Тип» inside «Гидравлические разделители и коллекторы», in filter order; the first match wins. */
    public const HYDRO_SEPARATOR_TYPES = [
        '/консол|кронштейн для|комплект крепления|соединительный комплект|^фитинг|клапан бойлера/u' => 'Крепления и комплектующие',
        '/совмещенн|с гидравлическим разделителем|^гидроразделитель zota \d|север-м|север-компакт|гидрострелка 1", \d/u' => 'Гидрострелки с коллектором',
        '/^(oleo?-pro )?(гидравлическ\w* (стрелка|разделитель)|гидроразделитель|разделитель гидравлическ)/u' => 'Гидрострелки',
        '/коллекторная группа|расходомер|royal thermo/u' => 'Коллекторы для тёплого пола и радиаторов',
        '/коллектор/u' => 'Котельные распределительные коллекторы',
    ];

    /** The number of heating circuits of a manifold or a combined separator: «5 контуров», «7 о/к», «Север-М4», «8 вых.». */
    public static function hydroCircuits(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (self::match($t, self::HYDRO_SEPARATOR_TYPES) === 'Крепления и комплектующие') {
            return null;
        }
        if (preg_match('/(\d{1,2})\s*(?:контур|о\/к|отоп|вых)/u', $t, $m) || preg_match('/север-[мm](\d)/u', $t, $m)) {
            $n = (int) $m[1];

            return $n >= 2 && $n <= 12 ? self::circuitsLabel($n) : null;
        }

        return null;
    }

    public static function circuitsLabel(int $n): string
    {
        return $n.' '.(in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) ? 'контура' : 'контуров');
    }

    public const FIREPLACE_GRILLES = 'kaminnye-reshyotki';

    /** Title pattern => «Тип» inside «Каминные решётки», in filter order; the first match wins. */
    public const GRILLE_TYPES = [
        '/luft n[lp]|aero/u' => 'Большие панели (LUFT NL/NP, AERO)',
        '/luft/u' => 'Щелевые LUFT',
        '/oskar|oscar|venus|fresh|медн|латунь|никелир/u' => 'Декоративные',
        '/решетка каминная/u' => 'Классические',
    ];

    /** Title pattern => «Цвет» of a fireplace grille; the first match wins. */
    public const GRILLE_COLORS = [
        '/черная-латунь/u' => 'Чёрная с латунью',
        '/бел/u' => 'Белая',
        '/ч[её]рн|\bc \d|luft n[lp] .*\bc\b/u' => 'Чёрная',
        '/графит/u' => 'Графитовая',
        '/рустик/u' => 'Рустик',
        '/крем/u' => 'Кремовая',
        '/медн/u' => 'Медная',
        '/латунь/u' => 'Латунь',
        '/никел/u' => 'Никель',
    ];

    /** «Размер» options of fireplace grilles, small to large. */
    public const GRILLE_SIZES = [
        '11×11 см', '11×17 см', '17×17 см', '17×30 см', '17×37 см', '17×49 см', '22×30 см',
        '9×20 см', '9×40 см', '9×60 см', '9×80 см', '9×100 см', '12×20 см', '12×40 см', '12×80 см', '12×100 см',
        '40×60 см', '40×80 см', '55×77 см',
    ];

    /** The face size of a fireplace grille, cm: Kratki «17х30», LUFT «9х100», LUFT NL «800х400х90» mm, AERO «90*600». */
    public static function grilleSize(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (! preg_match('/(\d{1,3})\s*[xх×*]\s*(\d{2,3})/u', $t, $m)) {
            return null;
        }
        [$a, $b] = [(int) $m[1], (int) $m[2]];
        if ($a >= 90 || $b > 100) {
            [$a, $b] = [(int) round($a / 10), (int) round($b / 10)];
        }
        [$a, $b] = $a <= $b ? [$a, $b] : [$b, $a];
        $size = $a.'×'.$b.' см';

        return in_array($size, self::GRILLE_SIZES, true) ? $size : null;
    }

    public const MANHOLES = 'lyuki-i-dozhdepriemniki';

    /** Title pattern => «Тип» inside «Люки и дождеприёмники», in filter order; the first match wins. */
    public const MANHOLE_TYPES = [
        '/^люк/u' => 'Люки',
        '/^дождеприемник/u' => 'Дождеприёмники',
        '/^лоток/u' => 'Водоотводные лотки',
        '/^решетка/u' => 'Решётки',
        '/^пескоуловитель/u' => 'Пескоуловители',
        '/^(трап|слив|поддон)/u' => 'Трапы, сливы и придверные поддоны',
        '/./u' => 'Комплектующие',
    ];

    /** «Класс нагрузки» options (EN 124), light to heavy. */
    public const LOAD_CLASSES = ['А15 (до 1,5 т)', 'А30–В60 (до 6 т)', 'В125 (до 12,5 т)', 'С250 (до 25 т)', 'D400 (до 40 т)'];

    /** The load class of a manhole, gully or drain from «А15», «(С250)», «B-125», «60кН», «3 т». */
    public static function loadClass(string $title): ?string
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        if (preg_match('/(?<![а-яa-z])[абвсcdbа]-?\s?(8|10|15|30|60|125|250|400)(?!\d)/u', $t, $m)
            || preg_match('/(?<![\d,])(8|10|15|30|60|125|250|400)\s?кн/u', $t, $m)) {
            $kn = (int) $m[1];
        } elseif (preg_match('/(?<![\d,])(1,5|3)\s?т(?![а-я])/u', $t, $m)) {
            $kn = $m[1] === '3' ? 30 : 15;
        } else {
            return null;
        }

        return self::LOAD_CLASSES[match (true) {
            $kn <= 15 => 0,
            $kn <= 60 => 1,
            $kn <= 125 => 2,
            $kn <= 250 => 3,
            default => 4,
        }];
    }

    /** Title pattern => «Материал» of a manhole, gully or grate (not of the grate a plastic channel comes with). */
    public const MANHOLE_MATERIALS = [
        '/^(люк|дождеприемник|решетка).*(чугун|вч50|гост 3634)/u' => 'Чугун',
        '/^люк/u' => 'Полимерно-песчаный',
        '/^(лоток|дождеприемник|решетка|пескоуловитель|трап|слив|заглушка|корзина|надстройка|перегородка|направляющий)/u' => 'Пластик',
    ];

    public const INSTALLATIONS = 'installyatsii-i-armatura-bachkov';

    /** Title pattern => «Тип» inside «Инсталляции и арматура бачков», in filter order; the first match wins. */
    public const INSTALLATION_TYPES = [
        '/^(инсталляц|система инсталляции|скрытая система|инсталляционный сет)/u' => 'Инсталляции',
        '/^кнопка/u' => 'Кнопки смыва',
        '/^арматура наливная/u' => 'Наливная арматура',
        '/^арматура сливная/u' => 'Сливная арматура',
        '/^арматура/u' => 'Арматура бачка в комплекте',
        '/^система смыва/u' => 'Педальные системы смыва',
        '/^бачок/u' => 'Бачки',
        '/^каркас/u' => 'Каркасы душевых поддонов',
        '/хоммет/u' => 'Монтажная система ХОММЕТ',
    ];

    /** Title pattern => «Цвет кнопки» of a flush button or a cistern fitting set; the first match wins. */
    public const FLUSH_BUTTON_COLORS = [
        '/^(кнопка|система инсталляции|арматура).*(нержавеющая сталь|steel)/u' => 'Нержавеющая сталь',
        '/^(кнопка|система инсталляции|арматура).*ч[её]рн/u' => 'Чёрный',
        '/^(кнопка|система инсталляции|арматура).*(хром|кнопка хром|шток хром)/u' => 'Хром',
        '/^(кнопка|система инсталляции|арматура).*металлизир/u' => 'Металлик',
        '/^(кнопка|система инсталляции|арматура).*бел/u' => 'Белый',
    ];

    public const TOOLS = 'instrumenty-dlya-montazha';

    /** «Тип» options of «Инструменты для монтажа», in filter order. */
    public const TOOL_ORDER = [
        'Сварочные аппараты для ПП труб',
        'Насадки для сварочных аппаратов',
        'Пресс-инструмент и пресс-клещи',
        'Расширительный инструмент',
        'Труборезы и ножницы',
        'Ключи монтажные',
        'Тросы и вантузы для прочистки',
        'Припой для пайки',
        'Прочий инструмент',
    ];

    /** Title pattern => «Тип» inside «Инструменты для монтажа»; the first match wins. */
    public const TOOL_TYPES = [
        '/^насадка (для унитаза)/u' => 'Тросы и вантузы для прочистки',
        '/^насадка расширительн|расширительный инструмент/u' => 'Расширительный инструмент',
        '/^насадка/u' => 'Насадки для сварочных аппаратов',
        '/сварочн/u' => 'Сварочные аппараты для ПП труб',
        '/пресс|^varmega vm796/u' => 'Пресс-инструмент и пресс-клещи',
        '/труборез|ножницы/u' => 'Труборезы и ножницы',
        '/^щипцы/u' => 'Прочий инструмент',
        '/ключ/u' => 'Ключи монтажные',
        '/^(трос|вантуз)/u' => 'Тросы и вантузы для прочистки',
        '/олово|припой/u' => 'Припой для пайки',
        '/./u' => 'Прочий инструмент',
    ];

    public const FIREWOOD_RACKS = 'drovnitsyi';

    /** Title pattern => «Тип» inside «Дровницы и каминные принадлежности», in filter order; the first match wins. */
    public const FIREWOOD_TYPES = [
        '/^дровница/u' => 'Дровницы',
        '/биокамин/u' => 'Биокамины и остекление',
        '/шамотн|колосник|вермикулит/u' => 'Шамот, колосники и вермикулит',
        '/./u' => 'Мангалы, очаги и прочее',
    ];

    public const HOUSEHOLD_FANS = 'ventilyatory';

    /**
     * Title pattern => «Тип» inside «Вентиляторы», in filter order; the first match wins. Most cards are named by the
     * model only, so the series decides: Ballu BAF / Royal Thermo RAF exhaust, BFF / EFF floor, BFT / EFT tower,
     * Electrolux ETF table, Ballu BIF industrial.
     */
    public const HOUSEHOLD_FAN_TYPES = [
        '/^(ballu baf|royal thermo raf)/u' => 'Вытяжные для ванной и кухни',
        '/напольн|^(ballu bff|electrolux eff)/u' => 'Напольные',
        '/^(ballu bft|electrolux eft)/u' => 'Колонные',
        '/^electrolux etf/u' => 'Настольные',
        '/промышленн/u' => 'Промышленные',
        '/подпора/u' => 'Вентиляторы подпора воздуха',
        '/приточно-вытяжн/u' => 'Приточно-вытяжные установки',
    ];

    public const GRILLS = 'mangalyi';

    /** Title pattern => «Тип» inside «Мангалы», in filter order; the first match wins. */
    public const GRILL_TYPES = [
        '/^мангал-беседка/u' => 'Мангалы-беседки',
        '/^(печь|казан-мангал)/u' => 'Печи под казан',
        '/костров\w* чаш|очаг/u' => 'Костровые чаши',
        '/^(казан|жаровня)|крышка чугунная/u' => 'Казаны, крышки и жаровни',
        '/мангал/u' => 'Мангалы',
    ];

    /** Title pattern => «Конструкция» of a grill; the first match wins. */
    public const GRILL_BUILDS = [
        '/передвижн|тачанка|карета/u' => 'Передвижной (на колёсах)',
        '/складн|разборн|поход|пикник|пионер|облегченн/u' => 'Складной, переносной',
        '/^мангал/u' => 'Стационарный',
    ];

    public const DRY_SEWAGE_PUMPS = 'kanalizatsionnye-nasosy-sukhoy-ustanovki';

    /** Title pattern => «Тип» inside «Канализационные насосы сухой установки», in filter order. */
    public const DRY_SEWAGE_TYPES = [
        '/сигнализац|задвижка/u' => 'Аксессуары',
        '/^канализационный насос/u' => 'Насосы сухой установки WELLMIX',
        '/sani|citilift|насосная установка/u' => 'Канализационные установки SFA',
    ];

    /** The motor power of a WELLMIX dry-pit pump: «WL 80-10-4» = Q 80 m³/h, H 10 m, 4 kW. */
    public static function wellmixPower(string $title): ?string
    {
        if (! preg_match('/wl \d+-\d+-(\d+(?:\.\d+)?)/u', mb_strtolower($title), $m)) {
            return null;
        }
        $kw = (float) $m[1];

        return match (true) {
            $kw <= 1.5 => 'До 1,5 кВт',
            $kw <= 4 => '2,2–4 кВт',
            $kw <= 7.5 => '5,5–7,5 кВт',
            $kw <= 15 => '11–15 кВт',
            default => '18,5–22 кВт',
        };
    }

    public const CHECK_VALVES = 'obratnye-klapany';

    /** Title pattern => «Конструкция» of a check valve; the first match wins. */
    public const CHECK_VALVE_TYPES = [
        '/шаров/u' => 'Шаровой',
        '/дву[хс]?створчат/u' => 'Двустворчатый',
        '/подъ[её]мн|золотник|16кч9нж|16ч6п/u' => 'Подъёмный (золотниковый)',
        '/межфланцев|дисков|осев/u' => 'Дисковый (межфланцевый)',
        '/пружинн|(?<![а-я])пп(?![а-я])|вн\./u' => 'Пружинный',
    ];

    /** Title pattern => «Присоединение» of a check valve; the first match wins. */
    public const CHECK_VALVE_JOINTS = [
        '/межфланцев/u' => 'Межфланцевое',
        '/фланцев|двустворчат|двухстворчат/u' => 'Фланцевое',
        '/(?<![а-я])пп(?![а-я])/u' => 'Под пластиковую или пресс-трубу',
        '/вн\.|"|пружинн|ру 40 ду 15/u' => 'Внутренняя — внутренняя резьба',
    ];

    /** Title pattern => «Материал» of a check valve (the body); the first match wins. */
    public const CHECK_VALVE_MATERIALS = [
        '/нерж/u' => 'Нержавеющая сталь',
        '/чугун|19ч21бр|16кч9нж|16ч6п|шаров/u' => 'Чугун',
        '/стальн/u' => 'Сталь',
        '/(?<![а-я])пп(?![а-я])/u' => 'Полипропилен (ПП)',
        '/латун|valtec|цветлит|(?<![а-я])ld$|sti$|aquasfera|giacomini|av engineering/u' => 'Латунь',
    ];

    public const PVC_PIPES = 'truby-npvh-napornye';

    /** Title pattern => «Тип» inside «Напорные трубы НПВХ», in filter order; the first match wins. */
    public const PVC_TYPES = [
        '/^труба/u' => 'Трубы',
        '/^отвод/u' => 'Отводы',
        '/^тройник|^крестовин/u' => 'Тройники и крестовины',
        '/^патрубок/u' => 'Патрубки (раструб, фланец)',
        '/^муфта/u' => 'Муфты ремонтные',
        '/./u' => 'Редукторы и заглушки',
    ];

    public const HEATING_MATS = 'nagrevatelynye-maty';

    public const MAT_AREAS = ['До 1 м²', '1,5–2 м²', '2,5–3 м²', '3,5–5 м²', '6–8 м²', '9–12 м²', '14–15 м²'];

    /**
     * [area bucket, W/m²] of a heating mat: OneKeyElectro «OKE-1050-7,00», Теплолюкс «1080 Вт/6,0 м2», Electrolux and
     * AC Electric «2-150-6» (cores-W/m²-m²), Grand Meyer «THM200-015» (200 W/m², 1,5 m²).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function matSpec(string $title): array
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        [$area, $density] = match (true) {
            (bool) preg_match('/oke-(\d+)-(\d+(?:,\d+)?)/u', $t, $m) => [(float) str_replace(',', '.', $m[2]), null],
            (bool) preg_match('/(\d+)\s*вт\/(\d+(?:,\d+)?)\s*м2/u', $t, $m) => [(float) str_replace(',', '.', $m[2]), null],
            (bool) preg_match('/\b2-(1[56]0)-(\d+(?:,\d+)?)/u', $t, $m) => [(float) str_replace(',', '.', $m[2]), (int) $m[1]],
            (bool) preg_match('/thm(200)-(\d{3})/u', $t, $m) => [((int) $m[2]) / 10, 200],
            default => [null, null],
        };
        if ($area !== null && $density === null && isset($m[1])) {
            $density = (int) round(((int) $m[1]) / $area);
        }
        $bucket = $area === null ? null : match (true) {
            $area <= 1 => 'До 1 м²',
            $area <= 2 => '1,5–2 м²',
            $area <= 3 => '2,5–3 м²',
            $area <= 5 => '3,5–5 м²',
            $area <= 8 => '6–8 м²',
            $area <= 12 => '9–12 м²',
            default => '14–15 м²',
        };

        return [$bucket, in_array($density, [150, 160, 180, 200], true) ? $density.' Вт/м²' : null];
    }

    public const HUMIDIFIERS = 'uvlazhniteli-vozdukha';

    /**
     * Title pattern => «Тип» inside «Увлажнители воздуха», in filter order; the first match wins. Cards named by the model
     * only go by the series: Electrolux EHU / Ballu UHB / Boneco U humidifiers, Boneco H hybrids (humidifier + purifier),
     * Ballu AP purifiers, Electrolux EDH / EDM / EDL dehumidifiers.
     */
    public const HUMIDIFIER_TYPES = [
        '/смола|calcoff/u' => 'Расходники и аксессуары',
        '/приточн/u' => 'Приточные очистители (бризеры)',
        '/осушитель|electrolux ed[hml]-/u' => 'Осушители воздуха',
        '/^boneco h\d/u' => 'Климатические комплексы (увлажнение и очистка)',
        '/очиститель|^ballu ap-/u' => 'Очистители воздуха',
        '/увлажнитель|ehu-|uhb-|^boneco u\d/u' => 'Увлажнители воздуха',
    ];

    public const HEATING_CABLES = 'nagrevatelynye-kabeli';

    public const CABLE_POWERS = ['До 300 Вт', '300–600 Вт', '600–1000 Вт', '1000–1500 Вт', '1500–2000 Вт', 'Более 2000 Вт'];

    public const CABLE_LENGTHS = ['До 20 м', '20–50 м', '50–100 м', 'Более 100 м'];

    /**
     * [power bucket, length bucket] of an underfloor heating cable: OneKeyElectro «OKE-1200-86,0» (W-m), Теплолюкс
     * «101,5 м/1800 Вт», Electrolux / Royal Thermo «2-17-2000» (17 W/m, 2000 W), Grand Meyer «THC20-115» (20 W/m, 115 m).
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function cableSpec(string $title): array
    {
        $t = mb_strtolower(trim(html_entity_decode($title)));
        $num = fn (string $v): float => (float) str_replace(',', '.', $v);
        [$watts, $meters] = match (true) {
            (bool) preg_match('/oke-(\d+)-(\d+(?:,\d+)?)/u', $t, $m) => [$num($m[1]), $num($m[2])],
            (bool) preg_match('/(\d+(?:,\d+)?)\s*м\/(\d+)\s*вт/u', $t, $m) => [$num($m[2]), $num($m[1])],
            (bool) preg_match('/\b2-17-(\d+)/u', $t, $m) => [$num($m[1]), $num($m[1]) / 17],
            (bool) preg_match('/thc20-(\d+)/u', $t, $m) => [$num($m[1]) * 20, $num($m[1])],
            default => [null, null],
        };
        if ($watts === null) {
            return [null, null];
        }
        $power = match (true) {
            $watts < 300 => 'До 300 Вт',
            $watts < 600 => '300–600 Вт',
            $watts < 1000 => '600–1000 Вт',
            $watts < 1500 => '1000–1500 Вт',
            $watts <= 2000 => '1500–2000 Вт',
            default => 'Более 2000 Вт',
        };
        $length = match (true) {
            $meters <= 20 => 'До 20 м',
            $meters <= 50 => '20–50 м',
            $meters <= 100 => '50–100 м',
            default => 'Более 100 м',
        };

        return [$power, $length];
    }

    public const CORRUGATED_FITTINGS = 'fitingi-dlya-gofrirovannoj-truby';

    /** Title pattern => «Резьба» of a Stahlmann fitting: (F) female, (M) male, «15х15» pipe to pipe. */
    public const CORRUGATED_THREADS = [
        '/\(f\)|внутренн/u' => 'Внутренняя резьба',
        '/\([mм]\)/u' => 'Наружная резьба',
        '/^(муфта|тройник) stahlmann \d{2}х\d{2}(х\d{2})? /u' => 'Без резьбы (труба — труба)',
    ];

    public const GLOBE_VALVES = 'ventili-zapornye';

    public const FLANGES = 'flantsy';

    public const SHOWER_SYSTEMS = 'dushevye-sistemy';

    public const WATER_FLOOR = 'vodyanoy-teplyy-pol';

    /** Title pattern => «Тип» inside «Водяной тёплый пол», in filter order; the first match wins. */
    public const WATER_FLOOR_TYPES = [
        '/пешель|гофрированн/u' => 'Защитная гофротруба',
        '/^труба/u' => 'Трубы',
        '/мат |плита/u' => 'Маты и плиты с фиксаторами',
        '/лента|компенсационн/u' => 'Демпферная лента',
        '/фольг|фолия/u' => 'Фольга и плёнка',
        '/скоба|клипс|фиксатор|дюбель-хомут/u' => 'Крепёж трубы (скобы, клипсы)',
        '/расходомер/u' => 'Расходомеры',
        '/./u' => 'Прочее',
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

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
        '/панель изолирующая|термостат предохранительный|комплект трехходового крана с датчиком бойлера/u' => 'zapchasti-dlya-kotlov',
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

    /** Section slug => [patterns, option order] of its «Назначение» filter. */
    public static function filters(): array
    {
        return [
            self::SECTION => [self::GROUPS, self::ORDER],
            self::PARTS => [self::PART_GROUPS, self::PART_ORDER],
        ];
    }

    public static function move(string $title): ?string
    {
        return self::first(self::MOVES, $title);
    }

    public static function group(string $title, array $rules = self::GROUPS): string
    {
        return self::first($rules, $title) ?? 'Прочее';
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

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
        '/^пульт управления термофор/u' => 'aksessuary-dlya-bani',
        '/^панель управления thermex|^комплект для подключения бойлера к одноконтурному котлу thermex/u' => 'komplektuyushchie-dlya-vodonagrevateley',
    ];

    /** How the «Назначение» options are listed in the filter: the most wanted first. */
    public const ORDER = [
        'Автоматика для котлов',
        'Автоматика для насосов и клапанов',
        'Комнатные термостаты и программаторы',
        'Погодозависимая автоматика и модули котлов',
        'GSM, Wi-Fi и удалённое управление',
        'Накладные и погружные термостаты',
        'Коммутация зон и реле',
        'Управление электрокотлами и ТЭНами',
        'Регуляторы тяги',
        'Умный дом',
        'Прочее',
    ];

    /** Title pattern => «Назначение» inside «Регуляторы». */
    public const GROUPS = [
        '/регулятор тяги/u' => 'Регуляторы тяги',
        '/тэн|пуэ-|пэу-|терморегулятор для электрических котлов|пувн/u' => 'Управление электрокотлами и ТЭНами',
        '/hommyn|zigbee|умная розетка/u' => 'Умный дом',
        '/^(контроллер (котла|твердотопливного котла|пеллетной|вентилятора)|комплект наддува|командн?о-контроллер|комплект (автоматики )?zota turboset|комплект turboset|комплект «микропроцессор|контроллер euroster|регулятор оборотов вентилятора|блок управления с вентилятором|galmet luksus|базовое решение|пульт управления bfu|siemens gdb)/u' => 'Автоматика для котлов',
        '/центр коммутации|kl ?0[468]|kl 10|интегратор|kg-8\b|клеммная|реле|rx 10|координатор/u' => 'Коммутация зон и реле',
        '/погодозависим|calormatic|multimatic|vrc ?7|vrc 470|vr 6\d|vr 7[01]|vr 32|vr 92|bosch (mm|ms|mc|ipm|icm|cw|bsol)|модуль подключения|смесительный модуль|каскадный модуль|расширительный модуль|модуль расширения|модули расширения|logamatic|plum ecotouch|блок индикации|c\.mg3|для солнечных/u' => 'Погодозависимая автоматика и модули котлов',
        // «Блок автоматики» alone is the Джилекс pump control unit.
        '/насос|клапан|солнечного коллектора|pc1[12]|pl 0[67]|^блок автоматики$/u' => 'Автоматика для насосов и клапанов',
        '/gsm|wi-?fi|интернет|ethernet|econet|шлюз|\blan\b|модуль связи|дистанционн|удаленн|vr 940|vr 91|grinson|nr40/u' => 'GSM, Wi-Fi и удалённое управление',
        '/накладн|капил|погружн/u' => 'Накладные и погружные термостаты',
        '/комнатн|программатор|терморегулятор|термостат|регулятор температуры|cr10|ct100|vrt 30|poer|ester|intellicomfort|auraton/u' => 'Комнатные термостаты и программаторы',
        '/контроллер|командо|turboset|автоматика|plum|euroster|tech st|плата/u' => 'Автоматика для котлов',
    ];

    public static function move(string $title): ?string
    {
        return self::first(self::MOVES, $title);
    }

    public static function group(string $title): string
    {
        return self::first(self::GROUPS, $title) ?? 'Прочее';
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

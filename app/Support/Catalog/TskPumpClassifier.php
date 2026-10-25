<?php

namespace App\Support\Catalog;

final class TskPumpClassifier
{
    /**
     * Return the most specific public catalogue slug that can be established
     * from a TSK/Aqualider product name. Null means "do not guess".
     */
    public static function categorySlug(string $name): ?string
    {
        $value = self::normalise($name);

        if ($value === '') {
            return null;
        }

        // Water filters: «Фильтр-грязевик» is not a mud pump, «фильтрующий элемент» not an accessory.
        if (str_contains($value, 'фильтр') && ! str_contains($value, 'насос')) {
            return 'filtry';
        }

        // Spare parts of a pump («Рабочий винт в обойме для ECO VINT», «Ротор JET 60 S»).
        if (self::containsAny($value, ['рабочий винт', 'рабочее колесо', 'ремкомплект', 'корпус насоса', 'трубка вентури', 'уплотнительное кольцо', 'запчаст'])
            || preg_match('/(?:^|\s)(?:ротор|статор|конденсатор|диффузор)\s/u', $value)) {
            return 'komplektuyushchie-dlya-nasosov';
        }

        if (self::containsAny($value, [
            'блок управления', 'манометр', 'реле давления', 'датчик сухого хода',
            'рукав напорный', 'муфта для шланга', 'соединительная муфта',
            'автоматическая трубная муфта', 'соединитель камлок', 'хомут для шланга',
            'скважинный адаптер', 'крышка скважины', 'оголовок скважинный',
            'зажим для троса', 'трос из нержавеющей', 'трехвыводной штуцер',
            'пятивыводной штуцер', 'обратный клапан', 'кабель для насоса',
            'кронштейн', 'гибкий шланг', 'кабель круглый',
            'винтовое соединение', 'кабель квв', 'кабель плоский', 'трос ', 'соединитель пластиков',
            'тройник', 'шланг поливочн', 'частотный преобразовател',
        ])) {
            return 'komplektuyushchie-dlya-nasosov';
        }

        // A spare membrane, not a hydroaccumulator that names its EPDM membrane.
        if (str_contains($value, 'мембрана') && ! self::containsAny($value, ['гидроаккум', 'бак '])) {
            return 'komplektuyushchie-dlya-nasosov';
        }

        // Tanks are present in the same price book, but are not pumps.
        if (self::containsAny($value, ['гидроаккум', 'мембранн', 'расширительн'])) {
            return 'membrannyie-baki';
        }

        // Industrial pumps (Wellmix, UNIPUMP MVH). A household «поверхностный»
        // multistage or end-suction pump stays with the surface pumps.
        if (str_contains($value, 'сухой установки')) {
            return 'kanalizatsionnye-nasosy-sukhoy-ustanovki';
        }
        if (preg_match('/(?:^|\s)(?:td|tg)\s*\d/u', $value)) {
            return 'in-lain-nasosy';
        }
        if (! self::containsAny($value, ['поверхност', 'скважин', 'погружн'])) {
            if (str_contains($value, 'многоступенчат') || preg_match('/(?:^|\s)(?:cmi|cuc|cvf?)\s*\d/u', $value)) {
                return 'mnogostupenchatye-nasosy';
            }
            if (self::containsAny($value, ['моноблочн', 'консольн'])) {
                return 'konsolnye-i-monoblochnye-nasosy';
            }
        }

        // Drainage/sewage submersibles must be checked before the generic
        // "погружной" rule used for borehole pumps.
        if (self::containsAny($value, [
            'дренаж', 'фекал', 'канализац', 'грязев', 'fekamax', 'fekapump', 'fekacut', 'шламов',
            'sanivort', 'wq ', 'wq-', 'wqd', 'wqm',
        ])) {
            return 'drenajnyie';
        }

        if (self::containsAny($value, [
            'циркуляц', 'циркул', ' nmt', 'nmt ', ' upc', 'upc ',
            ' lpa', 'lpa ',
        ]) || preg_match('/(?:^|\s)(?:wrs|wre|ups)(?:\s|-|\d|$)/u', $value)) {
            return 'tsirkulyatsionnyie';
        }

        // PSB is a surface multistage pump with a frequency converter. WP is
        // a motor pump; KOTLOV currently groups motor pumps with surface pumps.
        if (self::containsAny($value, [
            'поверхност', 'мотопомп', 'самовсас', 'частотным преобразовател',
            'частотным регулятор', 'центробежный насос', 'насос вихревой',
        ]) || preg_match('/\b(?:psb|wp)[\s_-]*\d/i', $value)) {
            return 'poverhnostnyie';
        }

        if (self::containsAny($value, [
            'насосная станция', 'автоматическая станция', 'станция водоснабжения',
            'гидрофор', 'акваробот',
        ])) {
            return 'nasosnyie-stantsii';
        }

        if (self::containsAny($value, [
            'скважин', 'глубин', 'водомет', 'эцв', 'eco vint', 'eco maxi',
            'винтовой насос', 'погружной насос для чистой воды',
        ]) || preg_match('/\b(?:3stm|4stm|3sdm|4sdm|4skm)\b/i', $value)) {
            return 'skvajinnye-nasosy';
        }

        // Model-only rows of the ТСК price, when no word above named the type («ECO 2-73 (кабель - 40 м)», «AUTO JET 60 S»,
        // «CP 25-60 180», «NMTD MAX II U 50/80»).
        if (str_contains($value, 'станция авт') || preg_match('/(?:^|\s)(?:auto\s+(?:jet|js|qb|eco|dp)|aups)/u', $value)) {
            return 'nasosnyie-stantsii';
        }
        if (preg_match('/(?:^|\s)(?:upc3?|ups|lpa|cp|nmtd?|ghn)[\s-]*\d/u', $value) || preg_match('/(?:^|\s)(?:upс|nmtd?\s+(?:max|smart|plus|eco))/u', $value)) {
            return 'tsirkulyatsionnyie';
        }
        if (preg_match('/(?:^|\s)(?:eco\s+jet|jet|js|qb|cpm|pkm|mhi?|мн)[\s-]*\d/u', $value)) {
            return 'poverhnostnyie';
        }
        if (preg_match('/(?:^|\s)(?:mini\s+|slim\s+|rus\s+|midi\s+)?eco(?:\s+(?:slim|rus|midi|maxi|vint))?[\s-]*\d/u', $value)) {
            return 'skvajinnye-nasosy';
        }

        return null;
    }

    public static function typeLabel(string $name): ?string
    {
        return match (self::categorySlug($name)) {
            'komplektuyushchie-dlya-nasosov' => 'Комплектующие для насосов',
            'membrannyie-baki' => 'Мембранный бак',
            'drenajnyie' => 'Дренажный или канализационный насос',
            'tsirkulyatsionnyie' => 'Циркуляционный насос',
            'poverhnostnyie' => preg_match('/\bwp[\s_-]*\d/i', self::normalise($name))
                ? 'Мотопомпа'
                : 'Поверхностный насос',
            'nasosnyie-stantsii' => 'Насосная станция',
            'skvajinnye-nasosy' => 'Скважинный насос',
            'mnogostupenchatye-nasosy' => 'Многоступенчатый насос',
            'konsolnye-i-monoblochnye-nasosy' => 'Консольный или моноблочный насос',
            'in-lain-nasosy' => 'Ин-лайн насос',
            'kanalizatsionnye-nasosy-sukhoy-ustanovki' => 'Канализационный насос сухой установки',
            default => null,
        };
    }

    private static function normalise(string $value): string
    {
        $value = mb_strtolower(str_replace(["\u{00A0}", 'ё'], [' ', 'е'], trim($value)));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    /** @param string[] $needles */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

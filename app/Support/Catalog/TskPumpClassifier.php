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

        if (self::containsAny($value, [
            'блок управления', 'манометр', 'реле давления', 'датчик сухого хода',
            'рукав напорный', 'муфта для шланга', 'соединительная муфта',
            'автоматическая трубная муфта', 'соединитель камлок', 'хомут для шланга',
            'скважинный адаптер', 'крышка скважины', 'оголовок скважинный',
            'зажим для троса', 'трос из нержавеющей', 'трехвыводной штуцер',
            'пятивыводной штуцер', 'обратный клапан', 'кабель для насоса',
        ])) {
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
        if (! str_contains($value, 'поверхност')) {
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
            'дренаж', 'фекал', 'канализац', 'грязев', 'fekamax',
            'sanivort', 'wq ', 'wq-', 'wqd', 'wqm',
        ])) {
            return 'drenajnyie';
        }

        if (self::containsAny($value, [
            'циркуляц', 'циркул', ' nmt', 'nmt ', ' upc', 'upc ',
            ' lpa', 'lpa ', 'ups ', 'ups-', 'ups25', 'ups32',
        ]) || preg_match('/(?:^|\s)(?:wrs|wre)(?:\s|-|$)/u', $value)) {
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

<?php

namespace App\Services\Suppliers;

/** Product purpose wins over the supplier's broad brand/price-list groups. */
final class TdKomplektPlumbingCategories
{
    public const CATEGORIES = [
        'shlangi-i-soediniteli' => ['Шланги и соединители', 'santehnika', 'Сливные и заливные шланги для подключения техники, соединители и переходники.'],
        'dushevye-sistemy' => ['Душевые системы и стойки', 'santehnika', 'Душевые системы, стойки и комплекты для ванной комнаты.'],
        'vodorazbornye-krany' => ['Водоразборные и установочные краны', 'krany-i-zapornaya-armatura', 'Краны для подключения техники, отбора воды и садового полива.'],
        'raspredelitelnye-kollektory' => ['Распределительные коллекторы', 'komplektuyushhie-dlya-otopleniya', 'Коллекторы с разным количеством выходов для распределения воды и теплоносителя.'],
        'komplektuyushchie-kollektorov' => ['Комплектующие коллекторов', 'komplektuyushhie-dlya-otopleniya', 'Расходомеры, крепления и сменные детали коллекторных узлов. Совместимость проверяется по модели.'],
        'instrument-dlya-prochistki-trub' => ['Тросы и инструмент для прочистки труб', 'montazhnye-materialy', 'Сантехнические тросы, вантузы и инструмент для устранения засоров.'],
        'fitingi-dlya-mednyh-trub' => ['Фитинги для медных труб', 'truby-i-fitingi', 'Соединительные детали для медных труб. Выбирайте размер и тип соединения.'],
        'gazovye-filtry' => ['Газовые фильтры', 'gazovoe-oborudovanie', 'Фильтры для газопроводов. Назначение и допустимые параметры проверяются по документации изделия.'],
        'aksessuary-dlya-vannoy' => ['Аксессуары для ванной', 'santehnika', 'Полки, коврики и аксессуары для обустройства ванной комнаты.'],
    ];

    public static function category(array $row): ?string
    {
        $name = mb_strtolower(trim($row['name'] ?? ''));
        $group = mb_strtolower(implode(' ', array_map(fn ($i) => $row['parentid_name'.$i] ?? '', range(1, 4))));
        if (! str_contains($group, 'сантехника') || str_contains($group, 'распродажа')) {
            return null;
        }
        if (preg_match('/^(?:душевая (?:термостатическая )?(?:система|стойка)|душевой (?:гарнитур|комплект))/u', $name)) {
            return 'dushevye-sistemy';
        }
        if (str_contains($group, 'шланги для стиральных машин') || str_contains($group, 'шланги дренажные')) {
            return 'shlangi-i-soediniteli';
        }
        if (str_contains($group, 'прочистки труб') || preg_match('/^(?:трос сантехнический|вантуз)\b/u', $name)) {
            return 'instrument-dlya-prochistki-trub';
        }
        if (str_contains($name, 'для медных труб')) {
            return 'fitingi-dlya-mednyh-trub';
        }
        if (preg_match('/^фильтр\b/u', $name)) {
            return str_contains($group, 'газоснабжение') || str_contains($name, '(газ)') ? 'gazovye-filtry' : 'filtry';
        }
        if (preg_match('/^(?:сетка-фильтр|вентильная головка|эксцентрик|автоматический ополаскиватель|комплект монтажный км-|узел присоединительный)/u', $name)) {
            return 'komplektuyushchie-dlya-santekhniki';
        }
        if (str_contains($name, 'для радиатора') || str_contains($name, 'кран маевского') || str_starts_with($name, 'термостатическая головка')) {
            return 'radiatornaya-armatura';
        }
        if (preg_match('/^(?:развоздушник|воздухоотводчик|предохранительный клапан|группа безопасности|редуктор давления|гаситель гидроудара)/u', $name)) {
            return 'predokhranitelnaya-i-reguliruyushchaya-armatura';
        }
        if (preg_match('/^(?:термостатический смесительный|трехходовой смесительный|трёхходовой смесительный|насосно-смесительная)/u', $name)) {
            return 'smesitelnye-klapany-i-uzly';
        }
        if (preg_match('/^(?:манометр|термометр)\b/u', $name)) {
            return 'manometry-i-termometry';
        }
        if (preg_match('/^гидрострелка\b/u', $name)) {
            return 'gidravlicheskie-razdeliteli-i-kollektory';
        }
        if (preg_match('/^коллектор\b/u', $name)) {
            return 'raspredelitelnye-kollektory';
        }
        if (str_contains($group, 'коллекторные группы: комплектующие')) {
            return str_starts_with($name, 'термостат') ? 'regulyatory' : 'komplektuyushchie-kollektorov';
        }
        if (preg_match('/^кран (?:водоразборный|садовый|для фильтра|вентильный)/u', $name)) {
            return 'vodorazbornye-krany';
        }
        if (preg_match('/^моно-смеситель\b/u', $name)) {
            return 'smesiteli';
        }
        if (str_contains($group, 'трубы для отопления и теплого пола')) {
            return str_contains($name, 'металлопласт') ? 'metalloplastikovye-truby' : 'truby-dlya-teplogo-vodyanogo-pola';
        }
        if (str_contains($group, 'расходные материалы')) {
            if (preg_match('/(?:паста|пакля|лён|лен\b|фум|нить)/u', $name)) {
                return 'uplotnitelnye-materialy';
            }
            if (preg_match('/^(?:смазка|герметик|фибра)\b/u', $name)) {
                return 'peny-germetiki-i-khimiya';
            }
        }
        if (str_contains($group, 'резьбовые') && preg_match('/^(?:колено|соединение|врезка)\b/u', $name)) {
            return 'rezbovye-fitingi';
        }
        if (str_contains($group, 'резьбовые') && preg_match('/^вентиль\b/u', $name)) {
            return 'ventili-zapornye';
        }

        return null;
    }
}

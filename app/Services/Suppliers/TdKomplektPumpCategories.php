<?php

namespace App\Services\Suppliers;

/** Source sections contain spare parts and occasional misplaced pumps. */
final class TdKomplektPumpCategories
{
    public static function tankModelKey(string $name, string $article = ''): ?string
    {
        if (! preg_match('/(?:^|\s)(?:бак|гидроаккумулятор)(?:\s|$)/ui', $name)
            || preg_match('/^(?:мембрана|баллон|комплект|кронштейн|фланец|ремкомплект)\b/ui', $name)) {
            return null;
        }
        $value = preg_match('/^(?:WRV|WAO|WAV|WDV)[ -]*\d+(?:[ -]*P)?$/i', $article) ? $article : $name;
        if (! preg_match('/(?<![a-z0-9])(WRV|WAO|WAV|WDV)[ -]*(\d+)(?:[ -]*(P))?(?![a-z0-9])/i', $value, $m)) {
            return null;
        }
        $suffix = ! empty($m[3]) || (strtoupper($m[1]) === 'WDV' && stripos($name, 'Premium') !== false) ? 'P' : '';

        return strtoupper($m[1]).$m[2].$suffix;
    }

    public static function applies(array $row): bool
    {
        return str_contains(mb_strtolower($row['parentid_name1'] ?? ''), 'насосы и насосное оборудование');
    }

    public static function category(array $row): ?string
    {
        $section = mb_strtolower(implode(' ', array_map(fn ($i) => $row['parentid_name'.$i] ?? '', [2, 3, 4])));
        $name = mb_strtolower(trim($row['name']));
        $description = mb_strtolower(strip_tags($row['description'] ?? ''));
        if (str_contains($section, 'распродажа') || str_contains($section, 'чистка прайса')) {
            return null;
        }

        // A membrane or mounting kit is not a tank; a bladder is not a water station.
        if (str_contains($section, 'товары сопутствующие')
            || preg_match('/^(?:баллон|мембрана|ремкомплект|кронштейн|прессостат|реле|коробка управления|датчик|поплавковый выключатель|блок автоматики|гайка|комплект (?:для|гаек))\b/u', $name)) {
            return 'komplektuyushchie-dlya-nasosov';
        }
        if (str_contains($section, 'баки мембранные') && preg_match('/^бак мембранный\b/u', $name)) {
            return 'membrannye-baki';
        }

        // Wilo PW-175EA (3059260) is an automatic pressure boosting unit, not circulation.
        if (str_contains($name, 'повышения давления') && str_contains($name, 'насос')) {
            return 'nasosy-povysheniya-davleniya';
        }
        if (str_contains($name, 'канализационн') && str_contains($name, 'установк')) {
            return 'kanalizatsionnye-nasosy';
        }
        if (str_contains($name, 'насос') && str_contains($name, 'фекаль')) {
            return 'fekalnye-nasosy';
        }
        if (str_starts_with($name, 'станция водоснабжения')) {
            return 'nasosnyie-stantsii';
        }
        if (! str_starts_with($name, 'насос')) {
            return null;
        }

        // An explicit type in the item title takes precedence over a broad source section.
        if (str_contains($name, 'скважин')) {
            return 'skvajinnye-nasosy';
        }
        if (str_contains($name, 'циркуляцион')) {
            return 'tsirkulyatsionnyie';
        }
        if (str_contains($name, 'вибрацион')) {
            return 'nasosy-dlya-kolodtsa';
        }
        if (str_contains($name, 'садовый')) {
            return 'poverhnostnyie';
        }
        if (str_contains($name, 'дренаж')) {
            return 'drenajnyie';
        }

        if (str_contains($section, 'насосы циркуляционные')) {
            return 'tsirkulyatsionnyie';
        }
        if (str_contains($section, 'насосы скважинные')) {
            return 'skvajinnye-nasosy';
        }
        if (str_contains($section, 'насосы садовые')) {
            return 'poverhnostnyie';
        }
        if (str_contains($section, 'насосы вибрационные') && str_contains($name, 'вибрацион')) {
            return 'nasosy-dlya-kolodtsa';
        }
        if (str_contains($section, 'насосы погружные')) {
            // A multistage clean-water pump with a float is a well/reservoir pump,
            // unlike the portable drainage pumps grouped beside it by this supplier.
            if (str_contains($description, 'многоступенчат') && str_contains($description, 'поплавков')) {
                return 'nasosy-dlya-kolodtsa';
            }
            if (str_contains($description, 'поплавков') && ! str_contains($description, 'мин. диаметр скважины')) {
                return 'drenajnyie';
            }
        }

        return null;
    }
}

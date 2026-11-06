<?php

namespace App\Services\Suppliers;

/**
 * The S-TANK retail price list in BYN (database/data/stank_price_2026-10.json, made from
 * «Прайс S-TANK октябрь бел.руб. 2026г РБ РРЦ.xlsx»): one row a model with its key («BER2-200», «SSHP-300»,
 * «AT-500» for «TA90/TA line 500»), price, specs. Where the list gives a magnesium and a titanium anode price,
 * «price» is the magnesium one, but for SOLAR SS, which the site sells with the titanium anode.
 */
class StankPriceList
{
    public const FILE = 'data/stank_price_2026-10.json';

    /** Series, the longer names first: [pattern, key, shown name]. */
    private const SERIES = [
        ['SOLAR\s*SS\s*DUO', 'SOLARSSDUO', 'SOLAR SS DUO'], ['SS\s*-?\s*HP', 'SSHP', 'SS-HP'], ['SOLAR\s*SS', 'SOLARSS', 'SOLAR SS'],
        ['SS\s*ETRO\s*MONO', 'SSETROMONO', 'SS ETRO MONO'], ['SS\s*ETRO', 'SSETRO', 'SS ETRO'],
        ['AT\s*ETRO\s*MONO', 'ATETROMONO', 'AT ETRO MONO'], ['AT\s*ETRO', 'ATETRO', 'AT ETRO'],
        ['SS\s*CT', 'SSCT', 'SS CT'], ['\bCT', 'CT', 'CT'], ['HFWT\s*DUO', 'HFWTDUO', 'HFWT DUO'], ['HFWT', 'HFWT', 'HFWT'],
        ['FRESH', 'FRESH', 'FRESH'], ['BER\s*2', 'BER2', 'BER2'], ['BER', 'BER', 'BER'], ['[PР]\s*TURBO', 'PTURBO', 'P Turbo'],
        ['P\s*EASY', 'PEASY', 'P Easy'], ['\bPW', 'PW', 'PW'], ['FLAT', 'FLAT', 'FLAT'], ['TA90|TA\s*LINE|\bAT\b', 'AT', 'TA line'],
        ['\bET\b', 'ET', 'ET'], ['\bSS\b', 'SS', 'SS'],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $rows = null;

    /** «Бак косвенного нагрева S-TANK BER2-200», «TA90/TA line 500», «Solar SS 150» → «BER2-200», «AT-500», «SOLARSS-150». */
    public static function key(string $name): ?string
    {
        $name = str_ireplace('S-TANK', ' ', $name);
        foreach (self::SERIES as [$pattern, $key]) {
            if (preg_match('/(?:'.$pattern.')[^0-9]{0,12}?(\d{2,4})(?!\d)/iu', $name, $m)) {
                return $key.'-'.$m[1];
            }
        }

        return null;
    }

    /** «SSHP-300» → «SSHP». */
    public static function series(string $key): string
    {
        return explode('-', $key, 2)[0];
    }

    /** @return array<string, array<string, mixed>> key => row */
    public static function rows(): array
    {
        if (self::$rows === null) {
            $data = json_decode((string) file_get_contents(database_path(self::FILE)), true) ?: [];
            self::$rows = collect($data['rows'] ?? [])->keyBy('key')->all();
        }

        return self::$rows;
    }

    /** @return array<string, mixed>|null */
    public static function row(string $key): ?array
    {
        return self::rows()[$key] ?? null;
    }

    /** The retail price of a model; null for «по запросу» and for a model the list does not have. */
    public static function price(string $key): ?float
    {
        $price = self::row($key)['price'] ?? null;

        return $price !== null ? (float) $price : null;
    }
}

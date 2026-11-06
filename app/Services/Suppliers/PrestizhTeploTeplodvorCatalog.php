<?php

namespace App\Services\Suppliers;

/**
 * The teplodvor.by pages of the brands «Престиж Тепло» sells (Vaillant, Protherm, Kospel, Drazice, Reflex, Flamco,
 * Afriso, Meibes, Aquastic, Galmet, Huch, Styleboiler): a page is found by the brand and model of its title
 * («Газовый котел Vaillant turboTEC pro VUW 242/5-3» → «vaillant|242 3 5 pro turbotec vuw»).
 */
class PrestizhTeploTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'vaillant|protherm|kospel|drazice|reflex|flamco|afriso|meibes|aquastic|galmet|huch|styleboiler';

    public static function brandIn(string $name): string
    {
        return preg_match('/\b('.static::BRAND.')\b/iu', $name, $m) ? mb_strtolower($m[1]) : '';
    }

    protected static function matchKey(string $name): string
    {
        $brand = self::brandIn($name);
        $key = PrestizhTeploPriceList::modelKey($name);

        return $brand !== '' && $key !== '' ? $brand.'|'.$key : '';
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }
}

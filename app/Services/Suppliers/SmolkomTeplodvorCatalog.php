<?php

namespace App\Services\Suppliers;

/**
 * The Dimplex, Royal Flame, Real Flame and Smolcom goods of teplodvor.by for the cards made from the Смолком
 * price list: a page is found by the brand and the same model (SmolkomPriceList::modelKey of its title).
 */
class SmolkomTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'dimplex|royal-flame|royalflame|real-flame|realflame|smolcom';

    protected static function matchKey(string $name): string
    {
        $brand = self::brandOf($name);
        $key = SmolkomPriceList::modelKey($name);

        return $brand !== '' && $key !== '' ? $brand.'|'.$key : '';
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }

    public static function brandOf(string $name): string
    {
        $name = mb_strtolower($name);

        return match (true) {
            str_contains($name, 'dimplex') || str_contains($name, 'димплекс') => 'dimplex',
            (bool) preg_match('/royal\s*flame/u', $name) => 'royalflame',
            (bool) preg_match('/real\s*flame/u', $name) => 'realflame',
            str_contains($name, 'smolcom') || str_contains($name, 'смолком') => 'smolcom',
            default => '',
        };
    }
}

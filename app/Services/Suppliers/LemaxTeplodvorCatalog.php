<?php

namespace App\Services\Suppliers;

/**
 * The LEMAX (Лемакс) goods of teplodvor.by for the cards made from the ТеплоТоп-Бел price lists: a page is found
 * by the same model (TeplotopPriceList::modelKey of its title).
 */
class LemaxTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'lemaks|lemax';

    protected static function matchKey(string $name): string
    {
        return TeplotopPriceList::modelKey($name);
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }
}

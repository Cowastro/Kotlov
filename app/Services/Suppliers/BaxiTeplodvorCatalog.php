<?php

namespace App\Services\Suppliers;

/**
 * The BAXI, Viessmann, Viena and Reflex goods of teplodvor.by for the cards made from the VISTAR price list: a page
 * is found by the same model (VistarPriceList::modelKey of its title).
 */
class BaxiTeplodvorCatalog extends FirewayTeplodvorCatalog
{
    protected const BRAND = 'baxi|viessmann|viena|reflex';

    protected static function matchKey(string $name): string
    {
        return VistarPriceList::modelKey($name);
    }

    protected function titleNames(string $title): array
    {
        return [$title];
    }
}

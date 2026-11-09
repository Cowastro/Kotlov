<?php

namespace App\Support\Catalog;

/**
 * Products we promote right now: they open their catalogue sections (default sort only), get a highlighted
 * card with the gift badge and the gift note in the product page. The cast-iron ЭкоКамин PRIMO 700 is sold
 * with a barbecue grill grate as a gift (the manufacturer's offer, ecokamin.ru).
 */
final class SpotlightProducts
{
    public const PRIMO_700 = 'topka-cugunnaia-primo-700';

    /** slug => gift that comes with the product. */
    private const GIFTS = [
        self::PRIMO_700 => 'Решётка-гриль в подарок',
    ];

    /** @return list<string> */
    public static function slugs(): array
    {
        return array_keys(self::GIFTS);
    }

    public static function gift(?string $slug): ?string
    {
        return $slug !== null ? (self::GIFTS[$slug] ?? null) : null;
    }
}

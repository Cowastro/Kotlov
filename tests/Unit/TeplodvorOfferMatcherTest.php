<?php

namespace Tests\Unit;

use App\Services\Suppliers\TeplodvorOfferMatcher;
use Tests\TestCase;

class TeplodvorOfferMatcherTest extends TestCase
{
    public function test_it_prefers_an_exact_supplier_article(): void
    {
        $match = app(TeplodvorOfferMatcher::class)->match([
            'article' => 'Deluxe ONE 24K',
            'name' => 'Котел газовый Navien Deluxe ONE 24K',
            'brand' => 'Navien',
        ], [
            'gazovyj-kotel-navien-deluxe-one-30k' => 'https://www.teplodvor.by/30k',
            'gazovyj-kotel-navien-deluxe-one-24k' => 'https://www.teplodvor.by/24k',
        ]);

        self::assertSame('https://www.teplodvor.by/24k', $match['url']);
        self::assertSame('article', $match['method']);
    }

    public function test_it_rejects_a_different_numeric_modification(): void
    {
        $match = app(TeplodvorOfferMatcher::class)->match([
            'article' => '',
            'name' => 'Котел Navien Deluxe ONE 24K',
            'brand' => 'Navien',
        ], [
            'gazovyj-kotel-navien-deluxe-one-30k' => 'https://www.teplodvor.by/30k',
        ]);

        self::assertNull($match['url']);
    }
}

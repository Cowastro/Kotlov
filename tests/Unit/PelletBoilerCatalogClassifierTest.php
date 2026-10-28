<?php

namespace Tests\Unit;

use App\Services\PelletBoilerCatalogClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PelletBoilerCatalogClassifierTest extends TestCase
{
    #[DataProvider('boilers')]
    public function test_it_recognizes_explicit_pellet_boilers(string $name): void
    {
        $this->assertTrue((new PelletBoilerCatalogClassifier)->isPelletBoiler($name));
    }

    public static function boilers(): array
    {
        return [
            ['Пеллетный котел TIS Pellet 15'],
            ['Котёл пеллетный автоматический 25 кВт'],
            ['Котел на пеллетах Heiztechnik 40'],
            ['Пеллетный котел Sakovich Pellet Universal 20 с горелкой KIPI Rotary и бункером'],
            ['Пеллетный котел Sakovich Pellet Standart 15 с горелкой Praktik, шнеком и бункером'],
        ];
    }

    #[DataProvider('nonBoilers')]
    public function test_it_rejects_burners_and_accessories(string $name): void
    {
        $this->assertFalse((new PelletBoilerCatalogClassifier)->isPelletBoiler($name));
    }

    public static function nonBoilers(): array
    {
        return [
            ['Пеллетная горелка KOTLOV XO EVO 26'],
            ['Бункер для пеллет 500 л'],
            ['Шнек подачи пеллет'],
            ['Комплект автоматики пеллетного котла'],
            ['Твердотопливный котел 20 кВт'],
        ];
    }
}

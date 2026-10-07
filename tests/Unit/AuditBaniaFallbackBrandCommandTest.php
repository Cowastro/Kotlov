<?php

namespace Tests\Unit;

use App\Console\Commands\AuditBaniaFallbackBrandCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AuditBaniaFallbackBrandCommandTest extends TestCase
{
    public function test_it_detects_prosept_as_the_real_brand(): void
    {
        $command = new AuditBaniaFallbackBrandCommand();
        $method = new ReflectionMethod($command, 'suggestBrand');
        $row = (object) [
            'name' => 'Антисептик для внутренних работ PROSEPT SAUNA концентрат 1:10 / 1 л',
            'supplier_name' => '',
            'source_url' => 'https://prosept.ru/catalog/antiseptiki-i-zashitnye-sostavy/prosept-sauna/',
        ];

        self::assertSame([
            'name' => 'PROSEPT',
            'slug' => 'prosept',
            'reason' => 'brand matched by "prosept"',
        ], $method->invoke($command, $row));
    }
}

<?php

namespace Tests\Unit;

use App\Console\Commands\SyncTskNasosyCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

class SyncTskNasosyCommandTest extends TestCase
{
    #[DataProvider('availabilityProvider')]
    public function test_it_maps_supplier_stock_to_public_availability(string $supplierStatus, string $expected): void
    {
        $command = new SyncTskNasosyCommand();
        $method = (new ReflectionClass($command))->getMethod('productAvailability');

        $this->assertSame($expected, $method->invoke($command, $supplierStatus));
    }

    public static function availabilityProvider(): array
    {
        return [
            ['in_stock', 'in_stock'],
            ['out_of_stock', 'out_of_stock'],
            ['preorder', 'check'],
            ['unknown', 'check'],
        ];
    }
}

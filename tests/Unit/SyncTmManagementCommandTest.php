<?php

namespace Tests\Unit;

use App\Console\Commands\SyncTmManagementCommand;
use ReflectionClass;
use Tests\TestCase;

class SyncTmManagementCommandTest extends TestCase
{
    public function test_it_never_uses_wholesale_as_a_missing_retail_price(): void
    {
        $this->assertNull($this->invoke('safeRetailPrice', [null, 345.0]));
        $this->assertNull($this->invoke('safeRetailPrice', [300.0, 345.0]));
        $this->assertSame(345.0, $this->invoke('safeRetailPrice', [345.0, 345.0]));
        $this->assertSame(430.0, $this->invoke('safeRetailPrice', [430.0, 345.0]));
    }

    public function test_it_reads_explicit_presence_without_inventing_a_quantity(): void
    {
        $this->assertSame([
            'in_stock' => true,
            'status' => 'available_without_quantity',
            'text' => 'на складе',
        ], $this->invoke('availabilityFromText', ['на складе']));

        $this->assertSame([
            'in_stock' => false,
            'status' => 'unknown',
            'text' => 'Остаток в прайсе не указан',
        ], $this->invoke('availabilityFromText', ['']));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        $command = new SyncTmManagementCommand();
        $reflection = new ReflectionClass($command);

        return $reflection->getMethod($method)->invokeArgs($command, $arguments);
    }
}

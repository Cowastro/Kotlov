<?php

namespace Tests\Unit;

use App\Console\Commands\RepairVarmegaSourceUrlsCommand;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RepairVarmegaSourceUrlsCommandTest extends TestCase
{
    public function test_official_only_mode_rejects_third_party_sources(): void
    {
        $method = new ReflectionMethod(RepairVarmegaSourceUrlsCommand::class, 'sourceIsAllowed');
        $command = new RepairVarmegaSourceUrlsCommand();

        $this->assertTrue($method->invoke($command, [
            'url' => 'https://varmega.ru/product/truby-i-fitingi/example/',
        ], true));
        $this->assertTrue($method->invoke($command, [
            'url' => 'https://www.varmega.ru/product/truby-i-fitingi/example/',
        ], true));
        $this->assertFalse($method->invoke($command, [
            'url' => 'https://termogorod.ru/truby-i-fitingi/example',
        ], true));
        $this->assertFalse($method->invoke($command, [
            'url' => 'https://rn-profi.by/example',
        ], true));
        $this->assertTrue($method->invoke($command, [
            'url' => 'https://rn-profi.by/example',
        ], false));
    }
}

<?php

namespace Tests\Unit;

use App\Console\Commands\AuditPhpSecurityCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AuditPhpSecurityCommandTest extends TestCase
{
    public function test_patched_php_thresholds_match_the_security_advisory(): void
    {
        $property = (new ReflectionClass(AuditPhpSecurityCommand::class))->getConstant('PATCHED');

        $this->assertSame([
            802 => 80235,
            803 => 80336,
            804 => 80427,
            805 => 80512,
        ], $property);
    }
}

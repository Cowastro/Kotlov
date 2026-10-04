<?php

namespace Tests\Unit;

use App\Console\Commands\NormalizeStoveHeatingAreasCommand;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class NormalizeStoveHeatingAreasCommandTest extends TestCase
{
    public function test_scope_includes_the_actual_heating_stove_product_category(): void
    {
        $categories = (new ReflectionClass(NormalizeStoveHeatingAreasCommand::class))
            ->getConstant('CATEGORY_SLUGS');

        $this->assertContains('pechi', $categories);
        $this->assertContains('peci-drovianye-otopitelnye', $categories);
    }
}

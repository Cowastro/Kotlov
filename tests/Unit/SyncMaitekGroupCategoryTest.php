<?php

namespace Tests\Unit;

use App\Console\Commands\SyncMaitekGroupCommand;
use ReflectionClass;
use Tests\TestCase;

class SyncMaitekGroupCategoryTest extends TestCase
{
    public function test_heating_elements_and_electric_boilers_use_different_categories(): void
    {
        $command = new SyncMaitekGroupCommand;
        $reflection = new ReflectionClass($command);

        $categories = $reflection->getProperty('categoryBySlug');
        $categories->setValue($command, [
            'elektricheskie' => 10,
            'elektricheskie-teny-dlya-otopleniya' => 20,
        ]);

        $categoryId = $reflection->getMethod('categoryId');

        $this->assertSame(20, $categoryId->invoke($command, [
            'name' => 'ТЭНБ - 6 - G1½» с колпаком',
            'short_description' => 'Блок ТЭНов',
        ]));
        $this->assertSame(10, $categoryId->invoke($command, [
            'name' => 'Котел СТЭН ЭКО 5',
            'short_description' => 'Электрический котел',
        ]));
    }
}

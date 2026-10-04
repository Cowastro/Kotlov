<?php

namespace Tests\Unit;

use App\Console\Commands\RemoveUnsupportedStoveHeatingClaimsCommand;
use ReflectionMethod;
use Tests\TestCase;

class RemoveUnsupportedStoveHeatingClaimsCommandTest extends TestCase
{
    public function test_exact_unsupported_claim_is_removed_without_inventing_a_replacement_value(): void
    {
        $command = new RemoveUnsupportedStoveHeatingClaimsCommand;
        $clean = new ReflectionMethod($command, 'cleanField');

        [$result, $count] = $clean->invoke($command, '<p>Модель позволяет прогревать помещения до 60 м².</p>', [
            'помещения до 60 м²' => 'жилые помещения',
        ]);

        $this->assertSame(1, $count);
        $this->assertSame('<p>Модель позволяет прогревать жилые помещения.</p>', $result);
        $this->assertStringNotContainsString('60 м²', $result);
    }

    public function test_cleanup_is_idempotent(): void
    {
        $command = new RemoveUnsupportedStoveHeatingClaimsCommand;
        $clean = new ReflectionMethod($command, 'cleanField');

        [$result, $count] = $clean->invoke($command, 'Для отопления дома', [
            'для обогрева до 150 м³' => 'для отопления дома',
        ]);

        $this->assertSame(0, $count);
        $this->assertSame('Для отопления дома', $result);
    }

    public function test_all_reviewed_modena_wording_variants_are_removed(): void
    {
        $command = new RemoveUnsupportedStoveHeatingClaimsCommand;
        $clean = new ReflectionMethod($command, 'cleanField');

        [$result, $count] = $clean->invoke(
            $command,
            'Печь предназначена для обогрева помещений объёмом до 150 м³. Конфорка подходит для обогрева до 150 м³ и приготовления пищи.',
            [
                'предназначена для обогрева помещений объёмом до 150 м³' => 'предназначена для отопления жилых помещений',
                'для обогрева до 150 м³' => 'для отопления дома',
            ],
        );

        $this->assertSame(2, $count);
        $this->assertStringNotContainsString('150 м³', $result);
        $this->assertStringContainsString('для отопления жилых помещений', $result);
        $this->assertStringContainsString('для отопления дома', $result);
    }
}

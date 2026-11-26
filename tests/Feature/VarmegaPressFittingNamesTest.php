<?php

namespace Tests\Feature;

use App\Services\Catalog\BoilerAutomationGroups as Groups;
use Tests\TestCase;

class VarmegaPressFittingNamesTest extends TestCase
{
    public function test_every_new_name_keeps_the_article_and_gets_a_press_fitting_type(): void
    {
        $names = json_decode((string) file_get_contents(database_path('data/varmega_press_fitting_names_2026-11.json')), true);
        $this->assertGreaterThan(250, count($names));
        foreach ($names as $old => $new) {
            $this->assertMatchesRegularExpression('/^Varmega (VM\d+[A-Z0-9]*) /u', $old);
            preg_match('/^Varmega (\S+) /u', $old, $m);
            $this->assertStringContainsString($m[1], $new, $old);
            $this->assertNotNull(Groups::match($new, Groups::PRESS_TYPES), $new);
        }
        $this->assertSame('Угольник 90° двухраструбный Varmega Inox Press VM710002828, 28×28 мм', $names['Varmega VM710002828 28x28']);
    }
}

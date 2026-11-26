<?php

namespace Tests\Feature;

use App\Services\Catalog\BoilerAutomationGroups as Groups;
use Tests\TestCase;

class VarmegaManifoldNamesTest extends TestCase
{
    public function test_every_new_name_keeps_the_article_and_lands_in_a_manifold_group_or_a_move(): void
    {
        $names = json_decode((string) file_get_contents(database_path('data/varmega_manifold_names_2026-11.json')), true);
        $this->assertGreaterThan(120, count($names));
        foreach ($names as $old => $new) {
            preg_match('/^Varmega (\S+) /u', $old, $m);
            $this->assertStringContainsString($m[1], $new, $old);
            $this->assertTrue(Groups::move($new) !== null || Groups::match($new, Groups::MANIFOLD_TYPES) !== null, $new);
        }
        $this->assertSame('5 выходов', Groups::manifoldOutlets($names['Varmega VM15105 1", 5 x 3/4"EK']));
        $this->assertSame('filtry', Groups::move($names['Varmega VM04001 1/2"']));
        $this->assertSame('3 выхода', Groups::manifoldOutlets('Коллектор отопительного контура Bosch HKV 3/25/32'));
        $this->assertSame('11 выходов', Groups::manifoldOutlets('Гребенка для отопления KAN серия 61 (11 конт.)'));
        $this->assertSame('5 выходов', Groups::manifoldOutlets('Гребенка TERMICA MEX05'));
        $this->assertNull(Groups::manifoldOutlets('Шкаф для коллектора ШРВ-5(1044х122х670) (встроенный)'));
    }
}

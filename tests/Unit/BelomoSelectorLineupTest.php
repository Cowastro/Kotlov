<?php

namespace Tests\Unit;

use App\Support\Catalog\BelomoSelectorLineup;
use PHPUnit\Framework\TestCase;

class BelomoSelectorLineupTest extends TestCase
{
    public function test_it_builds_the_complete_price_list_for_selection(): void
    {
        $models = BelomoSelectorLineup::models();

        $this->assertCount(77, $models);
        $this->assertSame('1009.00.00.000-00', $models[0]['article_label']);
        $this->assertSame('8349.00.00.000-17', $models[76]['article_label']);
        $this->assertSame('G10', $models[76]['size']);
        $this->assertSame(16.0, $models[76]['max_flow']);
        $this->assertTrue($models[76]['request_only']);
        $this->assertSame('Под заказ · уточним цену и срок', $models[76]['availability_label']);
    }

    public function test_it_exposes_all_families_and_g4_thermal_variants(): void
    {
        $models = BelomoSelectorLineup::models();
        $families = BelomoSelectorLineup::families($models);
        $articles = array_column(array_filter($models, fn (array $model): bool => $model['size'] === 'G4' && $model['thermal']), 'article_label');

        $this->assertContains('СКАТ G10', array_column($families, 'label'));
        $this->assertContains('8181.00.00.000-20', $articles);
        $this->assertContains('8181.00.00.000-21', $articles);
        $this->assertContains('8336.00.00.000-06', $articles);
        $this->assertContains('8336.00.00.000-07', $articles);
    }
}

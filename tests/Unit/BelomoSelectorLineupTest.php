<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Support\Catalog\BelomoSelectorLineup;
use App\Support\Catalog\EliconGasMeterCatalog;
use Tests\TestCase;

class BelomoSelectorLineupTest extends TestCase
{
    public function test_factory_articles_identify_ultrasonic_models_after_title_normalization(): void
    {
        $models = BelomoSelectorLineup::models();
        $ultrasonic = array_filter($models, fn (array $model): bool => $model['technology'] === 'ultrasonic');
        $this->assertCount(48, $ultrasonic);
        foreach ($models as $model) {
            $expected = preg_match('/^(8345|8348|8349)\./', $model['article_label']) ? 'ultrasonic' : 'diaphragm';
            $this->assertSame($expected, $model['technology'], $model['article_label']);
        }
    }

    public function test_it_builds_the_complete_price_list_for_selection(): void
    {
        $models = BelomoSelectorLineup::models();

        $this->assertCount(79, $models);
        $this->assertSame('1009.00.00.000-00', $models[0]['article_label']);
        $this->assertSame('8349.00.00.000-17', $models[76]['article_label']);
        $this->assertSame('G10', $models[76]['size']);
        $this->assertSame(16.0, $models[76]['max_flow']);
        $this->assertTrue($models[76]['request_only']);
        $this->assertSame('Под заказ · уточним цену и срок', $models[76]['availability_label']);
    }

    public function test_all_official_models_and_confirmed_8181_24_are_present_without_duplicates(): void
    {
        $models = BelomoSelectorLineup::models();
        $articles = array_column($models, 'article_label');
        foreach (EliconGasMeterCatalog::models() as $row) {
            $this->assertContains($row['full_article'], $articles);
        }
        $this->assertSame($articles, array_values(array_unique($articles)));
        $this->assertContains('8181.00.00.000-24', $articles);
        $left = collect($models)->firstWhere('article_label', '8072.00.00.000-21');
        $this->assertSame(110, $left['length']);
        $this->assertTrue($left['stock_program']);
        $this->assertFalse($left['stock_priority']);
    }

    public function test_binding_uses_factory_article_even_if_names_disagree_and_never_name_alone(): void
    {
        $model = collect(BelomoSelectorLineup::models())->firstWhere('article_label', '8072.00.00.000-21');
        $product = new Product(['name' => 'Другое старое название L=250', 'sku' => 'CUSTOM', 'specs' => ['Артикул производителя' => '8072-21']]);
        $product->setRelation('supplierProducts', collect());
        $this->assertTrue(BelomoSelectorLineup::matchesProduct($product, $model));
        $product->specs = [];
        $product->name = $model['name'];
        $this->assertFalse(BelomoSelectorLineup::matchesProduct($product, $model));
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

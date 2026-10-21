<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\GasMeterSelectionGuide;
use PHPUnit\Framework\TestCase;

class GasMeterSelectionGuideTest extends TestCase
{
    public function test_it_explains_a_specific_meter_in_plain_language(): void
    {
        $product = new Product([
            'name' => 'Счетчик газа диафрагменный с термокомпенсатором СГД-3Т-1-2 G6 (правый) L=200',
        ]);
        $product->setRelation('category', new Category(['slug' => 'schetchiki-gaza']));

        $guide = GasMeterSelectionGuide::forProduct($product);

        $this->assertSame('G6', $guide['size']);
        $this->assertSame(10.0, $guide['max_flow']);
        $this->assertSame('правый', $guide['direction']);
        $this->assertSame(200, $guide['length']);
        $this->assertTrue($guide['thermal']);
        $this->assertSame('diaphragm', $guide['technology']);
    }

    public function test_flow_is_mapped_to_the_smallest_suitable_size(): void
    {
        $this->assertSame('G1,6', GasMeterSelectionGuide::sizeForFlow(2.5));
        $this->assertSame('G2,5', GasMeterSelectionGuide::sizeForFlow(2.6));
        $this->assertSame('G4', GasMeterSelectionGuide::sizeForFlow(5.0));
        $this->assertSame('G6', GasMeterSelectionGuide::sizeForFlow(8.0));
        $this->assertNull(GasMeterSelectionGuide::sizeForFlow(10.1));
    }

    public function test_recommendations_respect_mounting_parameters(): void
    {
        $models = GasMeterSelectionGuide::recommend(5.5, [
            'direction' => 'left',
            'length' => 200,
            'technology' => 'diaphragm',
            'thermal' => true,
        ]);

        $this->assertCount(1, $models);
        $this->assertSame('8181-20', $models[0]['article']);
        $this->assertTrue($models[0]['stock_program']);
    }

    public function test_specification_is_presented_as_eight_buyer_choices_and_twelve_exact_articles(): void
    {
        $this->assertCount(12, GasMeterSelectionGuide::selectorModels());
        $this->assertCount(8, GasMeterSelectionGuide::selectorFamilies());
        $this->assertSame([
            'sgd-g1-6',
            'sgd-g2-5',
            'sgd-3t-g4',
            'sgd-3t-g6',
            'vega-g1-6',
            'vega-g2-5',
            'kata-g4',
            'skat-g6',
        ], array_column(GasMeterSelectionGuide::selectorFamilies(), 'key'));
    }

    public function test_every_article_from_the_specification_can_be_selected_by_its_real_parameters(): void
    {
        $cases = [
            ['8072-20', 1.2, ['direction' => 'right', 'length' => 110, 'technology' => 'diaphragm']],
            ['8072-21', 1.2, ['direction' => 'left', 'length' => 110, 'technology' => 'diaphragm']],
            ['8072-22', 3.0, ['direction' => 'right', 'length' => 110, 'technology' => 'diaphragm']],
            ['8072-23', 3.0, ['direction' => 'left', 'length' => 110, 'technology' => 'diaphragm']],
            ['8181-20', 5.0, ['direction' => 'left', 'length' => 200, 'technology' => 'diaphragm', 'thermal' => true]],
            ['8181-00', 7.0, ['direction' => 'left', 'length' => 200, 'technology' => 'diaphragm', 'thermal' => true]],
            ['8181-01', 7.0, ['direction' => 'right', 'length' => 200, 'technology' => 'diaphragm', 'thermal' => true]],
            ['8181-10', 7.0, ['direction' => 'left', 'length' => 250, 'technology' => 'diaphragm', 'thermal' => true]],
            ['8345-00', 1.2, ['technology' => 'ultrasonic']],
            ['8345-04', 3.0, ['technology' => 'ultrasonic']],
            ['8348-09', 5.0, ['technology' => 'ultrasonic']],
            ['8349-01', 7.0, ['technology' => 'ultrasonic']],
        ];

        foreach ($cases as [$article, $flow, $filters]) {
            $articles = array_column(GasMeterSelectionGuide::recommend($flow, $filters), 'article');
            $this->assertContains($article, $articles, "Артикул {$article} должен подбираться по своим параметрам.");
        }
    }

    public function test_non_stock_belomo_model_is_clearly_marked_as_order_only(): void
    {
        $product = new Product([
            'name' => 'Счетчик газа ультразвуковой КАТА-G4 R-2',
            'slug' => 'kata-g4-r-2',
            'sku' => '8348-06',
            'price' => 555,
            'is_active' => true,
            'in_stock' => true,
        ]);
        $product->setRelation('category', new Category(['slug' => 'schetchiki-gaza']));
        $product->setRelation('supplierProducts', collect());
        $product->setRelation('integrationProducts', collect());
        $product->setRelation('purchasePriceSources', collect());

        $model = GasMeterSelectionGuide::selectorProduct($product, false);

        $this->assertSame('Под заказ', $model['availability_label']);
        $this->assertFalse($model['stock_program']);
        $this->assertFalse($model['buyable']);
        $this->assertSame('/baza-znaniy/belomo-kata-skat-oboznacheniya#variants', $model['technical_url']);
    }

    public function test_non_meter_product_is_ignored(): void
    {
        $product = new Product(['name' => 'Газовый котёл G6']);
        $product->setRelation('category', new Category(['slug' => 'gazovye-kotly']));

        $this->assertNull(GasMeterSelectionGuide::forProduct($product));
    }
}

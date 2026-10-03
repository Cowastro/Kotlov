<?php

namespace Tests\Unit;

use App\Models\Brand;
use App\Models\Product;
use App\Services\HeatPumpProductPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HeatPumpProductPresenterTest extends TestCase
{
    #[Test]
    public function it_builds_a_high_temperature_profile_for_an_r290_kotlov_ge_model(): void
    {
        $product = new Product([
            'name' => 'Тепловой насос KOTLOV GE NL-FLM50-190II/R290 19 кВт',
            'specs' => [
                ['key' => 'Модель', 'value' => 'NL-FLM50-190II/R290'],
                ['key' => 'Мощность', 'value' => '19 кВт'],
                ['key' => 'Хладагент', 'value' => 'R290'],
                ['key' => 'Питание', 'value' => '380 В'],
                ['key' => 'Температура воды', 'value' => 'до 75 °C'],
            ],
        ]);
        $product->setRelation('brand', new Brand(['name' => 'KOTLOV GE']));

        $profile = app(HeatPumpProductPresenter::class)->build($product);

        $this->assertSame('NL-FLM50-190II/R290', $profile['model']);
        $this->assertSame('19 кВт', $profile['power']);
        $this->assertSame('/teplovye-nasosy-r290', $profile['landing_url']);
        $this->assertContains('Радиаторное отопление', $profile['uses']);
    }

    #[Test]
    public function it_builds_an_r32_profile_for_our_line_and_ignores_other_brands(): void
    {
        $product = new Product([
            'name' => 'Тепловой насос FLM30-R32',
            'specs' => [
                ['key' => 'Хладагент', 'value' => 'R32'],
                ['key' => 'Мощность', 'value' => '10 кВт'],
            ],
        ]);
        $product->setRelation('brand', new Brand(['name' => 'KOTLOV GE']));

        $profile = app(HeatPumpProductPresenter::class)->build($product);

        $this->assertSame('/teplovye-nasosy-dlya-doma', $profile['landing_url']);
        $this->assertContains('Тёплый пол', $profile['uses']);

        $product->setRelation('brand', new Brand(['name' => 'Другой бренд']));
        $this->assertNull(app(HeatPumpProductPresenter::class)->build($product));
    }
}

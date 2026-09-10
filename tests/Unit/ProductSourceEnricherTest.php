<?php

namespace Tests\Unit;

use App\Services\ProductSourceEnricher;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ProductSourceEnricherTest extends TestCase
{
    public function test_it_extracts_rusklimat_b2b_characteristics(): void
    {
        $html = <<<'HTML'
        <div class="characteristics">
            <table><tr><th>Фото</th><th>Наименование</th></tr></table>
            <div class="characteristics__line">
                <span class="characteristics__leftText">Фото</span>
                <div class="characteristics__right">Наименование</div>
            </div>
            <div class="characteristics__line">
                <div class="characteristics__left">
                    <span class="characteristics__leftText">Объем внутреннего бака</span>
                </div>
                <div class="characteristics__center"></div>
                <div class="characteristics__right">100 л&nbsp;</div>
            </div>
            <div class="characteristics__line">
                <div class="characteristics__left">
                    <span class="characteristics__leftText">Материал бака</span>
                </div>
                <div class="characteristics__right"><span>Нержавеющая сталь</span></div>
            </div>
        </div>
        HTML;

        $method = new ReflectionMethod(ProductSourceEnricher::class, 'extractSpecs');
        $specs = $method->invoke(new ProductSourceEnricher(), $html);

        $this->assertCount(2, $specs);
        $this->assertContains([
            'key' => 'Объем внутреннего бака',
            'value' => '100 л',
            'unit' => '',
        ], $specs);
        $this->assertContains([
            'key' => 'Материал бака',
            'value' => 'Нержавеющая сталь',
            'unit' => '',
        ], $specs);
    }
}

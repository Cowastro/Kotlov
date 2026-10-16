<?php

namespace Tests\Unit;

use App\Services\Suppliers\OmenergoCatalog;
use PHPUnit\Framework\TestCase;

class OmenergoCatalogTest extends TestCase
{
    public function test_only_documented_aliases_match_and_variants_remain_distinct(): void
    {
        $c = new OmenergoCatalog;
        $this->assertSame($c->key('Энергия Voltron 500 (HP)'), $c->key('Стабилизатор напряжения для отопительных систем Энергия Voltron 500'));
        $this->assertNotSame($c->key('Энергия Voltron 500 (HP)'), $c->key('Энергия Voltron Neo 500'));
        $this->assertNotSame($c->key('Энергия Ultra 5000'), $c->key('Энергия Ultra 5000 (HV)'));
        $this->assertSame($c->key('Стабилизатор напряжения Энерготех NORMA 3500ВА'), $c->key('Стабилизатор напряжения NORMA 3500'));
        $this->assertNull($c->find(['name' => 'Энергия Voltron 500 (HP)'], [['name' => 'Voltron 500', 'url' => 'a'], ['name' => 'Voltron 500', 'url' => 'b']]));
    }

    public function test_gallery_does_not_collect_logos_related_products_or_untrusted_urls(): void
    {
        $c = new OmenergoCatalog;
        $html = '<h1>Энергия Voltron 500</h1><li class="us-product-info-item-manufacturer"><a>Энергия</a></li>'
            .'<img src="https://omenergo.by/logo.jpg"><a class="oct-gallery" href="https://omenergo.by/image/product-1000x1000.jpg">Photo</a>'
            .'<a class="oct-gallery" href="https://other.invalid/image/product.jpg">Wrong</a>'
            .'<div class="us-product-description-cont"><table><tr><td>Мощность</td><td>500 ВА</td></tr></table></div>';
        $page = $c->parsePage($html);
        $this->assertSame('Энергия', $page['brand']);
        $this->assertSame(['https://omenergo.by/image/product-1000x1000.jpg'], $page['images']);
        $this->assertSame([['key' => 'Мощность', 'value' => '500 ВА']], $page['specs']);
    }

    public function test_article_conflict_and_accuracy_conflict_cannot_be_published(): void
    {
        $c = new OmenergoCatalog;
        $this->assertFalse($c->identityMatches(['article' => 'Е0101-0242', 'brand' => 'Энергия', 'name' => 'Энергия Expert 800'], ['article' => 'Е0101-0242', 'name' => 'Энергия Expert 350 (220В)']));
        $this->assertFalse($c->identityMatches(['article' => 'Е0102-0105', 'brand' => 'Энергия', 'name' => 'ЛАТР ЭНЕРГИЯ Black Series 1Ф TDGC2-15кВА 45А (0-300V)'], ['article' => 'Е0102-0105', 'name' => 'ЛАТР ЭНЕРГИЯ Black Series 1Ф TDGC2-0.5кВА 2А (0-250V)']));
        $this->assertTrue($c->identityMatches(['article' => 'Е0101-0240', 'brand' => 'Энергия', 'name' => 'Энергия Expert 350'], ['article' => 'Е0101-0240', 'name' => 'Инверторный стабилизатор напряжения Энергия Expert 350 (230В)']));
        $result = $c->verifiedSpecs(['description' => 'Релейный стабилизатор, 5% точность.'], ['specs' => [['key' => 'Номинальное выходное напряжение', 'value' => '220±10%'], ['key' => 'Мощность', 'value' => '3000 ВА']]]);
        $this->assertCount(1, $result['conflicts']);
        $this->assertSame([['key' => 'Мощность', 'value' => '3000 ВА']], $result['specs']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Suppliers\OmenergoCatalog;
use PHPUnit\Framework\TestCase;

class OmenergoCatalogTest extends TestCase
{
    public function test_pro_heading_without_battery_voltage_requires_exact_article_and_technical_values(): void
    {
        $c = new OmenergoCatalog;
        $row = ['name' => 'Энергия ИБП Pro-3400 24V', 'brand' => 'Энергия', 'article' => 'Е0201-0032'];
        $page = ['name' => 'Интерактивный ИБП Энергия Pro-3400', 'article' => 'Е0201-0032', 'specs' => [
            ['key' => 'Напряжение АКБ, В', 'value' => '24 (2x12 АКБ)'],
            ['key' => 'Номинальная/ пороговая мощность', 'value' => '3400 ВА'],
        ]];
        $this->assertTrue($c->identityMatches($row, $page));
        $page['specs'][0]['value'] = '12';
        $this->assertFalse($c->identityMatches($row, $page));
        $page['specs'][0]['value'] = '24 (2x12 АКБ)';
        $page['article'] = 'Е0201-0033';
        $this->assertFalse($c->identityMatches($row, $page));
        $page['article'] = 'Е0201-0032';
        $page['specs'][1]['value'] = '5000 ВА';
        $this->assertFalse($c->identityMatches($row, $page));
    }

    public function test_old_garant_requires_matching_capacity_and_battery_voltage(): void
    {
        $c = new OmenergoCatalog;
        $row = ['name' => 'ИБП Гарант-500 12В 500ВА', 'brand' => 'Энергия'];
        $entry = ['name' => 'Источник бесперебойного питания Энергия Гарант-500', 'url' => 'https://omenergo.by/old-garant/'];
        $this->assertSame($entry, $c->find($row, [$entry]));
        $this->assertNull($c->find($row, [$entry, array_merge($entry, ['url' => 'https://omenergo.by/duplicate/'])]));
        $page = $entry + ['brand' => 'Энергия', 'specs' => [
            ['key' => 'Пиковая/номинальная мощность', 'value' => '500/300 ВА'],
            ['key' => 'Напряжение подключаемых батарей', 'value' => '12 В'],
        ]];
        $this->assertTrue($c->identityMatches($row, $page));
        $page['specs'][1]['value'] = '24 В';
        $this->assertFalse($c->identityMatches($row, $page));
        $page['specs'][1]['value'] = '12 В';
        $page['specs'][0]['value'] = '750/450 ВА';
        $this->assertFalse($c->identityMatches($row, $page));
        $page['specs'] = [];
        $this->assertFalse($c->identityMatches($row, $page));
    }

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

    public function test_supplier_three_column_units_and_model_attribute_blocks_are_preserved(): void
    {
        $html = '<div class="us-product-description-cont"><table>'
            .'<tr><td>Точность стабилизации</td><td>±%</td><td>5,7</td></tr>'
            .'<tr><td>Режим работы</td><td>—</td><td>Постоянный</td></tr>'
            .'<tr><td>Other model</td><td>1</td><td>2</td><td>3</td></tr></table></div>'
            .'<div id="us-product-attributes"><div class="us-product-attr-item d-flex"><span>Мощность</span><span>3,5кВа</span></div></div>';
        $this->assertSame([
            ['key' => 'Точность стабилизации', 'value' => '±5,7%'],
            ['key' => 'Режим работы', 'value' => 'Постоянный'],
            ['key' => 'Мощность', 'value' => '3,5кВа'],
        ], (new OmenergoCatalog)->parsePage($html)['specs']);
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

    public function test_manufacturer_variant_parser_uses_selected_model_not_family_heading(): void
    {
        $html = '<h1>Стабилизаторы Энергия Константа</h1>'
            .'<div class="card-characteristic__item"><span>Название модели</span><span>Стабилизатор напряжения Энергия Константа 8000</span></div>'
            .'<div class="card-characteristic__item"><span>Артикул<div>Подсказка</div></span><span>Е0101-0225</span></div>'
            .'<div class="card-characteristic__item"><span>Точность</span><span>3%</span></div>'
            .'<div class="slider__for--img"><img data-old="https://xn--c1adkrq9eta.xn--p1ai/sites/default/files/styles/style_400x400/public/product-variants/e0101-0225-01.jpg"></div>';
        $page = (new OmenergoCatalog)->parseFactoryPage($html);
        $this->assertSame('Е0101-0225', $page['article']);
        $this->assertStringContainsString('8000', $page['name']);
        $this->assertSame(['https://xn--c1adkrq9eta.xn--p1ai/sites/default/files/product-variants/e0101-0225-01.jpg'], $page['images']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\HeaterEquipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaterEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug): Category
    {
        return Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => 0, 'is_active' => true]);
    }

    private function product(Category $category, string $slug, array $extra = []): Product
    {
        return Product::create($extra + ['category_id' => $category->id, 'name' => $slug, 'slug' => $slug, 'sku' => $slug,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK]);
    }

    public function test_bare_transformer_shows_required_control_separate_feet_and_standard_related_cards(): void
    {
        $heater = $this->product($this->category('elektricheskie-konvektoryi'), 'ballu-becevu-1500');
        $parts = $this->category(HeaterEquipment::PARTS_CATEGORY);
        $control = $this->product($parts, 'ballu-bctevu-4e');
        $feet = $this->product($parts, 'ballu-bftevur');
        $this->product($parts, 'ballu-cwm-02');
        $response = $this->get('/'.$heater->category->slug.'/'.$heater->slug)->assertOk();
        $response->assertSee('Комплектация и установка')->assertSee('В комплекте')->assertSee('Комплект настенного крепления')
            ->assertSee('Приобретается отдельно')->assertSee('Для работы')->assertSee('Для установки на пол')
            ->assertSee('Выбрать совместимые комплектующие')->assertSee('id="compatible-heater-equipment"', false)
            ->assertSee('/'.HeaterEquipment::PARTS_CATEGORY.'/'.$control->slug)->assertSee('/'.HeaterEquipment::PARTS_CATEGORY.'/'.$feet->slug);
        $groups = $response->viewData('heaterEquipmentGroups');
        $this->assertSame(['control', 'feet'], $groups->pluck('kind')->all());
        $this->assertSame([$control->id, $feet->id], $groups->flatMap(fn ($g) => $g['products']->pluck('product.id'))->all());
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-heater-equipment>'), strpos($html, 'product-desktop-purchase-actions'));
    }

    public function test_only_exact_series_and_orderable_active_parts_are_recommended(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        $air = $this->product($category, 'electrolux-echag2-1500-t');
        $rapid = $this->product($category, 'konvektor-elektriceskii-electrolux-echr-1500-t-otopitelnyi-modul');
        $parts = $this->category(HeaterEquipment::PARTS_CATEGORY);
        foreach (['electrolux-eftag2r', 'electrolux-eftrr-komplekt-sassi-dlia-konvektora-rapid-transformer',
            'komplekt-sassi-electrolux-air-gate-transformer-eftag2r', 'universalnyi-komplekt-opor-electrolux-efa'] as $slug) {
            $this->product($parts, $slug);
        }
        $this->product($parts, 'electrolux-echtui4', ['is_archived' => true]);
        $this->product($parts, 'electrolux-echtue4', ['is_active' => false]);
        $this->product($parts, 'blok-upravleniia-electrolux-echtum3-transformer-mechanic', ['price' => 0]);
        $groups = HeaterEquipment::relatedGroups($air, HeaterEquipment::details($air));
        $this->assertSame(['feet'], $groups->pluck('kind')->all());
        $this->assertSame(['electrolux-eftag2r'], $groups[0]['products']->pluck('product.slug')->all());
        $groups = HeaterEquipment::relatedGroups($rapid, HeaterEquipment::details($rapid));
        $this->assertSame(['electrolux-eftrr-komplekt-sassi-dlia-konvektora-rapid-transformer'], $groups[0]['products']->pluck('product.slug')->all());
        $parts->update(['is_active' => false]);
        $this->assertTrue(HeaterEquipment::relatedGroups($air, HeaterEquipment::details($air))->isEmpty());
    }

    public function test_included_mounts_are_not_sold_again_and_unverified_models_are_not_guessed(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        $timberk = $this->product($category, 'elektriceskii-konvektor-timberk-tecpf10n-dg-1500');
        $details = HeaterEquipment::details($timberk);
        $this->assertContains('Опорные ножки — 2 шт.', $details['included']);
        $this->assertContains('Комплект настенной установки', $details['included']);
        $this->assertSame([], $details['separate']);
        $emt = $this->product($category, 'ballu-becemt-1500');
        $details = HeaterEquipment::details($emt);
        $this->assertSame(['wall'], array_column($details['separate'], 'kind'));
        $this->assertContains('Комплект для напольной установки', $details['included']);
        $unknown = $this->product($category, 'ballu-becevu-1500-unverified');
        $this->get('/'.$category->slug.'/'.$unknown->slug)->assertOk()->assertSee('Точная комплектация этой модели пока не подтверждена.')
            ->assertDontSee('id="compatible-heater-equipment"', false);
        $other = $this->product($this->category('kotly'), 'ballu-becevu-2000');
        $this->assertNull(HeaterEquipment::details($other));
    }

    public function test_plinth_and_ready_convectors_do_not_inherit_transformer_accessories(): void
    {
        $category = $this->category('elektricheskie-konvektoryi');
        foreach (['ballu-becpl-1500', 'konvektor-elektriceskii-electrolux-rapid-echr-1000-m',
            'konvektor-elektriceskii-electrolux-echa2-1000-m'] as $slug) {
            $p = $this->product($category, $slug);
            $this->assertTrue(HeaterEquipment::relatedGroups($p, HeaterEquipment::details($p))->isEmpty());
        }
        $elboom = $this->product($this->category('elektricheskie-teplye-plintusy'), 'elektrokonvektor-plintusnyiy-elboom-ev-p-027-230');
        $this->assertSame(['Внешний терморегулятор', 'Распределительная коробка'], array_column(HeaterEquipment::details($elboom)['separate'], 'label'));
    }

    public function test_every_published_fact_and_relationship_has_manufacturer_evidence(): void
    {
        $records = HeaterEquipment::records();
        foreach ($records['products'] as $slug => $fact) {
            $this->assertNotEmpty($fact['sources'], $slug);
            $this->assertNotEmpty($fact['evidence'], $slug);
            foreach ($fact['sources'] as $source) $this->assertStringStartsWith('https://', $source);
            foreach ($fact['separate'] as $group) {
                $this->assertContains($group['requirement'], ['required', 'floor', 'wall']);
                foreach ($group['parts'] as $id) {
                    $this->assertArrayHasKey($id, $records['parts']);
                    $this->assertStringStartsWith('https://b2b.rusklimat.com/', $records['parts'][$id]['source']);
                }
            }
        }
    }
}

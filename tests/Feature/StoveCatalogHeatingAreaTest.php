<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoveCatalogHeatingAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_area_bands_use_the_same_facts_as_cards_instead_of_old_filter_buckets(): void
    {
        $category = Category::create(['slug' => 'pechki', 'name' => 'Печи', 'parent_id' => 0, 'is_active' => true]);
        $casting = Category::create(['slug' => 'pechnoe-i-kaminnoe-lite', 'name' => 'Литьё', 'parent_id' => $category->id, 'is_active' => true]);
        $area = Attribute::create([
            'category_id' => $category->id, 'name' => 'Площадь отапливаемого помещения (м²)',
            'type' => 'select', 'in_filter' => true, 'in_product' => true,
        ]);
        $outdated = AttributeOption::create(['attribute_id' => $area->id, 'name' => '50 м2 - 100 м2']);

        $add = fn (Category $group, string $slug, string $key, string $value) => Product::create([
            'category_id' => $group->id, 'slug' => $slug, 'sku' => $slug, 'name' => 'Печь '.$slug,
            'price' => 100, 'is_active' => true, 'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => [['key' => $key, 'value' => $value]],
        ]);
        $explicit = $add($category, 'verified-area', 'Площадь обогрева', '120 м²');
        $explicit->allAttributeValues()->create(['attribute_id' => $area->id, 'option_id' => $outdated->id]);
        $estimate = $add($category, 'estimated-area', 'Площадь отапливаемого помещения', 'до 80 м² (при высоте потолка 2,5 м)');
        $volume = $add($category, 'volume-only', 'Объём обогрева', 'до 200 м³');
        $volume->allAttributeValues()->create(['attribute_id' => $area->id, 'option_id' => $outdated->id]);
        $accessory = $add($casting, 'casting-item', 'Площадь обогрева', '150 м²');

        $this->get('/pechki')->assertOk()
            ->assertSee('heating_area=over-100#catalog-products', false)
            ->assertSeeText('120 м²')
            ->assertSeeText('Оценка при потолке 2,5 м: до 80 м²')
            ->assertSeeText('Объём обогрева: до 200 м³')
            ->assertSee('class="fireplace-card-specs ', false)
            ->assertDontSee('id="attr-'.$area->id.'"', false)
            ->assertViewHas('stoveHeatingFacts', fn (array $facts) => ! isset($facts[$accessory->id]) && ! $facts[$volume->id]['is_area']);

        $this->get('/pechki?heating_area=over-100')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$explicit->id])
            ->assertViewHas('stoveAreaLinks', fn (array $links) => collect($links)->firstWhere('active', true)['count'] === 1);
        $this->get('/pechki?heating_area=50-to-100')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$estimate->id]);
        $legacy = http_build_query(['attr' => [$area->id => [$outdated->id]]]);
        $this->get('/pechki?'.$legacy.'&heating_area=50-to-100')->assertOk()
            ->assertViewHas('products', fn ($page) => $page->pluck('id')->all() === [$estimate->id]);
    }
}

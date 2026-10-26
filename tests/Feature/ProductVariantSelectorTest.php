<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductVariantSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductVariantSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_groups_same_tank_series_by_volume_and_shows_availability(): void
    {
        $category = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'membrannye-baki',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Flamco',
            'slug' => 'flamco-selector-test',
            'is_active' => true,
        ]);

        $current = $this->tank($category, $brand, 25, Product::AVAILABILITY_CHECK);
        $available = $this->tank($category, $brand, 35, Product::AVAILABILITY_IN_STOCK, true);
        $this->tank($category, $brand, 50, Product::AVAILABILITY_OUT_OF_STOCK);

        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Расширительный бак Flamco Airfix R 35 л',
            'slug' => 'flamco-airfix-r-35-test',
            'price' => 200,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);

        $response = $this->get('/membrannye-baki/'.$current->slug);

        $response->assertOk()
            ->assertSeeText('Выберите объём:')
            ->assertSeeText('25 л')
            ->assertSeeText('35 л')
            ->assertSeeText('50 л')
            ->assertSee('/membrannye-baki/'.$available->slug, false)
            ->assertSee('is-current', false)
            ->assertSee('is-in_stock', false);

        $this->assertSame(3, substr_count($response->getContent(), 'class="product-model-selector__option '));
    }

    public function test_excluded_product_remains_visible_in_admin_group_but_not_on_storefront(): void
    {
        $category = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'membrannye-baki',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Wester',
            'slug' => 'wester-selector-test',
            'is_active' => true,
        ]);

        $current = $this->tank($category, $brand, 24, Product::AVAILABILITY_CHECK);
        $excluded = $this->tank($category, $brand, 35, Product::AVAILABILITY_CHECK);
        $excluded->update(['variant_selector_excluded' => true]);

        $selector = app(ProductVariantSelector::class);

        $this->assertNull($selector->forProduct($current));
        $this->assertCount(2, $selector->forAdmin($current)['variants']);
        $this->assertTrue($selector->forAdmin($current)['variants']->last()['is_excluded']);
    }

    public function test_admin_can_see_series_cards_and_exclude_the_current_product(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $category = Category::query()->create([
            'name' => 'Мембранные баки',
            'slug' => 'membrannye-baki',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Flamco Admin',
            'slug' => 'flamco-admin-selector-test',
            'is_active' => true,
        ]);
        $current = $this->tank($category, $brand, 25, Product::AVAILABILITY_CHECK);
        $this->tank($category, $brand, 50, Product::AVAILABILITY_CHECK);

        $this->actingAs($admin)
            ->get(ProductResource::getUrl('edit', ['record' => $current], panel: 'admin'))
            ->assertOk()
            ->assertSeeText('Варианты модели на сайте')
            ->assertSeeText('25 л')
            ->assertSeeText('50 л')
            ->assertSeeText('текущая');

        Livewire::actingAs($admin)
            ->test(EditProduct::class, ['record' => $current->getRouteKey()])
            ->fillForm(['variant_selector_excluded' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($current->fresh()->variant_selector_excluded);
    }

    public function test_tis_standard_50_cards_switch_between_diameters_of_the_same_element(): void
    {
        $category = Category::query()->updateOrCreate(['slug' => 'truby-mono'], [
            'name' => 'Трубы одностенные',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Теплов и Сухов',
            'slug' => 'teplov-i-suhov-selector-test',
            'is_active' => true,
        ]);
        $current = $this->tisChimney($category, $brand, '115');
        $second = $this->tisChimney($category, $brand, '150');
        $this->tisChimney($category, $brand, '200', 'Труба моно L500 ТМ-Р (304-0.8)');

        $selector = app(ProductVariantSelector::class)->forProduct($current);

        $this->assertSame('diameter', $selector['type']);
        $this->assertSame('Выберите диаметр:', $selector['title']);
        $this->assertSame(['Ø115 мм', 'Ø150 мм'], $selector['variants']->pluck('label')->all());
        $this->assertSame('/truby-mono/'.$second->slug, parse_url($selector['variants']->last()['url'], PHP_URL_PATH));
    }

    public function test_tis_standard_30_mono_selector_includes_shared_standard_50_elements(): void
    {
        $category = Category::query()->updateOrCreate(['slug' => 'truby-mono'], [
            'name' => 'Трубы одностенные',
            'parent_id' => 0,
            'is_active' => true,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Теплов и Сухов',
            'slug' => 'teplov-i-suhov-standard-30-selector-test',
            'is_active' => true,
        ]);
        $current = $this->tisChimney($category, $brand, '80', standard: 30);
        $this->tisChimney($category, $brand, '120', standard: 30);
        $this->tisChimney($category, $brand, '150');

        $selector = app(ProductVariantSelector::class)->forProduct($current);

        $this->assertSame(['Ø80 мм', 'Ø120 мм', 'Ø150 мм'], $selector['variants']->pluck('label')->all());
    }

    public function test_chimney_parts_switch_between_diameters_of_the_same_element_whatever_the_naming(): void
    {
        $category = Category::query()->create(['name' => 'Трубы моно', 'slug' => 'truby-mono', 'is_active' => true]);
        $card = fn (string $name, string $slug) => Product::query()->create([
            'category_id' => $category->id, 'name' => $name, 'slug' => $slug, 'price' => 50, 'is_active' => true, 'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
        ]);
        $d150 = $card('Теплов и Сухов Труба L1000 ТМ-Р 430-0.8 D150', 'tis-l1000-150');
        $card('Труба Теплов и Сухов Моно ТМ-Р L1000 430-0.8 Ø120', 'tis-l1000-120');
        $card('Теплов и Сухов Труба L500 ТМ-Р 430-0.8 D130', 'tis-l500-130');
        $card('Теплов и Сухов Труба L1000 ТМ-Р 430-0.8 D130 (MC Black)', 'tis-l1000-130-black');
        $card('Ferrum Труба L1000 430-0.8 D130', 'ferrum-l1000-130');

        $selector = app(ProductVariantSelector::class)->forProduct($d150->fresh('category'));

        $this->assertSame('Выберите диаметр:', $selector['title']);
        $this->assertSame(['Ø120', 'Ø150'], $selector['variants']->pluck('label')->all());
    }

    private function tank(Category $category, Brand $brand, int $volume, string $availability, bool $inStock = false): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => "Расширительный бак Flamco Flexcon R {$volume} л",
            'slug' => "flamco-flexcon-r-{$volume}-test",
            'price' => 150 + $volume,
            'is_active' => true,
            'is_archived' => false,
            'in_stock' => $inStock,
            'manual_stock_management' => true,
            'availability_status' => $availability,
        ]);
    }

    private function tisChimney(Category $category, Brand $brand, string $diameter, string $element = 'Труба моно L1000 ТМ-Р (304-0.8)', int $standard = 50): Product
    {
        return Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'ТиС Стандарт '.$standard.' '.$element.' D'.$diameter,
            'slug' => 'tis-standard-'.$standard.'-'.str_replace('/', '-', $diameter).'-'.md5($element),
            'sku' => 'TIS'.$standard.'-'.str_replace('/', '-', $diameter).'-'.substr(md5($element), 0, 5),
            'price' => 100,
            'is_active' => true,
            'is_archived' => false,
            'availability_status' => Product::AVAILABILITY_CHECK,
            'specs' => [
                'Серия' => 'ТиС «Стандарт '.$standard.'»',
                'Исполнение' => 'Моно',
                'Тип элемента' => $element,
                'Диаметр' => $diameter,
            ],
        ]);
    }
}

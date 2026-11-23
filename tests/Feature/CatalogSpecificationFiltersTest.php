<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Services\CatalogSpecificationFilters;
use App\Services\ChimneyDiameterQuickFilter;
use App\Services\Integrations\IntegrationProductCreator;
use App\Services\ProductSourceEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSpecificationFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_diameter_reaches_inherited_and_quick_filters_without_mono_in_title(): void
    {
        $root = Category::updateOrCreate(['slug' => 'dymohody'], ['name' => 'Дымоходы', 'parent_id' => 0, 'is_active' => true]);
        $category = Category::create(['slug' => 'shibery-audit', 'name' => 'Шиберы', 'parent_id' => $root->id, 'is_active' => true]);
        $attribute = Attribute::create(['category_id' => $root->id, 'name' => 'Диаметр', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => '115 мм']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Шибер-задвижка Теплов и Сухов, 430-0,8, Ø 115',
            'slug' => 'gate-115-audit', 'price' => 90, 'is_active' => true, 'is_archived' => false,
            'specs' => [['key' => 'Диаметр', 'value' => '115 мм']]]);
        $service = app(CatalogSpecificationFilters::class);
        $this->assertSame(1, $service->sync($product));
        $this->assertSame(0, $service->sync($product));
        $this->get('/shibery-audit?'.http_build_query(['attr' => [$attribute->id => [$option->id]]]))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));
        $this->get('/shibery-audit?mono_diameter=115')->assertOk()
            ->assertViewHas('products', fn ($products) => $products->contains('id', $product->id));
        $filter = new ChimneyDiameterQuickFilter;
        $this->assertNull($filter->monoDiameter('Шибер термо Ø115/200'));
        $this->assertNull($filter->monoDiameter('Шибер Ø115-200'));
        $this->assertSame('430', $filter->steelGrade('Шибер', [['key' => 'Марка стали', 'value' => 'AISI 430']]));
    }

    public function test_new_imported_cards_and_source_refresh_keep_existing_filter_option(): void
    {
        $category = Category::create(['slug' => 'filter-audit', 'name' => 'Проверка', 'is_active' => true]);
        $attribute = Attribute::create(['category_id' => $category->id, 'name' => 'Толщина', 'suffix' => 'мм', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => '0.8']);
        $specs = [['key' => 'Толщина', 'value' => '0,8 мм']];
        $product = app(IntegrationProductCreator::class)->createStandalone(['category_id' => $category->id, 'name' => 'Элемент', 'retail_price' => 50, 'specs' => $specs]);
        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        app(ProductSourceEnricher::class)->syncSpecsToAttributeValues($product, $specs);
        $this->assertDatabaseHas('product_attribute_values', ['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        $this->assertSame(1, ProductAttributeValue::where('product_id', $product->id)->where('attribute_id', $attribute->id)->count());
    }

    public function test_audit_only_maps_exact_existing_options_and_preserves_manual_and_ambiguous_values(): void
    {
        $category = Category::create(['slug' => 'safe-filter-audit', 'name' => 'Проверка', 'is_active' => true]);
        $attribute = Attribute::create(['category_id' => $category->id, 'name' => 'Цвет', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => 'Белый']);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Прибор', 'slug' => 'safe-filter-card', 'price' => 100,
            'is_active' => true, 'is_archived' => false, 'specs' => [['key' => 'Цвет', 'value' => 'Белый']]]);
        $value = ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'value' => 'Белый']);
        $this->artisan('catalog:sync-specification-filters')->assertSuccessful();
        $this->assertNull($value->fresh()->option_id);
        $this->artisan('catalog:sync-specification-filters', ['--apply' => true])->assertSuccessful();
        $this->assertSame($option->id, $value->fresh()->option_id);
        $product->update(['specs' => [['key' => 'Цвет', 'value' => 'Чёрный']]]);
        $this->assertSame([], app(CatalogSpecificationFilters::class)->plan($product));
        $value->delete();
        $product->update(['specs' => [['key' => 'Цвет', 'value' => 'Белый'], ['key' => 'Цвет', 'value' => 'Чёрный']]]);
        $this->assertSame([], app(CatalogSpecificationFilters::class)->plan($product));
    }
}

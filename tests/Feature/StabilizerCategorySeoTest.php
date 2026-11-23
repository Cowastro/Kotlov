<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\Catalog\StabilizerCategoryPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StabilizerCategorySeoTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $category = Category::updateOrCreate(['slug' => StabilizerCategoryPage::SLUG], ['name' => 'Стабилизаторы напряжения', 'parent_id' => 0, 'is_active' => true]);
        $product = Product::create(['name' => 'Test stabilizer', 'slug' => 'test-stabilizer', 'sku' => 'SEO-ST-TEST', 'category_id' => $category->id,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK]);
        $attribute = Attribute::create(['name' => 'Число фаз', 'category_id' => $category->id, 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['name' => '1', 'attribute_id' => $attribute->id]);
        ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);

        return [$category, $product, $attribute, $option];
    }

    public function test_guide_faq_schema_and_current_product_list_match_visible_content(): void
    {
        [$category, $product] = $this->fixture();
        $response = $this->get('/'.$category->slug)->assertOk()->assertSee('Подберите под вашу сеть')->assertSee('id="stabilizer-guide"', false);
        $nodes = json_decode($response->viewData('schemaJson'), true, 512, JSON_THROW_ON_ERROR);
        $faq = collect($nodes)->firstWhere('@type', 'FAQPage');
        $this->assertCount(6, $faq['mainEntity']);
        foreach ($faq['mainEntity'] as $item) {
            $response->assertSee($item['name'])->assertSee($item['acceptedAnswer']['text']);
        }
        $list = collect($nodes)->firstWhere('@type', 'ItemList');
        $this->assertSame($response->viewData('products')->count(), $list['numberOfItems']);
        $this->assertContains('https://localhost/'.$category->slug.'/'.$product->slug, array_column($list['itemListElement'], 'url'));
        $response->assertDontSee('корректирует частоту')->assertDontSee('Проверка по паспорту');
    }

    public function test_empty_quick_select_keeps_products_and_selected_option_filters_correctly(): void
    {
        [$category, $product, $attribute, $option] = $this->fixture();
        foreach (['', (string) $option->id] as $selected) {
            $response = $this->get('/'.$category->slug.'?'.http_build_query(['attr' => [$attribute->id => [$selected]], 'q' => 'Test stabilizer', 'price_max' => 150, 'in_stock' => 1]))->assertOk();
            $this->assertContains($product->id, $response->viewData('products')->modelKeys());
            $response->assertSee('name="q" value="Test stabilizer"', false)->assertSee('name="price_max" value="150"', false)
                ->assertSee('content="noindex, follow"', false)->assertDontSee('id="stabilizer-guide"', false);
            $this->assertNull(collect(json_decode($response->viewData('schemaJson'), true))->firstWhere('@type', 'FAQPage'));
        }
    }

    public function test_multiple_selected_options_are_preserved_and_ambiguous_power_is_not_relabelled_as_va(): void
    {
        [$category, , $attribute, $option] = $this->fixture();
        $other = AttributeOption::create(['name' => '3', 'attribute_id' => $attribute->id]);
        $power = Attribute::create(['name' => 'Мощность', 'category_id' => $category->id, 'type' => 'select', 'in_filter' => true]);
        AttributeOption::create(['name' => '5', 'attribute_id' => $power->id]);
        $response = $this->get('/'.$category->slug.'?'.http_build_query(['attr' => [$attribute->id => [$option->id, $other->id]], 'page' => 1]))->assertOk();
        $response->assertSee('name="attr['.$attribute->id.'][0]" value="'.$option->id.'"', false)
            ->assertSee('name="attr['.$attribute->id.'][1]" value="'.$other->id.'"', false)->assertDontSee('Полная мощность, ВА');
    }

    public function test_editorial_migration_updates_only_stabilizer_category(): void
    {
        [$category] = $this->fixture();
        $other = Category::create(['name' => 'Other', 'slug' => 'other-seo-section', 'parent_id' => 0, 'content' => 'Keep original', 'is_active' => true]);
        $migration = require database_path('migrations/2026_11_23_210000_improve_stabilizer_category_seo.php');
        $migration->up();
        $category->refresh();
        $this->assertSame('Стабилизаторы напряжения для котла и дома', $category->h1);
        $this->assertLessThanOrEqual(70, mb_strlen(str_replace('%city%', 'Минске', $category->meta_title)));
        $this->assertLessThanOrEqual(180, mb_strlen($category->meta_description));
        $this->assertSame('Keep original', $other->fresh()->content);
    }
}

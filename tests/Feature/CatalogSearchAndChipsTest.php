<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\Catalog\CatalogSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSearchAndChipsTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $slug, ?Category $parent = null): Category
    {
        return Category::updateOrCreate(['slug' => $slug], ['name' => $slug, 'parent_id' => $parent?->id ?? 0, 'is_active' => true]);
    }

    private function product(Category $category, string $slug, array $extra = []): Product
    {
        return Product::create($extra + ['category_id' => $category->id, 'slug' => $slug, 'sku' => $slug, 'name' => $slug,
            'price' => 100, 'is_active' => true, 'is_archived' => false, 'in_stock' => true,
            'availability_status' => Product::AVAILABILITY_IN_STOCK]);
    }

    public function test_search_works_in_other_sections_by_brand_model_and_sku_without_leaving_the_category(): void
    {
        $brand = Brand::create(['name' => 'SearchBrand', 'slug' => 'search-brand']);
        $outside = $this->category('outside-search');
        $this->product($outside, 'outside-search-card', ['name' => 'Model AX-42', 'brand_id' => $brand->id]);
        foreach (['radiatory', 'stabilizatory-napryazheniya', 'tsirkulyatsionnyie', 'filtry'] as $slug) {
            $category = $this->category($slug);
            $match = $this->product($category, $slug.'-model', ['name' => 'Model AX-42', 'sku' => 'SKU-'.$slug, 'brand_id' => $brand->id]);
            $this->product($category, $slug.'-other');
            $this->product($category, $slug.'-archived', ['name' => 'Model AX-42', 'brand_id' => $brand->id, 'is_archived' => true]);
            $this->get('/'.$slug.'?q=SearchBrand%20AX-42')->assertOk()
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$match->id])
                ->assertSee('aria-label="Поиск в разделе"', false)->assertSee('Поиск: SearchBrand AX-42');
            $this->get('/'.$slug.'?q=SKU-'.$slug)->assertOk()
                ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$match->id]);
        }
    }

    public function test_search_includes_active_descendants_and_reduces_brand_and_attribute_options_to_matches(): void
    {
        $root = $this->category('radiatory');
        $child = $this->category('stalnye-radiatory', $root);
        $brand = Brand::create(['name' => 'Matching', 'slug' => 'matching-brand']);
        $otherBrand = Brand::create(['name' => 'Other', 'slug' => 'other-brand']);
        $match = $this->product($child, 'panel-ax-42', ['name' => 'Panel AX-42', 'brand_id' => $brand->id]);
        $other = $this->product($child, 'panel-other', ['name' => 'Other model', 'brand_id' => $otherBrand->id]);
        // Unmanaged attributes still narrow with search; connection now requires manufacturer evidence.
        $attribute = Attribute::create(['category_id' => $child->id, 'name' => 'Цвет', 'type' => 'select', 'in_filter' => true]);
        foreach ([[$match, 'Белый'], [$other, 'Чёрный']] as [$product, $label]) {
            $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => $label]);
            ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        }
        $response = $this->get('/radiatory?q=AX-42')->assertOk();
        $this->assertSame([$match->id], $response->viewData('products')->modelKeys());
        $this->assertSame([$brand->id], $response->viewData('brands')->modelKeys());
        $this->assertSame(['Белый'], $response->viewData('filterAttributes')->first()->options->pluck('name')->all());
        $child->update(['is_active' => false]);
        $this->get('/radiatory?q=AX-42')->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
    }

    public function test_selected_filters_render_without_javascript_and_each_can_be_removed_independently(): void
    {
        $category = $this->category('stabilizatory-napryazheniya');
        $brand = Brand::create(['name' => 'Selected brand', 'slug' => 'selected-brand']);
        $product = $this->product($category, 'stable-model', ['brand_id' => $brand->id]);
        $attribute = Attribute::create(['category_id' => $category->id, 'name' => 'Число фаз', 'type' => 'select', 'in_filter' => true]);
        $option = AttributeOption::create(['attribute_id' => $attribute->id, 'name' => '1']);
        ProductAttributeValue::create(['product_id' => $product->id, 'attribute_id' => $attribute->id, 'option_id' => $option->id]);
        $params = ['q' => 'stable', 'brand' => $brand->id, 'attr' => [$attribute->id => [$option->id]], 'in_stock' => '1', 'price_max' => '150', 'sort' => 'price_asc', 'page' => 2];
        $response = $this->get('/'.$category->slug.'?'.http_build_query($params))->assertOk();
        $response->assertSee('aria-label="Выбранные фильтры"', false)->assertSee('Selected brand')->assertSee('Число фаз · 1')
            ->assertSee('Поиск: stable')->assertSee('Цена до 150 BYN')->assertSee('Сбросить все фильтры');
        foreach ($response->viewData('electricFilterChips') as $chip) {
            parse_str(parse_url($chip['url'], PHP_URL_QUERY) ?? '', $remaining);
            $this->assertArrayNotHasKey('page', $remaining);
            $this->assertSame('price_asc', $remaining['sort']);
            if ($chip['label'] === 'Поиск: stable') {
                $this->assertArrayNotHasKey('q', $remaining);
                $this->assertEquals($params['attr'], $remaining['attr']);
                $this->assertSame((string) $brand->id, $remaining['brand']);
            } elseif ($chip['label'] === 'Число фаз · 1') {
                $this->assertArrayNotHasKey('attr', $remaining);
                $this->assertSame('stable', $remaining['q']);
            }
        }
        $this->assertStringNotContainsString('id="applied-filters"', $response->getContent());
    }

    public function test_search_preserves_filter_inputs_and_escapes_literal_wildcards_and_html(): void
    {
        $category = $this->category('tsirkulyatsionnyie');
        $literal = $this->product($category, 'literal-model', ['name' => 'Pump 50%_!']);
        $this->product($category, 'other-model', ['name' => 'Pump 500xx']);
        $this->get('/tsirkulyatsionnyie?q='.urlencode('50%_!'))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$literal->id]);
        $response = $this->get('/tsirkulyatsionnyie?q='.urlencode('<script>alert(1)</script>').'&in_stock=1&price_min=20&page=2')->assertOk();
        $response->assertSee('name="in_stock" value="1"', false)->assertSee('name="price_min" value="20"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/tsirkulyatsionnyie?q[]=bad')->assertOk();
        $this->assertSame('', CatalogSearch::term(['bad']));
        $this->assertSame(100, mb_strlen(CatalogSearch::term(str_repeat('я', 120))));
    }

    public function test_other_sections_have_self_canonical_pagination_and_noindex_for_filtered_search_results(): void
    {
        $category = $this->category('radiatory');
        for ($i = 1; $i <= 26; $i++) $this->product($category, 'radiator-'.$i);
        $base = 'https://kotlov.by/radiatory';
        $this->get($base.'?page=2')->assertOk()->assertViewHas('robots', null)
            ->assertSee('<link rel="canonical" href="'.$base.'?page=2">', false);
        $this->get($base.'?page=1')->assertOk()->assertSee('<link rel="canonical" href="'.$base.'">', false);
        $this->get($base.'?page=3')->assertNotFound();
        foreach (['q=radiator', 'in_stock=1', 'price_max=200', 'sort=price_asc', 'q=missing-model'] as $query) {
            $this->get($base.'?'.$query)->assertOk()->assertSee('content="noindex, follow"', false)
                ->assertSee('<link rel="canonical" href="'.$base.'">', false);
        }
    }
}

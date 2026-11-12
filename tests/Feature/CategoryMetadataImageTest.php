<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryMetadataImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoryMetadataImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function category(string $slug, array $extra = []): Category
    {
        return Category::create(['slug' => $slug, 'name' => 'Тестовая категория', 'parent_id' => 0, 'is_active' => true, ...$extra]);
    }

    private function product(Category $category, array $extra = []): Product
    {
        return Product::create([
            'category_id' => $category->id, 'name' => 'Тестовый товар', 'slug' => 'photo-product-'.Product::count(),
            'sku' => 'PHOTO-'.Product::count(), 'price' => 100, 'is_active' => true, 'is_archived' => false,
            'in_stock' => true, 'images' => ['img/popular/heatpump.jpg'], ...$extra,
        ]);
    }

    public function test_category_metadata_uses_its_photo_and_dimensions_in_html_and_schema(): void
    {
        $category = $this->category('metadata-category', ['image' => 'img/popular/boiler_img.jpg']);
        $this->product($category);
        $response = $this->get('/metadata-category')->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('property="og:image" content="http://localhost/img/popular/boiler_img.jpg"', $html);
        $this->assertStringContainsString('name="twitter:image" content="http://localhost/img/popular/boiler_img.jpg"', $html);
        $this->assertStringContainsString('property="og:image:width"', $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $html);
        $this->assertStringContainsString('"image":"http://localhost/img/popular/boiler_img.jpg"', $html);
    }

    public function test_missing_category_photo_uses_own_product_before_descendant_or_stock_image(): void
    {
        $category = $this->category('pechi-kaminy', ['image' => 'img/missing-file.jpg']);
        $child = $this->category('metadata-child', ['parent_id' => $category->id]);
        $this->product($child, ['is_featured' => true, 'images' => ['img/popular/boiler_img.jpg']]);
        $product = $this->product($category);
        $image = app(CategoryMetadataImage::class)->resolve($category);
        $this->assertSame('product:'.$product->id, $image['source']);
        $this->assertSame('/img/popular/heatpump.jpg', $image['path']);
    }

    public function test_skips_missing_proxy_files_placeholders_archived_and_unrelated_products(): void
    {
        $category = $this->category('metadata-hub');
        $child = $this->category('metadata-leaf', ['parent_id' => $category->id]);
        $unrelated = $this->category('metadata-unrelated');
        $this->product($category, ['images' => ['product/nonexistent/metadata-test.jpg', 'img/products/product-placeholder.jpg']]);
        $this->product($category, ['is_archived' => true]);
        $this->product($unrelated);
        $product = $this->product($child);
        $image = app(CategoryMetadataImage::class)->resolve($category);
        $this->assertSame('product:'.$product->id, $image['source']);
    }

    public function test_cached_path_is_rendered_on_current_city_domain(): void
    {
        $category = $this->category('metadata-city', ['image' => 'img/popular/boiler_img.jpg']);
        $resolver = app(CategoryMetadataImage::class);
        $this->assertStringContainsString('localhost/', $resolver->resolve($category)['url']);
        app('url')->forceRootUrl('https://lida.kotlov.by');
        app('url')->forceScheme('https');
        $this->assertSame('https://lida.kotlov.by/img/popular/boiler_img.jpg', $resolver->resolve($category)['url']);
    }

    public function test_empty_category_does_not_advertise_generic_logo_or_placeholder(): void
    {
        $category = $this->category('metadata-empty');
        $this->assertNull(app(CategoryMetadataImage::class)->resolve($category));
        $html = $this->get('/metadata-empty')->assertOk()->getContent();
        $this->assertStringNotContainsString('property="og:image"', $html);
        $this->assertStringNotContainsString('name="twitter:image"', $html);
    }
}

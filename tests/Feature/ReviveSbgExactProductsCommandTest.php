<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviveSbgExactProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_repairs_activates_and_links_the_guarded_dormant_card(): void
    {
        $source = IntegrationSource::query()->create([
            'code' => 'onec-sbg',
            'name' => '1С СанБизнесГруп',
            'settings' => ['partner_name' => 'ООО «СанБизнесГруп»'],
            'is_active' => true,
            'update_stock' => true,
        ]);
        $category = Category::query()->create(['name' => 'Дымоходы', 'slug' => 'chimneys', 'parent_id' => 0]);
        $brand = Brand::query()->create(['name' => 'КПД', 'slug' => 'kpd']);
        $target = Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022423',
            'name' => 'КПД КПД ЧЕРНЫЙ Отвод 45* 2мм ф150',
            'slug' => 'old-kpd-elbow',
            'price' => 56,
            'is_active' => false,
            'is_archived' => false,
        ]);
        Product::query()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'KOTLOV-022421',
            'name' => 'КПД Отвод 45* 2мм ф120',
            'slug' => 'image-template-kpd-elbow',
            'images' => ['img/products/kpd/elbow-45.jpg'],
        ]);
        $item = IntegrationProduct::query()->create([
            'integration_source_id' => $source->id,
            'external_id' => 'kpd-elbow-150',
            'external_code' => 'УТ-00008980',
            'name' => 'КПД ЧЁРНЫЙ Отвод 45*2мм ф150',
            'stock_quantity' => 2,
            'match_status' => 'ambiguous',
            'candidates' => [['product_id' => $target->id, 'score' => 0.93]],
        ]);

        $this->artisan('integration:revive-sbg-exact-products')->assertSuccessful();
        $this->assertNull($item->fresh()->product_id);

        $this->artisan('integration:revive-sbg-exact-products', ['--apply' => true])->assertSuccessful();

        $target->refresh();
        $item->refresh();
        $this->assertSame($target->id, $item->product_id);
        $this->assertSame('manual_search', $item->match_method);
        $this->assertTrue($target->is_active);
        $this->assertSame('КПД Отвод 45° 2 мм Ø150, чёрный', $target->name);
        $this->assertSame(['img/products/kpd/elbow-45.jpg'], $target->images);
        $this->assertSame('2', (string) $target->stock_qty);
        $this->assertTrue($target->in_stock);
        $this->assertNotEmpty($target->content);
        $this->assertCount(5, $target->specs);
    }
}

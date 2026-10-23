<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckSupplierLinksCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_flags_links_with_missing_or_reordered_model_numbers(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-kotly'], ['name' => 'Газовые котлы', 'parent_id' => 0, 'is_active' => true]);
        $supplier = Supplier::query()->create(['code' => 'teploshop', 'name' => 'Теплошоп', 'is_active' => true]);
        $link = function (string $card, string $row, string $slug) use ($category, $supplier): Product {
            $product = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 4000, 'name' => $card, 'slug' => $slug]);
            SupplierProduct::query()->create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => $slug,
                'supplier_article_normalized' => $slug, 'supplier_name' => $row, 'match_status' => 'matched', 'match_confidence' => 'model_key']);

            return $product;
        };
        $link('Газовый котел Vaillant turboTEC plus VU 242/5-5 24 кВт', 'Vaillant turboTEC plus VU 242/5-5', 'ok-242');
        $swapped = $link('Газовый котел Vaillant turboTEC plus VU 242/3-5', 'Vaillant turboTEC pro VUW 242/5-3', 'swapped-242');
        $other = $link('Насос UNIPUMP CP 25-40 180', 'CP 25-60 180', 'cp-25-40');

        $this->artisan('catalog:check-supplier-links', ['supplier' => 'teploshop'])
            ->expectsOutputToContain("audit.link_check.order={$swapped->id}:swapped-242:")
            ->expectsOutputToContain("audit.link_check.missing_number={$other->id}:cp-25-40:")
            ->expectsOutputToContain('metric.link_check.ok=1')
            ->assertSuccessful();
    }
}

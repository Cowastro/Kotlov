<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditSupplierOverlapCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_compares_costs_with_other_suppliers_of_the_same_cards(): void
    {
        $category = Category::query()->firstOrCreate(['slug' => 'gazovye-kotly'], ['name' => 'Газовые котлы', 'parent_id' => 0, 'is_active' => true]);
        $card = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 4000, 'name' => 'Котел Vaillant turboTEC pro VUW 242/5-3', 'slug' => 'turbotec-242']);
        $alone = Product::query()->create(['category_id' => $category->id, 'is_active' => true, 'price' => 3000, 'name' => 'Котел BIOTEP 18', 'slug' => 'biotep-18']);
        $mine = Supplier::query()->create(['code' => 'teploshop', 'name' => 'Теплошоп', 'is_active' => true]);
        $other = Supplier::query()->create(['code' => 'thermostudio', 'name' => 'Термостудио', 'is_active' => true]);
        $link = fn (Supplier $s, Product $p, ?float $cost, string $article) => SupplierProduct::query()->create([
            'supplier_id' => $s->id, 'product_id' => $p->id, 'supplier_article' => $article, 'supplier_article_normalized' => $article,
            'price_byn' => $cost, 'match_status' => 'matched', 'raw' => ['retail' => 3990],
        ]);
        $link($mine, $card, 2800, 'A1');
        $link($other, $card, 2500, 'B1');
        $link($mine, $alone, 2000, 'A2');

        $this->artisan('catalog:supplier-overlap', ['supplier' => 'teploshop'])
            ->expectsOutputToContain("audit.overlap.card={$card->id}:turbotec-242:thermostudio:1:2500.00:2800.00:3990.00:")
            // Teploshop is 12% dearer than the other supplier on the shared card.
            ->expectsOutputToContain('audit.overlap.pair=none:thermostudio:1:1:1:1:0:12')
            ->expectsOutputToContain('metric.overlap.exclusive_cards=1')
            ->assertSuccessful();
    }
}

<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class B2bCatalogQuickFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function product(string $sku, array $data = []): Product
    {
        $category = Category::firstOrCreate(['slug' => 'quick-b2b-test'], ['name' => 'Фильтры B2B', 'is_active' => true]);

        return Product::create(array_replace(['category_id' => $category->id, 'name' => 'Товар '.$sku, 'sku' => $sku, 'slug' => 'quick-'.$sku, 'is_active' => true, 'is_archived' => false, 'price' => 100], $data));
    }

    public function test_stock_filter_excludes_stale_unknown_and_other_supplier_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $a = Supplier::create(['code' => 'quick-a', 'name' => 'Первый поставщик', 'is_active' => true]);
        $b = Supplier::create(['code' => 'quick-b', 'name' => 'Второй поставщик', 'is_active' => true]);
        $fresh = $this->product('fresh');
        $stale = $this->product('stale');
        $unknown = $this->product('unknown');
        foreach ([[$fresh, $a, 0, now()], [$fresh, $b, 5, now()], [$stale, $a, 20, now()->subDays(3)], [$unknown, $a, null, now()]] as [$p,$s,$qty,$time]) {
            SupplierProduct::create(['product_id' => $p->id, 'supplier_id' => $s->id, 'supplier_article' => $p->sku, 'price_byn' => 50, 'match_confidence' => 'official_source', 'stock_quantity' => $qty, 'in_stock' => $qty > 0, 'last_synced_at' => $time, 'last_stock_synced_at' => $time]);
        }
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'category' => $fresh->category_id, 'flags' => ['in_stock']]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$fresh->id]);
        $this->get(route('b2b.catalog', ['workspace' => 1, 'category' => $fresh->category_id, 'flags' => ['in_stock'], 'workspace_supplier' => $a->id]))->assertOk()->assertViewHas('products', fn ($products) => $products->isEmpty());
        $this->get(route('b2b.catalog', ['workspace' => 1, 'category' => $fresh->category_id, 'flags' => ['in_stock'], 'workspace_supplier' => $b->id]))->assertOk()->assertViewHas('products', fn ($products) => $products->modelKeys() === [$fresh->id]);
    }

    public function test_flags_price_range_and_selected_project_combine_without_changing_saved_items(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $p = $this->product('matching', ['is_new' => true, 'is_sale' => true, 'price_old' => 150]);
        $this->product('no-price', ['price' => 0]);
        $this->product('regular');
        $selection = CustomerSelection::create(['user_id' => $admin->id, 'name' => 'Объект', 'items' => [$p->id => ['id' => $p->id, 'name' => $p->name, 'price' => 100, 'quantity' => 1]], 'meta' => ['staff_workspace' => true], 'revision' => 1]);
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'selection_id' => $selection->id, 'flags' => ['selected', 'new', 'sale', 'priced'], 'price_min' => 90, 'price_max' => 110]))->assertOk()
            ->assertViewHas('products', fn ($products) => $products->modelKeys() === [$p->id])->assertSee('Быстрые фильтры');
        $this->assertSame(1, $selection->fresh()->revision);
        $this->get(route('b2b.catalog', ['workspace' => 1, 'price_min' => 'invalid']))->assertRedirect();
    }
}

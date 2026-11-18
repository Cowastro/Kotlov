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

    public function test_stock_navigation_counts_only_matching_products_and_keeps_other_stocked_branches(): void
    {
        Product::query()->update(['is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $parent = Category::create(['name' => 'Группа наличия', 'slug' => 'stock-navigation-parent', 'is_active' => true]);
        $ready = Category::create(['parent_id' => $parent->id, 'name' => 'Доступные модели', 'slug' => 'stock-navigation-ready', 'is_active' => true]);
        $empty = Category::create(['parent_id' => $parent->id, 'name' => 'Нет остатков', 'slug' => 'stock-navigation-empty', 'is_active' => true]);
        $other = Category::create(['name' => 'Другой складской раздел', 'slug' => 'stock-navigation-other', 'is_active' => true]);
        $a = Supplier::create(['name' => 'Склад A', 'code' => 'navigation-a', 'is_active' => true]);
        $b = Supplier::create(['name' => 'Склад B', 'code' => 'navigation-b', 'is_active' => true]);
        $first = $this->product('navigation-1', ['category_id' => $ready->id, 'price' => 100]);
        $second = $this->product('navigation-2', ['category_id' => $ready->id, 'price' => 200]);
        $third = $this->product('navigation-3', ['category_id' => $other->id, 'price' => 300]);
        $zero = $this->product('navigation-zero', ['category_id' => $empty->id]);
        $stale = $this->product('navigation-stale', ['category_id' => $empty->id]);
        foreach ([[$first,$a,3,now()], [$first,$b,2,now()], [$second,$a,4,now()], [$third,$b,5,now()], [$zero,$a,0,now()], [$stale,$a,10,now()->subDays(3)]] as [$p,$s,$qty,$time]) {
            SupplierProduct::create(['product_id' => $p->id, 'supplier_id' => $s->id, 'supplier_article' => $p->sku,
                'price_byn' => 50, 'match_confidence' => 'official_source', 'stock_quantity' => $qty, 'in_stock' => $qty > 0,
                'last_synced_at' => $time, 'last_stock_synced_at' => $time]);
        }
        $params = ['workspace' => 1, 'flags' => ['in_stock']];
        $this->actingAs($admin)->get(route('b2b.catalog', $params))->assertOk()
            ->assertViewHas('navigationTotal', 3)
            ->assertViewHas('products', fn ($products) => $products->total() === 3)
            ->assertViewHas('categoryTree', function ($tree) use ($parent, $ready, $empty, $other) {
                $nodes = $tree->keyBy('id');
                return $nodes->keys()->sort()->values()->all() === collect([$parent->id,$other->id])->sort()->values()->all()
                    && $nodes[$parent->id]['count'] === 2 && $nodes[$other->id]['count'] === 1
                    && $nodes[$parent->id]['children']->pluck('id')->all() === [$ready->id]
                    && !$nodes[$parent->id]['children']->contains('id', $empty->id);
            })->assertSee('Все в наличии')->assertDontSee('Нет остатков');
        // Selecting a category after enabling stock narrows the listing, not the navigation universe.
        $this->get(route('b2b.catalog', $params + ['category' => $ready->id]))->assertOk()
            ->assertViewHas('categoryId', $ready->id)->assertViewHas('navigationTotal', 3)
            ->assertViewHas('products', fn ($products) => $products->total() === 2);
        $this->get(route('b2b.catalog', $params + ['workspace_supplier' => $b->id]))->assertOk()
            ->assertViewHas('navigationTotal', 2)->assertViewHas('products', fn ($products) => $products->total() === 2);
        $this->get(route('b2b.catalog', $params + ['price_min' => 150]))->assertOk()
            ->assertViewHas('navigationTotal', 2)->assertViewHas('categoryTree', fn ($tree) => $tree->sum('count') === 2);
        $this->get(route('b2b.catalog', ['workspace' => 1]))->assertOk()->assertSee('Нет остатков');
    }

    public function test_all_products_counter_includes_stocked_goods_outside_the_visible_category_tree(): void
    {
        Product::query()->update(['is_active' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $hidden = Category::create(['name' => 'Скрытый раздел', 'slug' => 'stock-hidden-category', 'is_active' => false]);
        $this->product('hidden-category-stock', ['category_id' => $hidden->id, 'manual_stock_management' => true,
            'in_stock' => true, 'availability_status' => Product::AVAILABILITY_IN_STOCK]);
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'flags' => ['in_stock']]))->assertOk()
            ->assertViewHas('navigationTotal', 1)->assertViewHas('products', fn ($products) => $products->total() === 1);
    }
}

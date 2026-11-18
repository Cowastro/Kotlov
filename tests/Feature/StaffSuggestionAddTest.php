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

class StaffSuggestionAddTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]);
        $category = Category::create(['name' => 'Тест', 'slug' => 'suggest-add-test', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Крепление', 'slug' => 'suggest-add-product', 'sku' => 'ADD-TEST-1', 'price' => 100, 'is_active' => true, 'is_archived' => false]);
        $supplier = Supplier::create(['name' => 'ТД Комплект', 'code' => 'td_komplekt', 'is_active' => true]);
        SupplierProduct::create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'MIN-8-MULT-4',
            'price_byn' => 60, 'in_stock' => true, 'stock_quantity' => 100, 'stock_status' => 'in_stock',
            'match_confidence' => 'official_source', 'last_synced_at' => now(), 'last_stock_synced_at' => now(),
            'raw' => ['prop_quantity_min' => 8, 'prop_multiplicity' => 4, 'prop_multiplicity_box' => 100, 'prop_unit' => 'шт.'],
        ]);
        $this->actingAs($manager);
        $draft = $this->postJson(route('b2b.workspace.store'), ['name' => 'Быстрое добавление'])->assertOk()->json();
        $offer = $this->getJson(route('b2b.catalog.suggest', ['workspace' => 1, 'q' => $product->sku]))->assertOk()
            ->assertJsonPath('items.0.procurement.order_rules.minimum', 8)
            ->assertJsonPath('items.0.procurement.order_rules.multiple', 4)->json('items.0.procurement');

        return [$manager, $product, CustomerSelection::findOrFail($draft['id']), $offer['supplier_key']];
    }

    private function add(CustomerSelection $draft, Product $product, string $key, int $quantity, int $revision)
    {
        return $this->putJson(route('b2b.workspace.update', ['selection' => $draft->id, 'product' => $product->id]), [
            'operation' => 'add', 'quantity' => $quantity, 'revision' => $revision, 'supplier_key' => $key,
            'purchase_price' => 0.01, 'order_rules' => [],
        ]);
    }

    public function test_addition_enforces_current_supplier_rules_and_accumulates_without_losing_prices(): void
    {
        [, $product, $draft, $key] = $this->fixture();
        $this->add($draft, $product, $key, 7, 1)->assertUnprocessable();
        $this->add($draft, $product, $key, 10, 1)->assertUnprocessable();
        $this->assertSame([], $draft->fresh()->items);
        $this->add($draft, $product, $key, 8, 1)->assertOk()->assertJsonPath('items.0.quantity', 8)->assertJsonPath('items.0.workspace_purchase.purchase_price', 60);
        $this->add($draft, $product, $key, 4, 2)->assertOk()->assertJsonPath('items.0.quantity', 12)->assertJsonPath('total', 1200);
        $this->add($draft, $product, $key, 4, 2)->assertConflict();
        $items = $draft->fresh()->items;
        $items[$product->id]['price'] = 90;
        $items[$product->id]['client_pricing'] = ['mode' => 'price', 'base_price' => 100];
        $items[$product->id]['workspace_note'] = 'Согласовано с клиентом';
        $draft->update(['items' => $items]);
        $this->add($draft, $product, $key, 4, 3)->assertOk()->assertJsonPath('items.0.quantity', 16)
            ->assertJsonPath('items.0.price', 90)->assertJsonPath('items.0.workspace_note', 'Согласовано с клиентом')->assertJsonPath('total', 1440);
        $this->assertCount(1, $draft->fresh()->items);
    }

    public function test_invalid_quantity_and_changed_supplier_leave_the_selection_unchanged(): void
    {
        [, $product, $draft, $key] = $this->fixture();
        $this->add($draft, $product, $key, 0, 1)->assertUnprocessable();
        $this->add($draft, $product, $key, 8, 1)->assertOk();
        $this->add($draft, $product, $key, 992, 2)->assertUnprocessable();
        $this->add($draft, $product, 'a-different-supplier', 4, 2)->assertUnprocessable();
        $this->assertSame(8, $draft->fresh()->items[$product->id]['quantity']);
        $this->assertSame(2, $draft->fresh()->revision);
    }

    public function test_clients_and_foreign_managers_cannot_add_to_another_selection(): void
    {
        [, $product, $draft, $key] = $this->fixture();
        $this->actingAs(User::factory()->create(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true]));
        $this->add($draft, $product, $key, 8, 1)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'client', 'is_active' => true]));
        $this->add($draft, $product, $key, 8, 1)->assertForbidden();
        $this->assertSame([], $draft->fresh()->items);
    }
}

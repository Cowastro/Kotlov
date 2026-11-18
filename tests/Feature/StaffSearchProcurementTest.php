<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\Category;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\StaffCatalogWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffSearchProcurementTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $category = Category::create(['name' => 'Подбор', 'slug' => 'rich-search-test', 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => 'Насос для подсказки', 'slug' => 'rich-search-product', 'sku' => 'RICH-SEARCH-1', 'price' => 100, 'is_active' => true, 'is_archived' => false]);
    }

    private function staff(array $attributes = []): User
    {
        return User::factory()->create(array_replace(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true], $attributes));
    }

    private function offer(Product $product, string $name, ?float $quantity, float $cost, bool $fresh = true): void
    {
        $supplier = Supplier::create(['name' => $name, 'code' => 'rich-search-'.Supplier::count(), 'is_active' => true]);
        SupplierProduct::create([
            'supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'RICH-SUP-'.Supplier::count(),
            'price_byn' => $cost, 'stock_quantity' => $quantity, 'in_stock' => true, 'stock_status' => 'in_stock',
            'match_confidence' => 'official_source', 'last_synced_at' => now(), 'last_stock_synced_at' => $fresh ? now() : now()->subDays(10),
        ]);
    }

    private function suggest(array $query = [])
    {
        return $this->getJson(route('b2b.catalog.suggest', array_replace(['workspace' => 1, 'mode' => 'reference', 'q' => 'RICH-SEARCH-1'], $query)));
    }

    public function test_staff_roles_receive_only_the_selected_offer_financial_summary(): void
    {
        $product = $this->product();
        $this->offer($product, 'Дешевле без количества', null, 50);
        $this->offer($product, 'Подтверждённый склад', 3, 60);
        foreach ([['role' => 'admin', 'is_crm_user' => false], ['role' => 'manager'], ['role' => 'sales_manager']] as $attributes) {
            $this->actingAs($this->staff($attributes));
            $response = $this->suggest()->assertOk()->assertJsonPath('items.0.price', 100)
                ->assertJsonPath('items.0.procurement.purchase_price', 60)
                ->assertJsonPath('items.0.procurement.margin', 40)
                ->assertJsonPath('items.0.procurement.margin_percent', 40)
                ->assertJsonPath('items.0.procurement.supplier', 'Подтверждённый склад')
                ->assertJsonPath('items.0.procurement.stock_state', 'known')
                ->assertJsonPath('items.0.procurement.stock_label', '3 шт.');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $keys = array_keys($response->json('items.0.procurement'));
            sort($keys);
            $this->assertSame(['margin', 'margin_percent', 'order_rules', 'purchase_price', 'stock_label', 'stock_state', 'supplier', 'supplier_key'], $keys);
        }
    }

    public function test_saved_supplier_is_used_and_unconfirmed_stock_is_not_invented(): void
    {
        $product = $this->product();
        $this->offer($product, 'Свежий склад', 3, 60);
        $this->offer($product, 'Выбранный склад', 99, 55, false);
        $manager = $this->staff();
        $offer = app(StaffCatalogWorkspace::class)->offers($product, $manager)->firstWhere('supplier', 'Выбранный склад');
        $draft = CustomerSelection::create(['user_id' => $manager->id, 'name' => 'Выбор менеджера', 'meta' => ['staff_workspace' => true], 'items' => [$product->id => ['workspace_purchase' => ['supplier_key' => $offer['supplier_key']]]]]);
        $this->actingAs($manager);
        $this->suggest(['selection_id' => $draft->id])->assertOk()
            ->assertJsonPath('items.0.procurement.supplier', 'Выбранный склад')
            ->assertJsonPath('items.0.procurement.purchase_price', 55)
            ->assertJsonPath('items.0.procurement.stock_state', 'unknown')
            ->assertJsonPath('items.0.procurement.stock_label', 'Остаток не подтверждён');
        $this->actingAs($this->staff());
        $this->suggest(['selection_id' => $draft->id])->assertNotFound();
    }

    public function test_missing_procurement_is_null_and_negative_margin_is_preserved(): void
    {
        $product = $this->product();
        $this->actingAs($this->staff());
        $this->suggest()->assertOk()->assertJsonPath('items.0.procurement.purchase_price', null)
            ->assertJsonPath('items.0.procurement.margin', null)->assertJsonPath('items.0.procurement.supplier', null)
            ->assertJsonPath('items.0.procurement.stock_label', 'Остаток не подтверждён');
        $this->offer($product, 'Дорогой поставщик', 1, 120);
        $this->suggest()->assertOk()->assertJsonPath('items.0.procurement.margin', -20)
            ->assertJsonPath('items.0.procurement.margin_percent', -20);
    }

    public function test_partner_and_customer_preview_never_receive_procurement_fields(): void
    {
        $product = $this->product();
        $this->offer($product, 'Внутренний поставщик', 5, 61.23);
        $partner = User::factory()->create(['role' => 'client', 'client_type' => 'wholesale', 'b2b_approved' => true, 'is_active' => true]);
        B2bClient::create(['user_id' => $partner->id, 'status' => 'approved', 'partner_type' => 'wholesale', 'company_name' => 'Клиент', 'allowed_integration_source_ids' => []]);
        $this->actingAs($partner);
        $response = $this->suggest(['workspace' => 0])->assertOk()->assertJsonCount(1, 'items');
        $this->assertArrayNotHasKey('procurement', $response->json('items.0'));
        $this->assertStringNotContainsString('purchase_price', $response->getContent());
        $this->assertStringNotContainsString('margin', $response->getContent());
        $this->assertStringNotContainsString('Внутренний поставщик', $response->getContent());
        $this->get(route('b2b.catalog', ['mode' => 'reference', 'q' => $product->sku]))->assertOk()
            ->assertDontSee('purchase_price', false)->assertDontSee('item.procurement', false);
        $this->suggest()->assertForbidden();
        $this->actingAs($this->staff(['role' => 'admin']));
        $preview = $this->suggest(['workspace' => 0, 'b2b-preview' => 1])->assertOk();
        $this->assertArrayNotHasKey('procurement', $preview->json('items.0'));
    }
}

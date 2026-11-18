<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\StaffCatalogWorkspace;
use App\Services\Suppliers\SupplierOrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffCatalogWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(array $attributes = []): User
    {
        return User::factory()->create(array_replace(['role' => 'manager', 'is_crm_user' => true, 'is_active' => true], $attributes));
    }

    private function product(): Product
    {
        $category = Category::firstOrCreate(['slug' => 'workspace-test'], ['name' => 'Насосы для подбора', 'is_active' => true]);

        return Product::create(['category_id' => $category->id, 'name' => 'Насос для большого подбора', 'slug' => 'workspace-pump', 'sku' => 'SUP-TEST-150', 'price' => 12.5, 'is_active' => true, 'is_archived' => false]);
    }

    public function test_crm_switch_opens_an_editable_workspace_without_changing_partner_permissions(): void
    {
        $manager = $this->manager();
        $product = $this->product();
        $this->actingAs($manager)->get(route('b2b.workspace'))->assertRedirect(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']));
        $this->get(route('b2b.catalog', ['workspace' => 1]))->assertOk()
            ->assertSee('Подбор для клиента')->assertSee('data-workspace-product="'.$product->id.'"', false)
            ->assertDontSee('<div class="b2b-order-bar"', false)->assertDontSee('data-workspace-save disabled', false);
        $this->get(route('b2b.catalog'))->assertForbidden();
        $this->get(route('b2b.catalog', ['b2b-preview' => 1]))->assertOk()->assertDontSee('class="b2b-workspace-save"', false);
        $this->assertStringContainsString(route('b2b.workspace'), view('filament.admin.workspace-switch')->render());
        $this->getJson(route('b2b.catalog.suggest', ['workspace' => 1, 'q' => 'SUP-TEST-150']))
            ->assertOk()->assertJsonPath('items.0.sku', 'SUP-TEST-150');
    }

    public function test_admin_can_enter_editable_workspace_from_storefront_without_partner_status(): void
    {
        $admin = $this->manager(['role' => 'admin', 'is_crm_user' => false]);
        $this->actingAs($admin);
        $html = view('partials.amerce-header', ['navCategories' => collect(), 'navCategoryUrls' => [], 'navBrands' => collect()])->render();
        $this->assertStringContainsString('B2B · подбор и заявки', $html);
        $this->assertStringContainsString('Администратор', $html);
        $this->assertStringContainsString('href="'.route('b2b.workspace').'"', $html);
        $this->get(route('account'))->assertOk()->assertSee('Открыть B2B-подбор')->assertSee('входные цены');
        $this->get(route('b2b.workspace'))->assertRedirect(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']));
        $this->get(route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']))->assertOk()
            ->assertSee('Режим администратора')->assertSee('data-workspace-new', false)->assertDontSee('Только просмотр');
        $this->assertFalse($admin->fresh()->isB2B());
    }

    public function test_internal_entry_and_status_are_not_exposed_to_retail_or_inactive_users(): void
    {
        foreach ([['role' => 'client', 'is_crm_user' => false], ['role' => 'admin', 'is_active' => false]] as $attributes) {
            $this->actingAs($this->manager($attributes));
            $html = view('partials.amerce-header', ['navCategories' => collect(), 'navCategoryUrls' => [], 'navBrands' => collect()])->render();
            $this->assertStringNotContainsString('B2B · подбор и заявки', $html);
            $this->assertStringNotContainsString('account-workspace-role', $html);
            $this->assertStringNotContainsString('href="'.route('b2b.workspace').'"', $html);
            $this->get(route('b2b.workspace'))->assertForbidden();
        }
    }

    public function test_workspace_is_not_available_to_retail_partners_inactive_or_non_crm_staff(): void
    {
        foreach ([['role' => 'client', 'is_crm_user' => false], ['role' => 'manager', 'is_crm_user' => false], ['is_active' => false]] as $attributes) {
            $this->actingAs($this->manager($attributes))->get(route('b2b.workspace'))->assertForbidden();
            $this->get(route('b2b.catalog', ['workspace' => 1]))->assertForbidden();
            $this->postJson(route('b2b.workspace.store'), ['name' => 'Нельзя'])->assertForbidden();
        }
    }

    public function test_positions_save_to_the_staff_account_with_server_prices_and_leave_cart_untouched(): void
    {
        $manager = $this->manager();
        $product = $this->product();
        $cart = [999 => ['id' => 999, 'name' => 'Существующая корзина', 'quantity' => 2, 'price' => 7]];
        $this->actingAs($manager)->withSession(['cart' => $cart, 'cart_owner_id' => $manager->id]);
        $draft = $this->postJson(route('b2b.workspace.store'), ['name' => 'Котельная · Алексей'])->assertOk()->json();
        $this->assertSame(1, $draft['revision']);
        $url = route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => $product->id]);
        $saved = $this->putJson($url, ['quantity' => 150, 'revision' => 1, 'price' => 0.01])->assertOk()
            ->assertSessionHas('cart', $cart)->assertJsonPath('items.0.price', 12.5)->assertJsonPath('total', 1875)->json();
        $this->putJson($url, ['quantity' => 151, 'revision' => $saved['revision']])->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.quantity', 151);
        $selection = CustomerSelection::findOrFail($draft['id']);
        $this->assertSame($manager->id, $selection->user_id);
        $this->assertSame(151, $selection->items[$product->id]['quantity']);
        $this->get(route('selections.show', [$selection, 'print' => 1]))->assertOk()->assertSee('Котельная · Алексей')->assertSee('SUP-TEST-150');
        $this->flushSession();
        $this->get(route('b2b.catalog', ['workspace' => 1]))->assertOk()->assertViewHas('workspaceState', fn ($state) => $state['id'] === $selection->id && $state['items'][0]['quantity'] === 151);
    }

    public function test_foreign_selections_cannot_be_viewed_or_changed(): void
    {
        $owner = $this->manager();
        $draft = $this->actingAs($owner)->postJson(route('b2b.workspace.store'), ['name' => 'Чужой подбор'])->json();
        $this->actingAs($this->manager())->get(route('b2b.catalog', ['workspace' => 1, 'selection_id' => $draft['id']]))->assertNotFound();
        $this->putJson(route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => 1]), ['quantity' => 1, 'revision' => 1])->assertNotFound();
    }

    public function test_conflicting_tabs_and_invalid_quantities_cannot_overwrite_a_saved_selection(): void
    {
        $product = $this->product();
        $draft = $this->actingAs($this->manager())->postJson(route('b2b.workspace.store'), ['name' => 'Проверка версий'])->json();
        $url = route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => $product->id]);
        $this->putJson($url, ['quantity' => 5, 'revision' => 1])->assertOk();
        $this->putJson($url, ['quantity' => 2, 'revision' => 1])->assertConflict();
        $this->putJson($url, ['quantity' => 1000, 'revision' => 2])->assertUnprocessable();
        $this->putJson($url, ['quantity' => 2.5, 'revision' => 2])->assertUnprocessable();
        $this->assertSame(5, CustomerSelection::find($draft['id'])->items[$product->id]['quantity']);
        $product->update(['is_archived' => true]);
        $this->putJson($url, ['quantity' => 6, 'revision' => 2])->assertNotFound();
        $this->putJson($url, ['quantity' => 0, 'revision' => 2])->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_page_size_is_bounded_and_reference_prices_can_be_sorted(): void
    {
        $product = $this->product();
        $this->actingAs($this->manager())->get(route('b2b.catalog', ['workspace' => 1, 'category' => $product->category_id, 'per_page' => 100, 'sort' => 'price_desc']))
            ->assertOk()->assertViewHas('products', fn ($products) => $products->perPage() === 100);
        $this->get(route('b2b.catalog', ['workspace' => 1, 'per_page' => 100000]))->assertOk()->assertViewHas('products', fn ($products) => $products->perPage() === 30);
    }

    public function test_admin_sees_supplier_costs_stock_and_margin_and_can_choose_an_offer(): void
    {
        $product = $this->product();
        foreach (['Первый поставщик' => [2.5, 30], 'Второй поставщик' => [3.75, null]] as $name => [$cost, $stock]) {
            $supplier = Supplier::create(['code' => 'workspace-'.Supplier::count(), 'name' => $name, 'is_active' => true]);
            SupplierProduct::create([
                'supplier_id' => $supplier->id, 'product_id' => $product->id,
                'supplier_article' => 'VENDOR-'.Supplier::count(), 'price_byn' => $cost,
                'in_stock' => $stock !== null, 'stock_quantity' => $stock, 'stock_status' => 'in_stock',
                'match_confidence' => 'official_source', 'warehouse_name' => 'Минск, склад тест',
                'last_synced_at' => now(), 'last_stock_synced_at' => now(),
            ]);
        }
        $admin = $this->manager(['role' => 'admin']);
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'q' => 'SUP-TEST-150']))->assertOk()
            ->assertSee('Входная / маржа')->assertSee('Первый поставщик')->assertSee('Второй поставщик')
            ->assertSee('Остаток уточняется')->assertSee('Минск, склад тест')->assertSee('30 шт.');
        $offers = app(StaffCatalogWorkspace::class)->offers($product->fresh(), $admin);
        $offer = $offers->firstWhere('supplier', 'Второй поставщик');
        $draft = $this->postJson(route('b2b.workspace.store'), ['name' => 'Подбор для клиента'])->json();
        $saved = $this->putJson(route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => $product->id]), [
            'quantity' => 150, 'revision' => 1, 'supplier_key' => $offer['supplier_key'], 'purchase_price' => 0.01,
        ])->assertOk()->assertJsonPath('items.0.workspace_purchase.purchase_price', 3.75)
            ->assertJsonPath('items.0.workspace_purchase.stock_quantity', null)
            ->assertJsonPath('procurement.total', 562.5)->assertJsonPath('procurement.margin', 1312.5)->assertJsonPath('procurement.margin_percent', 70)->json();
        $this->get($saved['print_url'])->assertOk()->assertDontSee('Второй поставщик')->assertDontSee('562,50');
        $selection = CustomerSelection::find($draft['id']);
        $sanitized = app(StaffCatalogWorkspace::class)->state($selection, $this->manager());
        $this->assertNull($sanitized['procurement']);
        $this->assertArrayNotHasKey('workspace_purchase', $sanitized['items'][0]);
        $this->actingAs($this->manager())->get(route('b2b.catalog', ['workspace' => 1, 'q' => 'SUP-TEST-150']))->assertOk()
            ->assertDontSee('Первый поставщик')->assertDontSee('data-workspace-cost', false);
    }

    public function test_a_large_selection_keeps_all_positions_when_one_line_changes(): void
    {
        $manager = $this->manager();
        $product = $this->product();
        $items = [];
        for ($i = 1; $i <= 150; $i++) {
            $line = Product::create([
                'category_id' => $product->category_id, 'name' => 'Позиция большого подбора '.$i,
                'slug' => 'large-selection-'.$i, 'sku' => 'LARGE-'.$i, 'price' => 2,
                'is_active' => true, 'is_archived' => false,
            ]);
            $items[$line->id] = ['id' => $line->id, 'name' => $line->name, 'sku' => $line->sku, 'quantity' => 1, 'price' => 2];
        }
        $selection = CustomerSelection::create(['user_id' => $manager->id, 'name' => '150 позиций', 'items' => $items, 'meta' => ['staff_workspace' => true], 'revision' => 1]);
        $this->actingAs($manager)->putJson(route('b2b.workspace.update', ['selection' => $selection->id, 'product' => $product->id]), ['revision' => 1, 'quantity' => 3])
            ->assertOk()->assertJsonCount(151, 'items');
        $selection->refresh();
        $this->assertCount(151, $selection->items);
        $this->assertSame('LARGE-150', $selection->items[$line->id]['sku']);
        $this->get(route('selections.show', [$selection, 'print' => 1]))->assertOk()->assertSee('LARGE-150');
    }

    public function test_missing_retail_price_is_not_treated_as_a_free_product_or_a_confirmed_loss(): void
    {
        $product = $this->product();
        $product->update(['price' => 0, 'manual_purchase_price_byn' => 8, 'manual_price_updated_at' => now()]);
        $draft = $this->actingAs($this->manager(['role' => 'admin']))->postJson(route('b2b.workspace.store'), ['name' => 'Цена уточняется'])->json();
        $saved = $this->putJson(route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => $product->id]), ['revision' => 1, 'quantity' => 3])
            ->assertOk()->assertJsonPath('procurement.total', 24)->assertJsonPath('procurement.margin', 0)
            ->assertJsonPath('procurement.margin_percent', null)->assertJsonPath('procurement.margin_unconfirmed', 1)->json();
        $this->get($saved['print_url'])->assertOk()->assertSee('Уточняется')->assertSee('В итог не включены позиции с неуточнённой ценой.');
    }

    public function test_an_existing_own_selection_can_be_reopened_and_extended_from_the_catalogue(): void
    {
        $admin = $this->manager(['role' => 'admin']);
        $product = $this->product();
        $selection = CustomerSelection::create(['user_id' => $admin->id, 'name' => 'Ранее сохранённый подбор', 'items' => [], 'revision' => 1]);
        $this->actingAs($admin)->get(route('b2b.catalog', ['workspace' => 1, 'selection_id' => $selection->id]))->assertOk()
            ->assertViewHas('workspaceState', fn ($state) => $state['id'] === $selection->id);
        $this->putJson(route('b2b.workspace.update', ['selection' => $selection->id, 'product' => $product->id]), ['revision' => 1, 'quantity' => 2])->assertOk();
        $this->assertTrue($selection->fresh()->meta['staff_workspace']);
        $this->assertSame(2, $selection->fresh()->items[$product->id]['quantity']);
    }

    public function test_supplier_pack_rules_are_confirmed_fields_and_survive_saving(): void
    {
        $admin = $this->manager(['role' => 'admin']);
        $product = $this->product();
        $supplier = Supplier::create(['code' => 'td_komplekt', 'name' => 'ТД Комплект', 'is_active' => true]);
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id, 'supplier_article' => 'TEST-MIN', 'price_byn' => 5, 'match_confidence' => 'official_source', 'raw' => ['prop_quantity_min' => '2', 'prop_multiplicity' => '0,5', 'prop_multiplicity_box' => '—', 'prop_unit' => 'шт.']]);
        $rules = SupplierOrderRules::fromOffer($offer);
        $this->assertSame(['minimum' => 2.0, 'multiple' => 0.5, 'unit' => 'шт.'], $rules);
        $draft = $this->actingAs($admin)->postJson(route('b2b.workspace.store'), ['name' => 'С упаковкой'])->json();
        $this->putJson(route('b2b.workspace.update', ['selection' => $draft['id'], 'product' => $product->id]), ['revision' => 1, 'quantity' => 2])
            ->assertOk()->assertJsonPath('items.0.order_rules.minimum', 2)->assertJsonPath('items.0.order_rules.multiple', 0.5);
        $offer->raw = [];
        $this->assertSame([], SupplierOrderRules::fromOffer($offer));
    }
}

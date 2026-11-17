<?php

namespace Tests\Feature;

use App\Models\B2bClient;
use App\Models\B2bOrder;
use App\Models\B2bPricingRule;
use App\Models\B2bSavedCart;
use App\Models\Category;
use App\Models\CustomerCart;
use App\Models\CustomerSelection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\User;
use App\Services\CartSelection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\PaginationState;
use Illuminate\Pagination\Paginator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MyRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiting_print_and_excel_use_only_shortage_and_keep_original_demand_unchanged(): void
    {
        [$user, $selection, $offer, $item] = $this->fixture();
        $this->actingAs($user)->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print', 'scope' => 'waiting']))
            ->assertOk()->assertSee('Недостающее количество')->assertSee('Не является резервом.')->assertSee('480,00');
        $response = $this->get(route('b2b.selections.export', [$selection, 'scope' => 'waiting']))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'request-export-');
        try {
            file_put_contents($file, $response->streamedContent());
            $book = IOFactory::load($file);
            $sheet = $book->getActiveSheet();
            $this->assertSame(4, $sheet->getCell('D4')->getValue());
            $this->assertSame(480.0, $sheet->getCell('E4')->getValue());
            $this->assertStringContainsString('Недостающее количество', $sheet->getCell('A1')->getValue());
            $this->assertStringContainsString('цены из сохранённой заявки', $sheet->getCell('A2')->getValue());
            $this->assertArrayHasKey('A1:F1', $sheet->getMergeCells());
            $book->disconnectWorksheets();
        } finally {
            @unlink($file);
        }
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print', 'scope' => 'ready']))
            ->assertOk()->assertSee('Доступное количество')->assertSee('720,00');
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
        $this->assertSame(2, $selection->fresh()->revision);
        $this->assertDatabaseCount('b2b_order_reservations', 0);
    }

    public function test_split_export_rejects_empty_or_invalid_scope_and_warns_about_unchecked_rows(): void
    {
        [$user, $selection, $offer, $item] = $this->fixture();
        $this->actingAs($user)->get(route('b2b.selections.export', [$selection, 'scope' => 'waiting']))
            ->assertRedirect(route('b2b.selections.show', $selection))->assertSessionHas('error');
        $this->getJson(route('b2b.selections.export', [$selection, 'scope' => 'made-up']))->assertUnprocessable();
        $this->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        $items = $selection->fresh()->items;
        $items[999] = ['id' => 999, 'name' => 'Ещё не проверяли', 'sku' => 'UNKNOWN', 'price' => 1, 'quantity' => 5];
        $selection->update(['items' => $items]);
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print', 'scope' => 'waiting']))
            ->assertOk()->assertSee('Не включено непроверенных позиций: 1.')->assertDontSee('Ещё не проверяли');
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'quantity', 'revision' => 2, 'product_id' => $item['id'], 'quantity' => 11])->assertOk();
        $this->get(route('b2b.selections.export', [$selection, 'scope' => 'waiting']))->assertSessionHas('error');
        $this->get(route('b2b.selections.export', [$selection, 'format' => 'print']))->assertOk()->assertSee('Ещё не проверяли');
        $this->assertSame(11, $selection->fresh()->items[$item['id']]['quantity']);
    }

    public function test_request_search_filters_own_and_legacy_names_and_keeps_pagination_query(): void
    {
        [$user,$selection] = $this->fixture();
        $foreign = User::factory()->create();
        CustomerSelection::create(['user_id' => $foreign->id, 'name' => 'Лесная чужой объект', 'items' => []]);
        for ($i = 1; $i <= 22; $i++) {
            CustomerSelection::create(['user_id' => $user->id, 'name' => 'Лесная заявка '.$i, 'items' => []]);
        }
        B2bSavedCart::create(['user_id' => $user->id, 'name' => 'Лесная старая корзина', 'items' => [], 'items_count' => 0, 'total' => 0]);
        B2bSavedCart::create(['user_id' => $user->id, 'name' => 'Другая старая корзина', 'items' => [], 'items_count' => 0, 'total' => 0]);
        // Livewire/Filament changes these process-wide resolvers. The account
        // page must use its own request path and page, even after such a component.
        Paginator::currentPageResolver(fn () => 999);
        Paginator::currentPathResolver(fn () => '/unrelated-component');
        try {
            $this->actingAs($user)->get(route('selections.index', ['q' => 'Лесная']))->assertOk()
                ->assertSee('Лесная старая корзина')->assertDontSee('Другая старая корзина')->assertDontSee('Лесная чужой объект')
                ->assertDontSee('Объект №1')->assertViewHas('selections', fn ($paginator) => $paginator->total() === 22 && $paginator->lastPage() === 2)->assertSee('page=2')->assertSee('q=%D0%9B%D0%B5%D1%81%D0%BD%D0%B0%D1%8F');
            $this->get(route('selections.index', ['q' => 'Лесная', 'page' => 2]))->assertOk()->assertViewHas('selections', fn ($paginator) => $paginator->count() === 2);
            $this->get(route('selections.index', ['q' => 'Нет такого объекта']))->assertOk()->assertSee('По этому названию заявок не найдено.');
            $this->get(route('selections.index', ['q' => 'Лесная старая']))->assertOk()->assertSee('Лесная старая корзина')->assertDontSee('По этому названию заявок не найдено.');
        } finally {
            PaginationState::resolveUsing($this->app);
        }
    }

    private function fixture(int $stock = 6): array
    {
        $user = User::factory()->create(['is_active' => true]);
        B2bClient::create(['user_id' => $user->id, 'status' => 'approved', 'partner_type' => 'wholesale',
            'company_name' => 'Тест заявок', 'allow_all_partner_suppliers' => true]);
        $category = Category::create(['name' => 'Насосы', 'slug' => 'request-pumps', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Склад', 'code' => 'request-test', 'is_active' => true,
            'b2b_partner_enabled' => true, 'b2b_require_positive_stock' => true, 'b2b_stock_max_age_hours' => 24]);
        $supplier->b2bPricingRules()->create(['name' => 'Цена', 'scope_type' => B2bPricingRule::SCOPE_SUPPLIER,
            'calculation_type' => B2bPricingRule::TYPE_TARGET_MARGIN, 'value' => 20, 'minimum_profit_byn' => 0,
            'minimum_margin_percent' => 10, 'rounding_step_byn' => 1, 'minimum_retail_discount_percent' => 0,
            'priority' => 100, 'status' => B2bPricingRule::STATUS_ACTIVE]);
        $product = Product::withoutEvents(fn () => Product::create(['category_id' => $category->id, 'sku' => 'PUMP-1', 'slug' => 'request-pump',
            'name' => 'Насос для объекта', 'price' => 200, 'is_active' => true, 'is_archived' => false]));
        $offer = SupplierProduct::create(['supplier_id' => $supplier->id, 'product_id' => $product->id,
            'supplier_article' => 'SUP-1', 'price_byn' => 100, 'stock_quantity' => $stock, 'in_stock' => $stock > 0,
            'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock', 'match_status' => 'matched',
            'last_stock_synced_at' => now(), 'last_synced_at' => now()]);
        $item = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'slug' => $product->slug,
            'category_slug' => $category->slug, 'price' => 120, 'quantity' => 10, 'pricing_type' => 'b2b',
            'supplier_product_id' => $offer->id, 'supply_option' => 'supplier:'.$offer->id, 'selection_note' => 'Этаж 2'];
        $selection = CustomerSelection::create(['user_id' => $user->id, 'name' => 'Объект №1', 'revision' => 1, 'items' => [$product->id => $item]]);

        return [$user, $selection, $offer, $item];
    }

    public function test_shortage_is_saved_while_only_ready_quantity_enters_cart_and_old_cart_is_preserved(): void
    {
        [$user, $selection, $offer, $item] = $this->fixture();
        $old = [999 => ['id' => 999, 'name' => 'Другая работа', 'price' => 5, 'quantity' => 2]];
        $this->actingAs($user)->withSession(['cart' => $old, 'cart_owner_id' => $user->id])->post(route('requests.prepare', $selection), ['revision' => 1])
            ->assertRedirect(route('cart'));
        $this->assertSame(10, $selection->fresh()->items[$item['id']]['quantity']);
        $allocation = $selection->fresh()->meta['availability'][$item['id']];
        $this->assertSame(6, $allocation['ready']);
        $this->assertSame(4, $allocation['waiting']);
        $this->assertSame(6, session('cart')[$item['id']]['quantity']);
        $this->assertCount(1, CartSelection::items(session('cart')), 'Prepared lines must be selected for checkout.');
        $this->assertSame(125.0, (float) session('cart')[$item['id']]['price']);
        $this->assertSame('Этаж 2', session('cart')[$item['id']]['selection_note']);
        $this->assertNull(session('cart_selection_id'), 'A partial cart must not overwrite the full request.');
        $this->assertSame($old, CustomerSelection::where('name', 'like', 'Корзина до оформления%')->sole()->items);
        $this->assertSame(6, CustomerCart::where('user_id', $user->id)->sole()->items[$item['id']]['quantity']);
        $this->assertDatabaseCount('b2b_order_reservations', 0);
        $this->get(route('b2b.selections.show', $selection))->assertOk()->assertSee('Доступно 6')->assertSee('Ожидается 4');
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'quantity', 'revision' => 2, 'product_id' => $item['id'], 'quantity' => 11])
            ->assertOk()->assertJsonPath('state.items.0.allocation', null);
        $this->post(route('requests.prepare', $selection), ['revision' => 2])->assertConflict();
        $this->assertSame(11, $selection->fresh()->items[$item['id']]['quantity']);
    }

    public function test_unavailable_stale_or_disallowed_offer_never_becomes_a_reservation_or_erases_cart(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $offer->update(['last_stock_synced_at' => now()->subDays(5), 'last_synced_at' => now()->subDays(5)]);
        $old = [77 => ['id' => 77, 'name' => 'Сохранить', 'quantity' => 1, 'price' => 3]];
        $this->actingAs($user)->withSession(['cart' => $old, 'cart_owner_id' => $user->id])->post(route('requests.prepare', $selection), ['revision' => 1])
            ->assertSessionHas('error');
        $this->assertSame($old, session('cart'));
        $this->assertSame(10, $selection->fresh()->meta['availability'][$item['id']]['waiting']);
        $this->assertDatabaseCount('b2b_order_reservations', 0);
        $foreign = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($foreign)->post(route('requests.check', $selection), ['revision' => 2])->assertNotFound();
        $this->post(route('requests.prepare', $selection), ['revision' => 2])->assertNotFound();
    }

    public function test_legacy_cart_import_is_idempotent_and_preserves_missing_products(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $legacy = B2bSavedCart::create(['user_id' => $user->id, 'name' => 'Старая корзина', 'items' => [$item, ['id' => 999, 'name' => 'Уже отсутствует', 'price' => 10, 'quantity' => 3]], 'items_count' => 13, 'total' => 1230]);
        $this->actingAs($user)->get(route('selections.index'))->assertOk()->assertSee('Мои заявки')->assertSee('Старая корзина')->assertSee('Резервы по заказам');
        $this->get(route('b2b.cabinet', ['section' => 'carts']))->assertRedirect(route('selections.index'));
        foreach (range(1, 2) as $_) {
            $this->post(route('requests.import-cart', $legacy))->assertRedirect();
        }
        $imported = CustomerSelection::where('legacy_saved_cart_id', $legacy->id)->sole();
        $this->assertCount(2, $imported->items);
        $this->assertSame(3, $imported->items[999]['quantity']);
        $this->assertDatabaseHas('b2b_saved_carts', ['id' => $legacy->id]);
        $this->get(route('selections.index'))->assertDontSee('Открыть как заявку');
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($other)->post(route('requests.import-cart', $legacy))->assertForbidden();
    }

    public function test_prepare_excludes_unchecked_lines_and_rechecks_stock_after_previous_check(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $this->actingAs($user)->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        $offer->update(['stock_quantity' => 2]);
        $this->post(route('requests.prepare', $selection), ['revision' => 2])->assertRedirect(route('cart'));
        $this->assertSame(2, session('cart')[$item['id']]['quantity']);
        $this->assertSame(8, $selection->fresh()->meta['availability'][$item['id']]['waiting']);
        $this->patchJson(route('b2b.selections.update', $selection), ['action' => 'select', 'revision' => 3, 'ids' => []])->assertOk();
        $this->post(route('requests.prepare', $selection), ['revision' => 4])->assertSessionHas('error');
        $this->assertSame(2, session('cart')[$item['id']]['quantity']);
    }

    public function test_real_reservations_reduce_ready_quantity_and_are_listed_separately_without_foreign_orders(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $order = B2bOrder::create(['b2b_client_id' => $user->activeB2bClient()->id, 'created_by' => $user->id,
            'number' => 'B2B-RESERVE-TEST', 'company_name' => 'Test company', 'status' => 'checking', 'contact_name' => 'Тест', 'contact_phone' => '+375290000000']);
        $line = $order->items()->create(['product_id' => $item['id'], 'product_name' => $item['name'], 'quantity' => 3, 'price' => 125, 'total' => 375]);
        $order->reservations()->create(['b2b_order_item_id' => $line->id, 'product_id' => $item['id'],
            'stock_key' => 'supplier:'.$offer->id, 'quantity' => 3, 'status' => 'active']);
        $this->actingAs($user)->post(route('requests.check', $selection), ['revision' => 1])->assertRedirect();
        $this->assertSame(3, $selection->fresh()->meta['availability'][$item['id']]['ready']);
        $this->assertSame(7, $selection->fresh()->meta['availability'][$item['id']]['waiting']);
        $this->get(route('selections.index'))->assertOk()->assertSee('B2B-RESERVE-TEST')->assertSee('3 шт. в резерве');
        $other = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($other)->get(route('selections.index'))->assertOk()->assertDontSee('B2B-RESERVE-TEST');
        $this->assertDatabaseCount('b2b_order_reservations', 1);
    }

    public function test_150_line_allocation_keeps_every_requested_unit_and_loads_only_these_offers(): void
    {
        [$user,$selection,$offer,$item] = $this->fixture();
        $items = $selection->items;
        Product::withoutEvents(function () use (&$items, $offer, $item) {
            for ($i = 2; $i <= 150; $i++) {
                $product = Product::create(['category_id' => $offer->product->category_id, 'name' => 'Насос '.$i, 'sku' => 'P-'.$i, 'slug' => 'bulk-request-'.$i, 'price' => 200, 'is_active' => true, 'is_archived' => false]);
                $row = $offer->replicate();
                $row->product_id = $product->id;
                $row->supplier_article = 'SUP-'.$i;
                $row->save();
                $items[$product->id] = array_replace($item, ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'supplier_product_id' => $row->id, 'supply_option' => 'supplier:'.$row->id]);
            }
        });
        $selection->update(['items' => $items]);
        $loaded = [];
        SupplierProduct::retrieved(function ($row) use (&$loaded) {
            $loaded[] = $row->id;
        });
        $this->actingAs($user)->post(route('requests.prepare', $selection), ['revision' => 1])->assertRedirect(route('cart'));
        $this->assertCount(150, $loaded);
        $this->assertCount(150, session('cart'));
        $this->assertSame(900, array_sum(array_column(session('cart'), 'quantity')));
        $this->assertSame(600, array_sum(array_column($selection->fresh()->meta['availability'], 'waiting')));
        $this->assertSame(1500, array_sum(array_column($selection->fresh()->items, 'quantity')));
        $this->assertDatabaseCount('b2b_order_reservations', 0);
    }
}

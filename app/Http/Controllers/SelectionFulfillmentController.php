<?php

namespace App\Http\Controllers;

use App\Models\B2bSavedCart;
use App\Models\CustomerSelection;
use App\Services\B2bCompanyAccess;
use App\Services\CartSelection;
use App\Services\CustomerCartStore;
use App\Services\SelectionAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectionFulfillmentController extends Controller
{
    public function importCart(Request $request, B2bSavedCart $savedCart)
    {
        $access = app(B2bCompanyAccess::class);
        $client = $request->user()->activeB2bClient();
        abort_unless($client && $access->allows($request->user(), 'orders.create', $client), 403);
        abort_unless($access->allUserIds($client)->contains((int) $savedCart->user_id), 404);
        $selection = DB::transaction(function () use ($request, $savedCart) {
            // Serializes repeat clicks without deleting or changing the old snapshot.
            $savedCart = B2bSavedCart::whereKey($savedCart->id)->lockForUpdate()->firstOrFail();

            return CustomerSelection::firstOrCreate([
                'user_id' => $request->user()->id, 'legacy_saved_cart_id' => $savedCart->id,
            ], ['name' => $savedCart->name, 'items' => collect($savedCart->items)->keyBy('id')->all(), 'revision' => 1]);
        });

        return to_route('b2b.selections.show', $selection);
    }

    public function check(Request $request, CustomerSelection $selection, SelectionAvailability $availability)
    {
        $this->authorizeSelection($request, $selection);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($request, $selection, $data, $availability) {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'], 409, 'Заявка изменена. Обновите страницу.');
            $meta = $locked->meta ?? [];
            $meta['availability'] = $availability->check($locked, $request->user());
            $locked->update(['meta' => $meta, 'revision' => $locked->revision + 1]);
        });

        return back()->with('success', 'Наличие проверено. Недостающее сохранено в ожидании. Резерв не создавался.');
    }

    public function prepare(Request $request, CustomerSelection $selection, SelectionAvailability $availability, CustomerCartStore $store)
    {
        $this->authorizeSelection($request, $selection);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $cart = DB::transaction(function () use ($request, $selection, $data, $availability) {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'], 409, 'Заявка изменена. Обновите страницу.');
            $meta = $locked->meta ?? [];
            // Always recheck the source; never trust quantities or prices sent by a browser.
            $meta['availability'] = $availability->check($locked, $request->user());
            $cart = [];
            foreach ($locked->items as $id => $item) {
                $allocation = $meta['availability'][$id];
                if (in_array((int) $id, array_map('intval', $meta['desk_excluded_ids'] ?? []), true) || $allocation['ready'] < 1) {
                    continue;
                }
                $offer = $allocation['offer'];
                $cart[$id] = array_replace($item, [
                    'quantity' => $allocation['ready'], 'price' => $offer['price'], 'pricing_type' => 'b2b',
                    'supply_option' => $offer['key'], 'integration_product_id' => $offer['integration_product_id'],
                    'supplier_product_id' => $offer['supplier_product_id'], 'warehouse_stock_id' => $offer['warehouse_stock_id'],
                    'warehouse_name' => $offer['warehouse_name'], 'source_name' => $offer['source_label'],
                    'order_rules' => $offer['order_rules'] ?? [], 'price_tax_mode' => 'inclusive',
                ]);
                unset($cart[$id]['allocation']);
            }
            $locked->update(['meta' => $meta, 'revision' => $locked->revision + 1]);

            return $cart;
        });
        if ($cart === []) {
            return back()->with('error', 'Нет подтверждённого доступного количества среди отмеченных товаров. Вся потребность сохранена в заявке.');
        }
        $current = $request->session()->get('cart', []);
        if ($current !== [] && $current !== $cart) {
            CustomerSelection::create(['user_id' => $request->user()->id, 'name' => 'Корзина до оформления · '.now()->format('d.m.Y H:i'),
                'items' => $current, 'meta' => $store->meta($request), 'note' => $request->session()->get('cart_note')]);
        }
        $request->session()->forget(CustomerCartStore::META);
        // Do not bind a partial cart to the full demand: saving it must never erase waiting lines.
        $request->session()->put(['cart' => $cart, 'cart_note' => 'Из заявки «'.$selection->name.'». Недостающее сохранено отдельно.', CartSelection::KEY => []]);
        $store->save($request, $request->user()->id);

        return to_route('cart')->with('success', 'Доступное количество открыто в корзине с актуальными ценами. Полная потребность и недостающее остаются в «Моих заявках». Это ещё не резерв.');
    }

    private function authorizeSelection(Request $request, CustomerSelection $selection): void
    {
        abort_unless(B2bSelectionDeskController::canUse($request->user()), 403);
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
    }
}

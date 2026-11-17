<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Services\CustomerCartStore;
use Illuminate\Http\Request;

class CustomerSelectionController extends Controller
{
    public function index(Request $request)
    {
        $selections = CustomerSelection::where('user_id', $request->user()->id)->latest('updated_at')->paginate(20);
        $user = $request->user();
        $client = $user->activeB2bClient();
        $access = app(\App\Services\B2bCompanyAccess::class);
        $legacyCarts = collect();
        if ($client && $access->allows($user, 'orders.create', $client)) {
            $legacyCarts = \App\Models\B2bSavedCart::whereIn('user_id', $access->allUserIds($client))
                ->whereNotIn('id', CustomerSelection::where('user_id', $user->id)->whereNotNull('legacy_saved_cart_id')->select('legacy_saved_cart_id'))
                ->latest('updated_at')->get();
        }
        $reservations = \App\Models\B2bOrderReservation::active()->with('item', 'order')
            ->whereHas('order', function ($query) use ($client, $access, $user) {
                if ($client && $access->allows($user, 'orders.view', $client)) $query->where('b2b_client_id', $client->id);
                else $query->where('created_by', $user->id);
                $query->whereNotIn('status', ['cancelled', 'completed', 'shipped']);
            })->latest('id')->paginate(20, ['*'], 'reserves_page');
        return response()->view('pages.selections.index', compact('selections', 'legacyCarts', 'reservations'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, CustomerCartStore $store)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'selection_id' => ['nullable', 'integer'],
            'revision' => ['required_with:selection_id', 'nullable', 'integer', 'min:1'],
        ]);
        $items = $request->session()->get('cart', []);
        if ($items === []) return back()->withErrors(['name' => 'Сначала добавьте товары в корзину.']);
        $values = ['name' => $data['name'], 'items' => $items,
            'meta' => array_diff_key($store->meta($request), array_flip(['cart_selection_id', 'cart_selection_revision'])),
            'note' => $request->session()->get('cart_note')];
        $sourceSelection = CustomerSelection::where('user_id', $request->user()->id)->find($request->session()->get('cart_selection_id'));
        if (!empty($sourceSelection?->meta['pump_calculations'])) $values['meta']['pump_calculations'] = $sourceSelection->meta['pump_calculations'];
        if (! empty($data['selection_id'])) {
            $selection = CustomerSelection::findOrFail($data['selection_id']);
            $this->own($request, $selection);
            $values['meta'] = array_replace($selection->meta ?? [], $values['meta']);
            $updated = CustomerSelection::whereKey($selection->id)->where('revision', $data['revision'])->update([
                'name' => $values['name'], 'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
                'meta' => json_encode($values['meta'], JSON_UNESCAPED_UNICODE), 'note' => $values['note'],
                'revision' => $data['revision'] + 1, 'updated_at' => now(),
            ]);
            if (! $updated) return back()->with('error', 'Подбор уже изменён в другой вкладке. Откройте актуальную версию или сохраните корзину отдельным подбором.');
            $selection->refresh();
        } else {
            $selection = CustomerSelection::create(['user_id' => $request->user()->id] + $values);
        }
        $request->session()->put(['cart_selection_id' => $selection->id, 'cart_selection_revision' => $selection->revision]);
        return to_route('selections.show', $selection)->with('success', 'Подбор сохранён в вашем аккаунте.');
    }

    public function show(Request $request, CustomerSelection $selection)
    {
        $this->own($request, $selection);
        if ($request->boolean('print')) return $this->printView($selection->name, $selection->items, $selection->note, $selection->updated_at, $selection->meta['pump_calculations'] ?? []);
        return response()->view('pages.selections.show', compact('selection'))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, CustomerSelection $selection)
    {
        $this->own($request, $selection);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'note' => ['nullable', 'string', 'max:1000'],
            'revision' => ['required', 'integer', 'min:1'],
            'quantities' => ['required', 'array', 'max:2000'], 'quantities.*' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
        $items = $selection->items;
        foreach ($items as $id => &$item) {
            // Never silently remove omitted rows (e.g. a truncated large form).
            if (array_key_exists($id, $data['quantities'])) $item['quantity'] = (int) $data['quantities'][$id];
        }
        unset($item);
        $items = array_filter($items, fn ($item) => $item['quantity'] > 0);
        $updated = CustomerSelection::whereKey($selection->id)->where('revision', $data['revision'])->update([
            'name' => $data['name'], 'note' => $data['note'] ?? null, 'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'revision' => $data['revision'] + 1, 'updated_at' => now(),
        ]);
        if (! $updated) return back()->with('error', 'Подбор уже изменён в другой вкладке. Обновите страницу перед сохранением.');
        return back()->with('success', 'Изменения сохранены.');
    }

    public function restore(Request $request, CustomerSelection $selection, CustomerCartStore $store)
    {
        $this->own($request, $selection);
        if ($selection->items === []) return back()->with('error', 'В подборе нет товаров.');
        $current = $request->session()->get('cart', []);
        if ($current !== [] && $current !== $selection->items) {
            CustomerSelection::create([
                'user_id' => $request->user()->id, 'name' => 'Корзина до открытия подбора · '.now()->format('d.m.Y H:i'),
                'items' => $current, 'meta' => $store->meta($request), 'note' => $request->session()->get('cart_note'),
            ]);
        }
        $request->session()->forget(CustomerCartStore::META);
        $request->session()->put($selection->meta ?? []);
        $request->session()->put(['cart' => $selection->items, 'cart_note' => $selection->note,
            'cart_selection_id' => $selection->id, 'cart_selection_revision' => $selection->revision]);
        return to_route('cart')->with('success', 'Подбор открыт в корзине. Цены и доступность проверяются заново; сохранённый подбор остаётся в аккаунте.');
    }

    public function printCurrent(Request $request)
    {
        $selection = $request->user() ? CustomerSelection::where('user_id', $request->user()->id)->find($request->session()->get('cart_selection_id')) : null;
        return $this->printView('Подбор оборудования', $request->session()->get('cart', []), $request->session()->get('cart_note'), now(), $selection?->meta['pump_calculations'] ?? []);
    }

    public function duplicate(Request $request, CustomerSelection $selection)
    {
        $this->own($request, $selection);
        $copy = CustomerSelection::create([
            'user_id' => $request->user()->id, 'name' => mb_substr($selection->name, 0, 145).' · копия',
            'items' => $selection->items, 'meta' => $selection->meta, 'note' => $selection->note,
        ]);
        return to_route('selections.show', $copy)->with('success', 'Копия подбора сохранена.');
    }

    public function moveLine(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer'], 'target_selection_id' => ['nullable', 'integer'],
            'name' => ['required_without:target_selection_id', 'nullable', 'string', 'max:160'],
        ]);
        $items = $request->session()->get('cart', []);
        abort_unless(isset($items[$data['product_id']]), 409, 'Позиция уже удалена из корзины.');
        $line = $items[$data['product_id']];
        $target = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $data, $line) {
            if (! empty($data['target_selection_id'])) {
                $target = CustomerSelection::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($data['target_selection_id']);
                $saved = $target->items;
                if (isset($saved[$data['product_id']])) return null;
                $saved[$data['product_id']] = $line;
                $target->update(['items' => $saved, 'revision' => $target->revision + 1]);
                return $target;
            }
            return CustomerSelection::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'items' => [$data['product_id'] => $line]]);
        });
        if (! $target) return back()->with('error', 'В выбранном подборе уже есть этот товар. Выберите другой подбор или создайте новый.');
        unset($items[$data['product_id']]);
        $request->session()->put('cart', $items);
        $request->session()->put(\App\Services\CartSelection::KEY, array_values(array_intersect($request->session()->get(\App\Services\CartSelection::KEY, []), array_keys($items))));
        return to_route('cart')->with('success', 'Позиция перенесена в подбор «'.$target->name.'».');
    }

    private function printView(string $name, array $items, ?string $note, $date, array $pumpCalculations = [])
    {
        return response()->view('pages.selections.print', compact('name', 'items', 'note', 'date', 'pumpCalculations'))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function own(Request $request, CustomerSelection $selection): void
    {
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
    }
}

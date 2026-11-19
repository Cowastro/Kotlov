<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Models\Product;
use App\Services\StaffCatalogWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffCatalogWorkspaceController extends Controller
{
    public function enter(Request $request)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);

        return to_route('b2b.catalog', ['workspace' => 1, 'mode' => 'reference']);
    }

    /** A list pasted from Excel or a letter: what each line is, for the manager to confirm before it goes to the selection. */
    public function paste(Request $request, \App\Services\B2bPastedList $list)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        $data = $request->validate(['text' => ['required', 'string', 'max:30000']]);

        return response()->json(['rows' => $list->recognise($data['text']), 'limit' => \App\Services\B2bPastedList::MAX_LINES])->header('Cache-Control', 'private, no-store');
    }

    /** What to offer instead of an item: the same element of another series, brand or supplier, in stock first. */
    public function analogs(Request $request, Product $product, StaffCatalogWorkspace $workspace, \App\Services\B2bAnalogs $analogs)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        $items = collect($analogs->for($product, $request->user(), $workspace))->map(fn (array $row): array => [
            'id' => (int) $row['product']->id, 'name' => (string) $row['product']->name, 'sku' => (string) ($row['product']->sku ?: ''),
            'brand' => (string) ($row['product']->brand?->name ?: ''), 'price' => (float) $row['product']->price,
            'url' => url('/'.($row['product']->category?->slug ?? 'catalog').'/'.$row['product']->slug),
            'matches' => $row['matches'],
            'summary' => $row['summary'],
        ])->all();

        return response()->json(['source' => ['id' => $product->id, 'name' => $product->name], 'items' => $items])->header('Cache-Control', 'private, no-store');
    }

    /** Clients to attach to a selection: CRM counterparties and B2B clients by name or УНП. */
    public function clients(Request $request, \App\Services\ClientPurchaseHistory $history)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);

        return response()->json(['items' => $history->search(mb_substr((string) $request->query('q', ''), 0, 100))])->header('Cache-Control', 'private, no-store');
    }

    /** The client of the selection (or none): its purchase history is shown while the selection is made. */
    public function client(Request $request, CustomerSelection $selection, StaffCatalogWorkspace $workspace, \App\Services\ClientPurchaseHistory $history)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
        $data = $request->validate(['client' => ['nullable', 'string', 'max:40', 'regex:/^(crm|b2b):\d+$/']]);
        $client = filled($data['client'] ?? null) ? $history->describe($data['client']) : null;
        abort_if(filled($data['client'] ?? null) && ! $client, 422, 'Клиент не найден.');
        DB::transaction(function () use ($selection, $client): void {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            $meta = $locked->meta ?? [];
            if ($client) {
                $meta['client'] = $client;
            } else {
                unset($meta['client']);
            }
            // The client does not change the positions: the revision of the items stays.
            $locked->meta = $meta;
            $locked->save();
        });

        return response()->json($workspace->state($selection->fresh(), $request->user()))->header('Cache-Control', 'private, no-store');
    }

    /** What the client of the selection bought before. */
    public function history(Request $request, CustomerSelection $selection, \App\Services\ClientPurchaseHistory $history)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
        $client = $selection->meta['client'] ?? null;

        return response()->json(['client' => $client, ...($client ? $history->for($client['key']) : ['orders' => [], 'products' => []])])
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, StaffCatalogWorkspace $workspace)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
        $selection = CustomerSelection::create([
            'user_id' => $request->user()->id, 'name' => $data['name'],
            'items' => [], 'meta' => ['staff_workspace' => true], 'revision' => 1,
        ]);
        $request->session()->put('staff_selection_id', $selection->id);

        return response()->json($workspace->state($selection, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, CustomerSelection $selection, int $product, StaffCatalogWorkspace $workspace)
    {
        abort_unless(StaffCatalogWorkspace::allowed($request->user()), 403);
        abort_unless((int) $selection->user_id === (int) $request->user()->id, 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'revision' => ['required', 'integer', 'min:1'],
            'supplier_key' => ['nullable', 'string', 'max:100'],
            'operation' => ['nullable', 'in:add,set'],
        ]);
        abort_if(filled($data['supplier_key'] ?? null) && ! StaffCatalogWorkspace::canViewProcurement($request->user()), 403);
        $selection = DB::transaction(function () use ($selection, $product, $data, $workspace, $request) {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'], 409,
                'Подбор изменён в другой вкладке. Обновите страницу перед сохранением.');
            $items = $locked->items;
            $adding = ($data['operation'] ?? 'set') === 'add';
            $quantity = (int) $data['quantity'] + ($adding ? (int) ($items[$product]['quantity'] ?? 0) : 0);
            if ($adding && ((int) $data['quantity'] < 1 || $quantity > 999)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'Добавьте положительное целое количество. В одной позиции может быть не более 999.']);
            }
            if ($adding && isset($items[$product]['workspace_purchase']['supplier_key']) && ($data['supplier_key'] ?? null) !== $items[$product]['workspace_purchase']['supplier_key']) {
                throw \Illuminate\Validation\ValidationException::withMessages(['supplier_key' => 'Поставщик в подборе изменён. Обновите поиск перед добавлением.']);
            }
            if ((int) $data['quantity'] === 0) {
                unset($items[$product]);
            } else {
                $model = Product::query()->active()->notArchived()->findOrFail($product);
                abort_if(! isset($items[$product]) && count($items) >= 2000, 422, 'В подборе уже 2000 позиций. Создайте отдельный подбор.');
                $previousNote = $items[$product]['workspace_note'] ?? null;
                $previousLine = $items[$product] ?? [];
                $items[$product] = [
                    'id' => $model->id, 'name' => $model->name, 'sku' => $model->sku,
                    'price' => (float) $model->price, 'quantity' => $quantity,
                    'image' => $model->image_url, 'pricing_type' => 'retail',
                    'selection_note' => (float) $model->price > 0
                        ? 'Розничная цена на момент подбора.'
                        : 'Розничная цена не задана.',
                ];
                if ($previousNote !== null) {
                    $items[$product]['workspace_note'] = $previousNote;
                }
                if (isset($previousLine['client_pricing'])) {
                    $items[$product]['client_pricing'] = $previousLine['client_pricing'];
                    $items[$product]['price'] = $previousLine['price'];
                    $items[$product]['selection_note'] = $previousLine['selection_note'] ?? null;
                }
                if (StaffCatalogWorkspace::canViewProcurement($request->user())) {
                    $offers = $workspace->offers($model, $request->user());
                    $key = $data['supplier_key'] ?? ($locked->items[$product]['workspace_purchase']['supplier_key'] ?? null);
                    $offer = filled($key) ? $offers->firstWhere('supplier_key', $key) : $offers->first();
                    abort_if(filled($key) && ! $offer, 422, 'Предложение поставщика изменилось. Обновите каталог и выберите поставщика заново.');
                    if ($offer) {
                        if ($adding && ($warning = \App\Services\Suppliers\SupplierOrderRules::warning($offer['order_rules'] ?? [], $quantity)) !== '') {
                            throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => $warning]);
                        }
                        $items[$product]['workspace_purchase'] = $offer;
                        $items[$product]['order_rules'] = $offer['order_rules'] ?? [];
                    }
                }
            }
            $locked->update(['items' => $items, 'meta' => array_replace($locked->meta ?? [], ['staff_workspace' => true]), 'revision' => $locked->revision + 1]);

            return $locked;
        });

        return response()->json($workspace->state($selection, $request->user()))->header('Cache-Control', 'private, no-store');
    }
}

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
        abort_unless((int) $selection->user_id === (int) $request->user()->id
            && ($selection->meta['staff_workspace'] ?? false), 404);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
            'revision' => ['required', 'integer', 'min:1'],
            'supplier_key' => ['nullable', 'string', 'max:100'],
        ]);
        abort_if(filled($data['supplier_key'] ?? null) && ! $request->user()->isAdmin(), 403);
        $selection = DB::transaction(function () use ($selection, $product, $data, $workspace, $request) {
            $locked = CustomerSelection::whereKey($selection->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'], 409,
                'Подбор изменён в другой вкладке. Обновите страницу перед сохранением.');
            $items = $locked->items;
            if ((int) $data['quantity'] === 0) {
                unset($items[$product]);
            } else {
                $model = Product::query()->active()->notArchived()->findOrFail($product);
                abort_if(! isset($items[$product]) && count($items) >= 2000, 422, 'В подборе уже 2000 позиций. Создайте отдельный подбор.');
                $items[$product] = [
                    'id' => $model->id, 'name' => $model->name, 'sku' => $model->sku,
                    'price' => (float) $model->price, 'quantity' => (int) $data['quantity'],
                    'image' => $model->image_url, 'pricing_type' => 'retail',
                    'selection_note' => (float) $model->price > 0
                        ? 'Цена сайта на момент подбора. Условия поставки уточняются.'
                        : 'Цена и условия поставки требуют уточнения.',
                ];
                if ($request->user()->isAdmin()) {
                    $offers = $workspace->offers($model, $request->user());
                    $key = $data['supplier_key'] ?? ($locked->items[$product]['workspace_purchase']['supplier_key'] ?? null);
                    $offer = filled($key) ? $offers->firstWhere('supplier_key', $key) : $offers->first();
                    abort_if(filled($key) && ! $offer, 422, 'Предложение поставщика изменилось. Обновите каталог и выберите поставщика заново.');
                    if ($offer) {
                        $items[$product]['workspace_purchase'] = $offer;
                    }
                }
            }
            $locked->update(['items' => $items, 'revision' => $locked->revision + 1]);

            return $locked;
        });

        return response()->json($workspace->state($selection, $request->user()))->header('Cache-Control', 'private, no-store');
    }
}

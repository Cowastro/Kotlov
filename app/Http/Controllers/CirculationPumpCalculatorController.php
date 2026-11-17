<?php

namespace App\Http\Controllers;

use App\Models\CustomerSelection;
use App\Models\Product;
use App\Services\B2bCompanyAccess;
use App\Services\B2bSupplyOptionResolver;
use App\Services\CirculationPumpCalculator;
use App\Services\StaffCatalogWorkspace;
use App\Support\Catalog\PumpCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class CirculationPumpCalculatorController extends Controller
{
    private function products()
    {
        return Product::query()->active()->notArchived()->whereHas('category', fn ($q) => $q->where('slug', 'tsirkulyatsionnyie'))
            ->with(['category', 'allAttributeValues.attribute', 'allAttributeValues.option']);
    }

    private function input(Request $request, CirculationPumpCalculator $calculator): array
    {
        $data = $calculator->validate($request->all());
        if (! empty($data['reference'])) {
            $reference = $this->products()->findOrFail($data['reference']);
            abort_unless($calculator->eligible($reference), 422);
            $facts = $calculator->facts($reference);
            $data['length'] = $data['length'] ?: ($facts['numbers']['length'] ?? '');
            $data['voltage'] = $data['voltage'] ?: ($facts['options']['voltage'][0] ?? '');
            $data['connection'] = $data['connection'] ?: ($facts['connection'] ?? '');
            $data['model'] = mb_substr($reference->name, 0, 100);
            if (empty($data['length'])) {
                throw ValidationException::withMessages(['length' => 'У исходной модели нет монтажной длины в каталоге. Укажите её с шильдика или паспорта.']);
            }
        }

        return $data;
    }

    public function show(Request $request, CirculationPumpCalculator $calculator, B2bSupplyOptionResolver $supply)
    {
        $input = array_replace($calculator->defaults(), array_filter($request->only(['scenario', 'load_method', 'fluid', 'system', 'supply', 'return', 'head_method', 'resistance', 'factor', 'voltage', 'length', 'connection', 'model', 'reference', 'power', 'area', 'heat_loss', 'flow', 'head', 'pipe_length']), fn ($value) => is_scalar($value) || $value === null));
        $result = null;
        $errors = new MessageBag;
        $matches = collect();
        $facts = [];
        if ($request->boolean('run')) {
            try {
                $data = $this->input($request, $calculator);
                $input = array_replace($calculator->defaults(), $data);
                $result = $calculator->calculate($data);
                $query = $this->products()->orderBy('name');
                if ($input['scenario'] === 'replacement' && ! empty($input['model']) && empty($input['reference'])) {
                    app(PumpCatalog::class)->search($query, $input['model']);
                }
                foreach ($query->get(['id', 'category_id', 'name', 'slug', 'sku', 'price', 'specs', 'images']) as $product) {
                    $fact = $calculator->facts($product);
                    if ($product->id !== (int) ($input['reference'] ?? 0) && $calculator->matches($product, $fact, $input, $result)) {
                        $matches->push($product);
                        $facts[$product->id] = $fact;
                    }
                }
            } catch (ValidationException $exception) {
                $errors = new MessageBag($exception->errors());
            }
        }
        $page = max(1, min(10000, $request->integer('page', 1)));
        $products = new LengthAwarePaginator($matches->forPage($page, 12)->values(), $matches->count(), 12, $page, ['path' => $request->url(), 'query' => $request->query()]);
        $partner = $request->user()?->activeB2bClient();
        $quotes = $partner ? $products->getCollection()->mapWithKeys(fn ($p) => [$p->id => $supply->preferredForProduct($p, $request->user())]) : collect();
        $selections = $request->user() ? CustomerSelection::where('user_id', $request->user()->id)->latest('updated_at')->limit(100)->get(['id', 'name', 'revision']) : collect();

        return response()->view('pages.circulation-pump-calculator', compact('input', 'result', 'errors', 'products', 'facts', 'quotes', 'partner', 'selections'))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', $request->query() ? 'noindex, follow' : 'index, follow');
    }

    public function save(Request $request, CirculationPumpCalculator $calculator, B2bSupplyOptionResolver $supply)
    {
        $user = $request->user();
        abort_unless($user?->is_active, 403);
        if ($user->isB2B()) {
            abort_unless(app(B2bCompanyAccess::class)->allows($user, 'orders.create'), 403);
        }
        $input = $this->input($request, $calculator);
        $result = $calculator->calculate($input);
        $data = $request->validate(['selection_id' => ['nullable', 'integer'], 'revision' => ['required_with:selection_id', 'nullable', 'integer', 'min:1'],
            'name' => ['required_without:selection_id', 'nullable', 'string', 'max:160'], 'product_id' => ['nullable', 'integer'], 'quantity' => ['required_with:product_id', 'nullable', 'integer', 'min:1', 'max:999']]);
        $item = null;
        if (! empty($data['product_id'])) {
            $product = $this->products()->findOrFail($data['product_id']);
            abort_if($product->id === (int) ($input['reference'] ?? 0), 422, 'Это исходная модель, а не кандидат на замену.');
            abort_unless($calculator->matches($product, $calculator->facts($product), $input, $result), 422, 'Товар не соответствует параметрам предварительного отбора.');
            if ($input['scenario'] === 'replacement' && ! empty($input['model']) && empty($input['reference'])) {
                $query = $this->products()->whereKey($product->id);
                app(PumpCatalog::class)->search($query, $input['model']);
                abort_unless($query->exists(), 422);
            }
            $quote = $user->activeB2bClient() ? $supply->preferredForProduct($product, $user) : null;
            abort_if($user->activeB2bClient() && (! $quote || ! $supply->canOrder($quote, (int) $data['quantity'])), 422, 'Для вашего B2B-аккаунта нет доступного предложения на это количество.');
            $item = ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'slug' => $product->slug, 'category_slug' => $product->category->slug,
                'image' => $product->image_url, 'price' => $quote['price'] ?? (float) $product->price, 'quantity' => (int) $data['quantity'],
                'pricing_type' => $quote ? 'b2b' : 'retail', 'selection_note' => $result['summary'], 'pump_calculation_key' => hash('sha256', json_encode($input))];
            if ($quote) {
                $item += ['price_tax_mode' => 'inclusive', 'supply_option' => $quote['key'], 'integration_product_id' => $quote['integration_product_id'], 'supplier_product_id' => $quote['supplier_product_id'],
                    'warehouse_stock_id' => $quote['warehouse_stock_id'], 'warehouse_name' => $quote['warehouse_name'], 'source_name' => $quote['source_label'], 'order_rules' => $quote['order_rules'] ?? [],
                    'warehouse_delivery_days' => $quote['delivery_days'], 'b2b_base_price' => $quote['base_price'], 'b2b_price_level_id' => $quote['price_level_id'], 'b2b_price_level_code' => $quote['price_level_code'],
                    'b2b_price_level_name' => $quote['price_level_name'], 'b2b_discount_percent' => $quote['discount_percent']];
            } elseif ($user->isAdmin()) {
                $offer = app(StaffCatalogWorkspace::class)->offers($product, $user)->first();
                if ($offer) {
                    $item['workspace_purchase'] = $offer;
                }
            }
        }
        $selection = DB::transaction(function () use ($data, $input, $result, $item, $user) {
            $selection = ! empty($data['selection_id']) ? CustomerSelection::where('user_id', $user->id)->lockForUpdate()->findOrFail($data['selection_id']) : new CustomerSelection(['user_id' => $user->id, 'name' => $data['name'], 'items' => [], 'meta' => [], 'revision' => 0]);
            abort_if($selection->exists && $selection->revision !== (int) $data['revision'], 409, 'Подбор изменён в другой вкладке. Обновите страницу калькулятора.');
            $items = $selection->items;
            abort_if($item && isset($items[$item['id']]), 409, 'Этот товар уже есть в подборе. Количество и цена не перезаписаны.');
            abort_if($item && count($items) >= 2000, 422, 'В подборе уже 2000 позиций.');
            if ($item) {
                $items[$item['id']] = $item;
            }
            $meta = $selection->meta ?? [];
            $calculations = $meta['pump_calculations'] ?? [];
            $hash = hash('sha256', json_encode($input));
            if (! isset($calculations[$hash])) {
                abort_if(count($calculations) >= 20, 422, 'В подборе уже 20 расчётов. Создайте отдельный подбор.');
                $calculations[$hash] = ['input' => $input, 'result' => $result, 'saved_at' => now()->toIso8601String(), 'version' => 1];
            }
            $meta['pump_calculations'] = $calculations;
            if (StaffCatalogWorkspace::allowed($user)) {
                $meta['staff_workspace'] = true;
            }
            $selection->fill(['items' => $items, 'meta' => $meta, 'revision' => $selection->revision + 1])->save();

            return $selection;
        });

        return to_route('selections.show', $selection)->with('success', 'Расчёт сохранён в подборе. Соответствие насоса рабочей точке требует проверки по паспорту.');
    }

    public function login(Request $request, CirculationPumpCalculator $calculator)
    {
        $input = $this->input($request, $calculator);
        $url = route('pump-calculator', array_merge($input, ['run' => 1])).'#pc-result';
        if ($request->user()) {
            return redirect($url);
        }
        $request->session()->put('url.intended', $url);

        return to_route('login');
    }
}

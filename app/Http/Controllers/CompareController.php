<?php

namespace App\Http\Controllers;

use App\Models\CompareItem;
use App\Support\Catalog\FireplaceSpecifications;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    private function getSessionId(): string
    {
        return session()->getId();
    }

    public function index()
    {
        $query = CompareItem::with([
            'product.brand', 'product.category', 'product.attributeValues',
        ]);

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            $query->where('session_id', $this->getSessionId());
        }

        $items = $query->get();
        $products = $items->pluck('product')->filter();

        // Собираем все уникальные атрибуты со всех товаров
        $allAttributes = $products->flatMap(function ($product) {
            return $product->attributeValues->filter(fn ($value) => $value->attribute?->is_comparable)->map(fn ($v) => [
                'id' => $v->attribute_id,
                'name' => $v->attribute->name ?? '',
            ]);
        })->unique('id')->sortBy('name')->values();

        $openingByProduct = $products->filter(fn ($product) => $product->category?->slug === 'elektrokamini')
            ->mapWithKeys(fn ($product) => [$product->id => FireplaceSpecifications::electricOpeningRows($product)]);
        $openingLabels = $openingByProduct->flatMap(fn ($rows) => array_keys($rows))->unique()->values();

        return view('pages.compare', compact('products', 'items', 'allAttributes', 'openingByProduct', 'openingLabels'));
    }

    public function add(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $query = CompareItem::where('product_id', $request->product_id);

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            $query->where('session_id', $this->getSessionId());
        }

        if ($query->exists()) {
            return response()->json(['message' => 'already_added', 'count' => $this->getCount()]);
        }

        // Максимум 4 товара
        $count = $this->getBaseQuery()->count();
        if ($count >= 4) {
            return response()->json(['message' => 'limit_reached', 'count' => $count], 422);
        }

        CompareItem::create([
            'product_id' => $request->product_id,
            'user_id' => auth()->id(),
            'session_id' => $this->getSessionId(),
        ]);

        return response()->json(['message' => 'added', 'count' => $this->getCount()]);
    }

    public function remove(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $this->getBaseQuery()
            ->where('product_id', $request->product_id)
            ->delete();

        return response()->json(['message' => 'removed', 'count' => $this->getCount()]);
    }

    public function clear()
    {
        $this->getBaseQuery()->delete();

        return redirect('/compare')->with('success', 'Список сравнения очищен.');
    }

    public function items()
    {
        $items = $this->getBaseQuery()
            ->with(['product.category', 'product.brand'])
            ->get();

        return response()->json(
            $items->filter(fn ($i) => $i->product)
                ->map(function ($i) {
                    $p = $i->product;

                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'url' => '/'.($p->category->slug ?? 'catalog').'/'.$p->slug,
                        'image' => $p->image_url,
                    ];
                })->values()
        );
    }

    public function clearAjax()
    {
        $this->getBaseQuery()->delete();

        return response()->json(['message' => 'cleared']);
    }

    private function getBaseQuery()
    {
        $query = CompareItem::query();
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            $query->where('session_id', $this->getSessionId());
        }

        return $query;
    }

    private function getCount(): int
    {
        return $this->getBaseQuery()->count();
    }
}

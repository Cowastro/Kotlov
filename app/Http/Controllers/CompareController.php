<?php

namespace App\Http\Controllers;

use App\Models\CompareItem;
use App\Models\Product;
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
            'product' => fn($q) => $q->with([
                'brand', 'category',
                'attributeValues' => fn($q) => $q
                    ->with('attribute', 'option')
                    ->orderBy('attribute_id'),
            ])
        ]);

        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            $query->where('session_id', $this->getSessionId());
        }

        $items    = $query->get();
        $products = $items->pluck('product')->filter();

        // Собираем все уникальные атрибуты со всех товаров
        $allAttributes = $products->flatMap(function ($product) {
            return $product->attributeValues->map(fn($v) => [
                'id'   => $v->attribute_id,
                'name' => $v->attribute->name ?? '',
            ]);
        })->unique('id')->sortBy('name')->values();

        return view('pages.compare', compact('products', 'items', 'allAttributes'));
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
            'user_id'    => auth()->id(),
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
            $items->filter(fn($i) => $i->product)
                  ->map(function ($i) {
                      $p = $i->product;
                      $images = is_array($p->images) ? $p->images : [];
                      $img = $images[0] ?? null;
                      return [
                          'id'    => $p->id,
                          'name'  => $p->name,
                          'url'   => '/' . ($p->category->slug ?? 'catalog') . '/' . $p->slug,
                          'image' => $img
                              ? 'https://kotlov.by/images/product/' . $img
                              : asset('img/products/product-placeholder.jpg'),
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
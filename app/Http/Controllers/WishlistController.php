<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    private function getSessionId(): string
    {
        return session()->getId();
    }

    private function baseQuery()
    {
        $query = WishlistItem::query();
        if (auth()->check()) {
            $query->where('user_id', auth()->id());
        } else {
            $query->where('session_id', $this->getSessionId());
        }
        return $query;
    }

    public function index()
    {
        $items = $this->baseQuery()
            ->with(['product' => fn($q) => $q->with(['category', 'brand'])])
            ->latest()
            ->get();

        $products = $items->pluck('product')->filter();

        return view('pages.wishlist', compact('products'));
    }

    public function add(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $exists = $this->baseQuery()
            ->where('product_id', $request->product_id)
            ->exists();

        if (!$exists) {
            WishlistItem::create([
                'product_id' => $request->product_id,
                'user_id'    => auth()->id(),
                'session_id' => $this->getSessionId(),
            ]);
            $message = 'added';
        } else {
            $message = 'already_added';
        }

        return response()->json([
            'message' => $message,
            'count'   => $this->baseQuery()->count(),
        ]);
    }

    public function remove(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $this->baseQuery()
            ->where('product_id', $request->product_id)
            ->delete();

        return response()->json([
            'message' => 'removed',
            'count'   => $this->baseQuery()->count(),
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $item = $this->baseQuery()
            ->where('product_id', $request->product_id)
            ->first();

        if ($item) {
            $item->delete();
            $message = 'removed';
        } else {
            WishlistItem::create([
                'product_id' => $request->product_id,
                'user_id'    => auth()->id(),
                'session_id' => $this->getSessionId(),
            ]);
            $message = 'added';
        }

        return response()->json([
            'message' => $message,
            'count'   => $this->baseQuery()->count(),
        ]);
    }
}

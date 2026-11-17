<?php

namespace App\Services;

use App\Models\CustomerCart;
use Illuminate\Http\Request;

class CustomerCartStore
{
    public const META = ['cart_note', 'cart_coupon', 'cart_delivery_region', 'cart_delivery_city', 'cart_selection_id', 'cart_selection_revision', CartSelection::KEY];

    public function meta(Request $request): array
    {
        return $request->session()->only(self::META);
    }

    public function hydrate(Request $request, int $userId): void
    {
        $owner = $request->session()->get('cart_owner_id');
        $guestItems = $owner === null ? $request->session()->get('cart', []) : [];
        $guestMeta = $owner === null ? $this->meta($request) : [];
        $saved = CustomerCart::firstOrCreate(['user_id' => $userId], [
            'items' => $owner === null || (int) $owner === $userId ? $request->session()->get('cart', []) : [],
            'meta' => $owner === null || (int) $owner === $userId ? $this->meta($request) : [],
        ]);
        $items = $saved->items ?? [];
        // Keep both the account cart and new guest positions on sign-in.
        foreach ($guestItems as $id => $item) {
            if (! isset($items[$id])) $items[$id] = $item;
            else $items[$id]['quantity'] = max((int) $items[$id]['quantity'], (int) $item['quantity']);
        }
        $request->session()->forget(self::META);
        $request->session()->put(array_replace($saved->meta ?? [], $guestMeta));
        $request->session()->put(['cart' => $items, 'cart_owner_id' => $userId]);
        if ($guestItems !== []) $this->save($request, $userId);
    }

    public function save(Request $request, int $userId): void
    {
        CustomerCart::updateOrCreate(['user_id' => $userId], [
            'items' => $request->session()->get('cart', []), 'meta' => $this->meta($request),
        ]);
    }
}

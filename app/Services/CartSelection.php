<?php

namespace App\Services;

final class CartSelection
{
    public const KEY = 'cart_excluded_ids';

    public static function items(array $cart): array
    {
        return array_diff_key($cart, array_flip(session(self::KEY, [])));
    }

    // Checkout reconciles selected lines only. Keep every postponed line intact.
    public static function merge(array $selected): array
    {
        $all = session('cart', []);
        return array_diff_key($all, self::items($all)) + $selected;
    }

    public static function complete(array $orderedIds): void
    {
        $remaining = array_diff_key(session('cart', []), array_flip($orderedIds));
        if ($remaining === []) {
            session()->forget(array_merge(['cart'], CustomerCartStore::META));
            return;
        }
        session(['cart' => $remaining]);
        session([self::KEY => array_values(array_intersect(session(self::KEY, []), array_keys($remaining)))]);
    }
}

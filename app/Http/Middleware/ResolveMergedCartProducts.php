<?php

namespace App\Http\Middleware;

use App\Services\MergedProductIdentity;
use Closure;
use Illuminate\Http\Request;

class ResolveMergedCartProducts
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->hasSession() || $request->is('admin', 'admin/*', 'integrations/*', '1c/*')) {
            return $next($request);
        }
        if ($request->session()->get('cart', []) === [] && ! $request->is('cart/*', 'account/selections/*', 'b2b/workspace/*', 'b2b/selections/*')) {
            return $next($request);
        }
        $identity = app(MergedProductIdentity::class);
        $map = $identity->map();
        if (! $map) {
            return $next($request);
        }
        // Compute both first: a conflicting line leaves the original session untouched.
        $cart = $identity->items($request->session()->get('cart', []), $map);
        $excluded = $identity->meta(['cart_excluded_ids' => $request->session()->get('cart_excluded_ids', [])], $map);
        $request->session()->put(['cart' => $cart] + $excluded);
        if ($request->is('cart/*', 'account/selections/*', 'b2b/workspace/*', 'b2b/selections/*')) {
            foreach (['id', 'product_id'] as $field) {
                $id = (int) $request->input($field);
                if (isset($map[$id])) {
                    $request->merge([$field => $map[$id]]);
                }
            }
        }

        return $next($request);
    }
}

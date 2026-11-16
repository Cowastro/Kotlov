<?php

namespace App\Http\Middleware;

use App\Services\CustomerCartStore;
use App\Models\CustomerSelection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class PersistCustomerCart
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('admin', 'admin/*', 'integrations/*', '1c/*') || ! $request->hasSession()) return $next($request);

        $store = app(CustomerCartStore::class);
        $userId = $request->user()?->id;
        if (! $userId) {
            $response = $next($request);
            if ($signedIn = $request->user()?->id) {
                Cache::lock('customer-cart:'.$signedIn, 30)->block(10, fn () => $store->hydrate($request, $signedIn));
            }
            return $response;
        }

        $cartRequest = $request->is('cart', 'cart/*', 'checkout', 'checkout/*', 'logout', 'account/selections')
            || ! $request->isMethodSafe();
        if (! $cartRequest && (int) $request->session()->get('cart_owner_id') === (int) $userId) return $next($request);

        // Hydrate inside the lock so concurrent adds use the latest account cart.
        try {
        return Cache::lock('customer-cart:'.$userId, 120)->block(10, function () use ($request, $next, $store, $userId) {
            $store->hydrate($request, $userId);
            $before = [$request->session()->get('cart', []), $store->meta($request)];
            $response = $next($request);
            if ($response->getStatusCode() < 400 && $request->user()?->id === $userId
                && $before !== [$request->session()->get('cart', []), $store->meta($request)]) {
                // Availability reconciliation may remove lines without a user
                // pressing Remove. Keep their complete original selection.
                if (($request->isMethodSafe() || $request->is('cart/update'))
                    && array_diff_key($before[0], $request->session()->get('cart', [])) !== []) {
                    CustomerSelection::create([
                        'user_id' => $userId, 'name' => 'Корзина до проверки доступности · '.now()->format('d.m.Y H:i'),
                        'items' => $before[0], 'meta' => $before[1], 'note' => $before[1]['cart_note'] ?? null,
                    ]);
                    $request->session()->flash('success', 'До обновления доступности сохранена копия корзины в разделе «Мои подборы».');
                }
                $store->save($request, $userId);
            }
            return $response;
        });
        } catch (LockTimeoutException $exception) {
            $message = 'Корзина сейчас сохраняется в другой вкладке. Повторите действие через несколько секунд.';
            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : to_route('cart')->with('error', $message);
        }
    }
}


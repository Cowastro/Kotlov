<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Models\Product;
use App\Services\B2bCatalogOfferResolver;
use App\Services\B2bCompanyAccess;
use App\Services\ContactRequestNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class B2bPriceRequestController extends Controller
{
    public function __invoke(
        Request $request,
        B2bCatalogOfferResolver $resolver,
        B2bCompanyAccess $access,
        ContactRequestNotifier $notifier,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user && $access->allows($user, 'catalog'), 403);

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::query()
            ->active()
            ->notArchived()
            ->with(['category', 'brand', 'integrationProducts.source', 'supplierProducts.supplier'])
            ->findOrFail($validated['product_id']);

        if ($resolver->forProduct($product, $user)) {
            return response()->json([
                'message' => 'Оптовая цена уже доступна. Обновите каталог и добавьте товар в корзину.',
            ], 422);
        }

        $existing = ContactRequest::query()
            ->where('user_id', $user->id)
            ->where('source', 'b2b_price_request')
            ->where('product_id', $product->id)
            ->whereIn('status', ContactRequest::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'request_id' => $existing->id,
                'status' => $existing->status,
                'status_label' => ContactRequest::$statuses[$existing->status] ?? $existing->status,
                'message' => 'Запрос уже передан менеджеру.',
            ]);
        }

        $quantity = (int) $validated['quantity'];
        $supplierNames = $product->integrationProducts
            ->pluck('source.name')
            ->merge($product->supplierProducts->pluck('supplier.name'))
            ->filter()
            ->unique()
            ->values();
        $company = $user->company_name ?: $user->name;
        $unp = $user->company_inn ? ' · УНП/ИНН '.$user->company_inn : '';
        $publicPrice = (float) $product->price > 0
            ? number_format((float) $product->price, 2, '.', ' ').' BYN'
            : 'не указана';

        $priceRequest = ContactRequest::query()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => $user->phone ?: 'не указан',
            'email' => $user->email,
            'message' => implode("\n", [
                'Запрос оптовой цены из B2B-каталога.',
                'Компания: '.$company.$unp.'.',
                'Количество: '.$quantity.' '.($product->unit ?: 'шт.').'.',
                'Розничная цена на момент запроса: '.$publicPrice.'.',
                'Известные каналы поставки: '.($supplierNames->join(', ') ?: 'не определены').'.',
            ]),
            'status' => 'new',
            'product_id' => $product->id,
            'requested_quantity' => $quantity,
            'product_name' => implode(' · ', array_filter([$product->name, $product->sku])),
            'product_url' => url('/'.($product->category?->slug ?? 'catalog').'/'.$product->slug),
            'source' => 'b2b_price_request',
        ]);

        $notifier->notify($priceRequest);

        return response()->json([
            'success' => true,
            'request_id' => $priceRequest->id,
            'status' => $priceRequest->status,
            'status_label' => ContactRequest::$statuses[$priceRequest->status],
            'message' => 'Запрос оптовой цены отправлен. Менеджер уже видит всю информацию.',
        ], 201);
    }
}

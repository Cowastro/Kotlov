<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Shipping\EvropochtaCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProductDeliveryEstimateController extends Controller
{
    public const SKU = 'PS-011.094';

    public function __invoke(Request $request, EvropochtaCheckout $checkout): JsonResponse
    {
        $input = $request->validate(['branch' => ['sometimes', 'required', 'integer', 'min:1']]);
        $product = Product::query()->orderable()->where('sku', self::SKU)->firstOrFail();
        $assessment = $checkout->assess([['id' => $product->id, 'quantity' => 1]]);
        $parcel = $assessment['parcel'];
        if ($parcel === null) {
            return response()->json(['message' => $assessment['message']], 422);
        }

        try {
            if (isset($input['branch'])) {
                $estimate = $checkout->estimate($parcel, (int) $input['branch']);

                return response()->json(['price' => $estimate['customer_price'], 'address' => $estimate['address']]);
            }

            $labels = array_map(fn (array $branch) => $branch['label'], $checkout->destinations($parcel));

            return response()->json(['branches' => $labels]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => 'Не удалось рассчитать доставку. Попробуйте ещё раз.'], 503);
        }
    }
}

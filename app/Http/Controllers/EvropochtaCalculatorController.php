<?php

namespace App\Http\Controllers;

use App\Services\Shipping\EvropochtaCheckout;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class EvropochtaCalculatorController extends Controller
{
    public function __invoke(Request $request, EvropochtaCheckout $checkout)
    {
        $parcel = ['store_id_start' => config('services.evropochta.checkout_origin')];
        $estimate = null;
        $values = [];

        try {
            $branches = $checkout->destinations($parcel);
        } catch (RuntimeException) {
            $branches = [];
        }

        if ($request->isMethod('post')) {
            $values = $request->validate([
                'evropochta_branch' => ['required', 'integer', 'min:1'],
                'weight' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:50'],
                'length' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
                'width' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
                'height' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:250'],
            ], [], [
                'evropochta_branch' => 'отделение получения', 'weight' => 'вес',
                'length' => 'длина', 'width' => 'ширина', 'height' => 'высота',
            ]);

            try {
                $estimate = $checkout->estimate($parcel + $values, (int) $values['evropochta_branch']);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['estimate' => $exception->getMessage()]);
            }
        }

        return view('pages.evropochta-calculator', [
            'branches' => $branches,
            'boxes' => config('evropochta_packaging', []),
            'values' => $values,
            'estimate' => $estimate,
        ]);
    }
}

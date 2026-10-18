<?php

namespace App\Http\Controllers;

use App\Services\Crm\BelarusTaxpayerRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxpayerLookupController extends Controller
{
    public function __invoke(Request $request, BelarusTaxpayerRegistry $registry): JsonResponse
    {
        $validated = $request->validate([
            'unp' => ['required', 'regex:/^\d{9}$/'],
        ], [
            'unp.required' => 'Введите УНП организации.',
            'unp.regex' => 'УНП должен состоять из 9 цифр.',
        ]);

        $company = $registry->lookup($validated['unp']);
        if (! $company) {
            return response()->json([
                'message' => 'Организация с таким УНП не найдена. Проверьте номер или заполните реквизиты вручную.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'unp' => $company['unp'],
                'legal_name' => $company['legal_name'] ?? null,
                'short_name' => $company['short_name'] ?? null,
                'address' => $company['address'] ?? null,
                'status' => $company['status'] ?? null,
                'source' => $company['source'] ?? 'Реестр МНС Республики Беларусь',
            ],
        ]);
    }
}

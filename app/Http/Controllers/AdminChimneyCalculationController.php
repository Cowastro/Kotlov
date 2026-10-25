<?php

namespace App\Http\Controllers;

use App\Filament\Pages\ChimneyCalculator;
use App\Models\ChimneyCalculationRequest;
use App\Rules\PhoneNotSpam;
use App\Services\ChimneyCalculationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminChimneyCalculationController extends Controller
{
    public function store(Request $request, ChimneyCalculationRequestService $service): JsonResponse
    {
        abort_unless($request->user()?->isManager() === true, 403);

        $validated = $request->validate([
            'calculation_id' => ['nullable', 'integer', 'exists:chimney_calculation_requests,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50', new PhoneNotSpam],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:1000'],
            'configuration' => ['nullable', 'string', 'max:12000'],
            'items' => ['required', 'array', 'min:1', 'max:80'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'equipment_product_id' => ['nullable', 'integer', 'min:1'],
            'selector_token' => ['required', 'string', 'min:32', 'max:100'],
        ], [
            'customer_name.required' => 'Укажите имя клиента.',
            'customer_phone.required' => 'Укажите телефон клиента.',
            'items.required' => 'Сначала рассчитайте комплект дымохода.',
        ]);

        if (! hash_equals((string) $request->session()->get('chimney_selector_token'), $validated['selector_token'])) {
            throw ValidationException::withMessages(['items' => 'Расчёт устарел. Обновите страницу и соберите комплект ещё раз.']);
        }

        $calculationId = (int) ($validated['calculation_id'] ?? 0);
        unset($validated['selector_token'], $validated['calculation_id']);

        if ($calculationId > 0) {
            $calculation = ChimneyCalculationRequest::query()->findOrFail($calculationId);
            if ($calculation->status !== 'pending') {
                throw ValidationException::withMessages(['calculation_id' => 'Заказ уже создан. Этот расчёт больше нельзя изменять.']);
            }
            $calculation = DB::transaction(function () use ($calculation, $service, $validated): ChimneyCalculationRequest {
                $calculation->update([
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'customer_email' => $validated['customer_email'] ?? null,
                    'source_url' => $validated['source_url'] ?? null,
                    'configuration' => $validated['configuration'] ?? null,
                    'equipment_product_id' => $validated['equipment_product_id'] ?? null,
                ]);

                return $service->revise($calculation, $validated['items']);
            });
            $message = "Расчёт {$calculation->number} обновлён.";
        } else {
            $calculation = $service->create($validated);
            $message = "Расчёт {$calculation->number} создан.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'request_id' => $calculation->id,
            'admin_url' => ChimneyCalculator::getUrl(['calculation' => $calculation->id]),
        ]);
    }
}

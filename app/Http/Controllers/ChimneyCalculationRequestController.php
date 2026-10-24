<?php

namespace App\Http\Controllers;

use App\Rules\PhoneNotSpam;
use App\Services\ChimneyCalculationRequestService;
use App\Services\ChimneyCalculationRequestTelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ChimneyCalculationRequestController extends Controller
{
    public function store(
        Request $request,
        ChimneyCalculationRequestService $service,
        ChimneyCalculationRequestTelegramNotifier $notifier,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
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
            'customer_name.required' => 'Укажите имя.',
            'customer_phone.required' => 'Укажите телефон.',
            'items.required' => 'Сначала рассчитайте комплект дымохода.',
        ]);

        if (! hash_equals((string) $request->session()->get('chimney_selector_token'), $validated['selector_token'])) {
            throw ValidationException::withMessages(['items' => 'Расчёт устарел. Обновите страницу и соберите комплект ещё раз.']);
        }

        unset($validated['selector_token']);
        $calculation = $service->create($validated);
        $notifier->notify($calculation);

        $message = "Расчёт {$calculation->number} получен. Менеджер проверит комплект и подтвердит создание заказа.";
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message, 'request_id' => $calculation->id]);
        }

        return back()->with('success', $message);
    }
}

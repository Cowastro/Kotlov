<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Rules\PhoneNotSpam;
use App\Services\ContactRequestNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactRequestController extends Controller
{
    public function store(Request $request, ContactRequestNotifier $notifier): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50', new PhoneNotSpam],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'product_url' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $contactRequest = ContactRequest::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'message' => $validated['message'] ?? '',
            'product_id' => $validated['product_id'] ?? null,
            'product_name' => $validated['product_name'] ?? null,
            'product_url' => $validated['product_url'] ?? null,
            'city' => $validated['city'] ?? null,
            'source' => $validated['source'] ?? null,
            'status' => 'new',
        ]);

        $notifier->notify($contactRequest);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => ($validated['source'] ?? null) === 'quick_callback'
                    ? 'Спасибо! Номер принят — скоро перезвоним.'
                    : 'Спасибо! Вопрос отправлен консультанту.',
                'request_id' => $contactRequest->id,
            ]);
        }

        return back()->with('success', 'Спасибо! Ваша заявка отправлена. Мы скоро свяжемся с вами.');
    }
}

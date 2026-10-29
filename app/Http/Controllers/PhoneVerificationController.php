<?php

namespace App\Http\Controllers;

use App\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PhoneVerificationController extends Controller
{
    public function store(Request $request, PhoneVerificationService $verification): JsonResponse
    {
        if (! config('services.sms_by.enabled')) {
            return response()->json([
                'message' => 'Подтверждение телефона пока недоступно.',
            ], 503);
        }

        $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        try {
            $challenge = $verification->request($request->string('phone')->toString(), $request->ip());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('SMS.BY verification send failed.', [
                'exception_type' => $exception::class,
            ]);

            return response()->json([
                'message' => 'Не удалось отправить код. Попробуйте немного позже.',
            ], 503);
        }

        return response()->json([
            'message' => 'Код отправлен на указанный номер.',
            'verification_token' => $challenge->token,
            'expires_in' => max(60, (int) config('services.sms_by.verification_ttl', 300)),
        ]);
    }
}

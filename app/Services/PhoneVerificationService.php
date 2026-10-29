<?php

namespace App\Services;

use App\Models\PhoneVerificationChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PhoneVerificationService
{
    public function __construct(private readonly SmsByClient $sms)
    {
    }

    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '80') && strlen($digits) === 11) {
            $digits = '375'.substr($digits, 2);
        }

        if (strlen($digits) !== 12 || ! str_starts_with($digits, '375')) {
            throw ValidationException::withMessages([
                'phone' => 'Введите белорусский номер в формате +375 (XX) XXX-XX-XX.',
            ]);
        }

        return $digits;
    }

    public function request(string $phone, ?string $ip): PhoneVerificationChallenge
    {
        $phone = $this->normalize($phone);
        $phoneKey = 'registration-sms:phone:'.hash('sha256', $phone);
        $ipKey = 'registration-sms:ip:'.hash('sha256', (string) $ip);

        if (RateLimiter::tooManyAttempts($phoneKey, 3)
            || RateLimiter::tooManyAttempts($ipKey, 10)) {
            throw ValidationException::withMessages([
                'phone' => 'Слишком много запросов. Повторите попытку позже.',
            ]);
        }

        $latest = PhoneVerificationChallenge::query()
            ->where('phone', $phone)
            ->latest('id')
            ->first();

        if ($latest?->created_at?->gt(now()->subMinute())) {
            throw ValidationException::withMessages([
                'phone' => 'Новый код можно запросить через минуту.',
            ]);
        }

        RateLimiter::hit($phoneKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $result = $this->sms->sendVerificationCode($phone);

        PhoneVerificationChallenge::query()
            ->where('expires_at', '<', now()->subDay())
            ->delete();

        return PhoneVerificationChallenge::create([
            'token' => (string) Str::uuid(),
            'phone' => $phone,
            'code_hash' => Hash::make($result['code']),
            'sms_id' => $result['sms_id'],
            'expires_at' => now()->addSeconds(max(60, (int) config('services.sms_by.verification_ttl', 300))),
            'request_ip_hash' => hash('sha256', (string) $ip),
        ]);
    }

    public function verifyAndConsume(string $token, string $phone, string $code): PhoneVerificationChallenge
    {
        $phone = $this->normalize($phone);
        $challenge = PhoneVerificationChallenge::query()
            ->where('token', $token)
            ->where('phone', $phone)
            ->first();

        if (! $challenge || $challenge->verified_at || $challenge->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'phone_code' => 'Код недействителен или срок его действия истёк.',
            ]);
        }

        if ($challenge->attempts >= 5) {
            throw ValidationException::withMessages([
                'phone_code' => 'Превышено количество попыток. Запросите новый код.',
            ]);
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            throw ValidationException::withMessages([
                'phone_code' => 'Неверный код подтверждения.',
            ]);
        }

        $challenge->forceFill(['verified_at' => now()])->save();

        return $challenge;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ProtectPublicForm
{
    public function handle(Request $request, Closure $next, string $formKey = 'form'): Response
    {
        // 1. Honeypot — поле должно оставаться пустым
        if ($request->filled('fax_number')) {
            $this->logBlocked($request, $formKey, 'honeypot');
            return $this->fakeSuccess($request);
        }

        // 2. Время заполнения — слишком быстро = бот
        $startedAt = (int) $request->input('form_started_at', 0);
        if ($startedAt > 0 && (time() - $startedAt) < 3) {
            $this->logBlocked($request, $formKey, 'too_fast', ['elapsed' => time() - $startedAt]);
            return $this->fakeSuccess($request);
        }

        // 3. Rate limit: 5 отправок за 10 минут с одного IP
        $rateLimitKey = 'form:' . $formKey . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $this->logBlocked($request, $formKey, 'rate_limit');
            return $this->fakeSuccess($request);
        }
        RateLimiter::hit($rateLimitKey, 600);

        // 4. Cloudflare Turnstile (если включён)
        if (config('services.turnstile.enabled')) {
            $token = $request->input('cf-turnstile-response', '');
            if (! $this->verifyTurnstile($token, $request->ip())) {
                $this->logBlocked($request, $formKey, 'turnstile');
                return $this->fakeSuccess($request);
            }
        }

        return $next($request);
    }

    private function fakeSuccess(Request $request): Response
    {
        // Не показываем ошибку — делаем вид, что форма принята
        return back()->with('success', 'Сообщение отправлено! Мы свяжемся с вами в течение рабочего дня.');
    }

    private function logBlocked(Request $request, string $formKey, string $reason, array $extra = []): void
    {
        Log::warning('Form spam blocked', array_merge([
            'ip'     => $request->ip(),
            'ua'     => $request->userAgent(),
            'form'   => $formKey,
            'route'  => $request->route()?->getName() ?? $request->path(),
            'reason' => $reason,
        ], $extra));
    }

    private function verifyTurnstile(string $token, string $ip): bool
    {
        if ($token === '') {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                [
                    'secret'   => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]
            );

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            // Fail open — не блокируем пользователей при проблемах с Turnstile
            Log::error('Turnstile verification failed: ' . $e->getMessage());
            return true;
        }
    }
}

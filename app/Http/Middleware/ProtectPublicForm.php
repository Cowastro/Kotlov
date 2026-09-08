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
        // 1. Honeypot — поле должно оставаться пустым (JS сбрасывает его у реальных пользователей)
        if ($request->filled('_hpf')) {
            $this->logBlocked($request, $formKey, 'honeypot');
            return $this->fakeSuccess($request);
        }

        // 2. Время заполнения: слишком быстро = бот, слишком давно = реплей/скрипт
        $startedAt = (int) $request->input('form_started_at', 0);
        $elapsed   = $startedAt > 0 ? time() - $startedAt : null;
        if ($elapsed !== null && $elapsed < 5) {
            $this->logBlocked($request, $formKey, 'too_fast', ['elapsed' => $elapsed]);
            return $this->fakeSuccess($request);
        }
        if ($elapsed !== null && $elapsed > 3600) {
            // Форма открыта больше часа назад — скорее всего автоматическая отправка
            $this->logBlocked($request, $formKey, 'stale_form', ['elapsed' => $elapsed]);
            return $this->fakeSuccess($request);
        }

        // 3. Rate limit: 3 отправки за 10 минут с одного IP (было 5)
        $rateLimitKey = 'form:' . $formKey . ':' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            $this->logBlocked($request, $formKey, 'rate_limit');
            return $this->fakeSuccess($request);
        }
        RateLimiter::hit($rateLimitKey, 600);

        // 4b. Подозрительный User-Agent — пустой или явно автоматизированный
        $ua = $request->userAgent() ?? '';
        if ($ua === '' || preg_match('/python|curl|wget|scrapy|headless|phantomjs|selenium|go-http|java\/|axios|node-fetch/i', $ua)) {
            $this->logBlocked($request, $formKey, 'bad_ua', ['ua' => $ua]);
            return $this->fakeSuccess($request);
        }

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

<?php

use App\Http\Middleware\AdminNoIndex;
use App\Http\Middleware\CitySubdomain;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\ProtectPublicForm;
use App\Http\Middleware\RequireRecentB2bPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Sitemap без session/cookie middleware
            Route::middleware([])->group(
                base_path('routes/sitemap.php')
            );
        },
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Исключаем Telegram webhook из CSRF-проверки
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook',
            '1c/exchange',
            '1c/exchange/*',
            'integrations/a1/telephony/*',
            'webpay/notify',
        ]);

        // SSL на балансировщике — доверяем X-Forwarded-For для получения реального IP
        $middleware->trustProxies(at: '*');

        // 301 редиректы со старого сайта — обрабатывает 404 и ищет в таблице redirects
        $middleware->append(HandleRedirects::class);
        $middleware->append(CitySubdomain::class);
        $middleware->append(AdminNoIndex::class);

        $middleware->alias([
            'public.form.protect' => ProtectPublicForm::class,
            'b2b.password.recent' => RequireRecentB2bPassword::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 301 редиректы со старого сайта — обрабатывает 404 и ищет в таблице redirects
        $middleware->append(\App\Http\Middleware\HandleRedirects::class);
        $middleware->append(\App\Http\Middleware\CitySubdomain::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
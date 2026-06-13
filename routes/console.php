<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Ежедневное обновление Русклимат ──────────────────────────────────────────
// Каждый день в 06:00: обновляет цены/наличие, создаёт новые товары с фото и AI-описаниями.
// Сервер: добавить в cron → * * * * * cd /var/www/h209767/data/www/new.kotlov.by && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
Schedule::command('supplier:sync-rusklimat --apply --create-new --enrich')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rusklimat-sync.log'));

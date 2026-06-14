<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Русклимат: ежедневная синхронизация ──────────────────────────────────────
// Сервер cron (один раз): * * * * * cd /var/www/h209767/data/www/new.kotlov.by && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
//
// Ежедневно обновляем ТОЛЬКО цены, наличие и создаём новые товары.
// Без AI и без массовой загрузки фото — это быстро, стабильно и не зависит от API.
//
// Фото, характеристики и описания заполняются ОТДЕЛЬНО и ВРУЧНУЮ, только для
// пустых карточек, и в cron не выносятся (дорого/долго, после первого заполнения
// повторять не нужно):
//   php artisan supplier:enrich-rusklimat --skip-content   # фото + характеристики, без AI
//   php artisan supplier:enrich-rusklimat --ai-only        # AI-описания (по согласованию)
Schedule::command('supplier:sync-rusklimat --apply --create-new')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rusklimat-sync.log'));

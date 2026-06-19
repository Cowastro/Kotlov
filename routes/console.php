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

// Rusklimat: frequent lightweight supplier cost/stock refresh for already linked products.
// Does not create products, download images, generate descriptions, or change products.price.
Schedule::command('supplier:sync-rusklimat --apply --only-existing --no-images')
    ->hourlyAt(10)
    ->unlessBetween('05:45', '06:45')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rusklimat-price-stock-sync.log'));

// BANIA: регулярно обновляет закупку, наличие и розничные цены по уже связанным товарам.
// Новые товары не создаются, сомнительные совпадения уходят в CSV-отчёт.
Schedule::command('supplier:sync-bania-pricelist --apply --sync-retail-prices')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bania-pricelist-sync.log'));

// Лигмет: ежедневно обновляем цены и наличие печей/каминов/топок.
// Новые товары не создаются автоматически — только обновление уже связанных.
Schedule::command('supplier:sync-ligmet --apply')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ligmet-sync.log'));

// ТСК Насосы: ежедневно обновляем цены и наличие насосного оборудования.
Schedule::command('supplier:sync-tsk-nasosy --apply')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tsk-nasosy-sync.log'));

// ГазКотелБел (ЖИТОМИР / GKB): ежедневно обновляем цены, остатки и РРЦ.
// Курс RUB→BYN уточнять при изменении: --rub-byn-rate=X
Schedule::command('supplier:sync-gazkotelbel --apply --rub-byn-rate=0.036')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/gazkotelbel-sync.log'));

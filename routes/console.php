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

// RN-Profi: обновляем цены и наличие кусками по вкладкам Google Sheet.
// Только уже связанные товары; создание карточек и обогащение источников запускаем отдельно.
Schedule::command('supplier:sync-rn-profi-chunks --apply --sync-retail-prices --only-linked')
    ->dailyAt('06:17')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rn-profi-price-stock-sync.log'));

// Майтек Групп: ежедневно обновляем цены, наличие и source_url по уже связанным товарам.
// Новые товары и обогащение карточек запускаются отдельно после проверки.
Schedule::command('supplier:sync-maitek-group --apply --available-only --sync-retail-prices')
    ->dailyAt('06:37')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/maitek-group-price-stock-sync.log'));

// Thermostudio: safe daily price/stock refresh for already linked products only.
Schedule::command('supplier:sync-thermostudio-pricelist --apply --available-only --only-linked --sync-retail-prices')
    ->dailyAt('06:47')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/thermostudio-pricelist-sync.log'));

// Akvatermex: safe daily price/stock refresh for already linked Thermex group products only.
Schedule::command('supplier:sync-akvatermex --apply --available-only --only-linked --sync-retail-prices --prefer-teplodvor-source')
    ->dailyAt('06:57')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/akvatermex-pricelist-sync.log'));

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
// Прайс в BYN — конвертация не нужна.
Schedule::command('supplier:sync-gazkotelbel --apply')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/gazkotelbel-sync.log'));

<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('integration:scan-issues')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

Schedule::command('integration:prune-exchange-files')
    ->dailyAt('03:20')
    ->timezone('Europe/Minsk')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/integration-exchange-cleanup.log'));

Schedule::command('telephony:sync-a1')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/a1-telephony-sync.log'));

Schedule::command('crm:mail-sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/crm-mail-sync.log'));

Schedule::command('commercial-documents:expire-quotes')
    ->dailyAt('00:15')
    ->timezone('Europe/Minsk')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/commercial-documents.log'));

Schedule::command('commercial-documents:notify-control')
    ->dailyAt('09:00')
    ->timezone('Europe/Minsk')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/commercial-documents.log'));

// Черновики попадают в кабинет только у поставщиков, для которых администратор
// отдельно включил автоматическую передачу после успешного контрольного периода.
// У всех существующих поставщиков настройка по умолчанию выключена.
Schedule::command('orders:auto-publish-supplier-requests --apply')
    ->everyTwoMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/supplier-order-auto-transfer.log'));

// Рыночные цены: команда сама выбирает только активные, явно разрешённые и
// наступившие по расписанию источники. Без адаптера или разрешения запросов нет.
Schedule::command('market:collect-prices')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/market-price-collection.log'));

// Автоматически поднимаем востребованные товары по свежим уникальным просмотрам,
// оценкам и отзывам. Старые просмотры имеют небольшой вес и не фиксируют выдачу навсегда.
Schedule::command('catalog:recalculate-popularity')
    ->hourlyAt(40)
    ->timezone('Europe/Minsk')
    ->withoutOverlapping(15)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/catalog-popularity.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$supplierSyncTimezone = 'Europe/Minsk';

// Omenergo daily maintenance never creates products or rewrites content/media.
Schedule::command('supplier:sync-omenergo --apply')
    ->dailyAt('07:45')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/omenergo-sync.log'));

// ── Русклимат: ежедневная синхронизация ──────────────────────────────────────
// Сервер cron (один раз): * * * * * cd /var/www/h209767/data/www/new.kotlov.by && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
//
// Ежедневно обновляем ТОЛЬКО цены и наличие уже связанных товаров.
// Новые связи и карточки создаются лишь отдельным ручным импортом после проверки.
// Без AI и без массовой загрузки фото — это быстро, стабильно и не зависит от API.
//
// Фото, характеристики и описания заполняются ОТДЕЛЬНО и ВРУЧНУЮ, только для
// пустых карточек, и в cron не выносятся (дорого/долго, после первого заполнения
// повторять не нужно):
//   php artisan supplier:enrich-rusklimat --skip-content   # фото + характеристики, без AI
//   php artisan supplier:enrich-rusklimat --ai-only        # AI-описания (по согласованию)
Schedule::command('supplier:sync-rusklimat-pricelist --apply --sync-retail-prices')
    ->dailyAt('07:00')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rusklimat-sync.log'));

// BANIA: раз в сутки обновляет закупку, наличие и розничные цены по уже связанным товарам.
// Новые товары не создаются, сомнительные совпадения уходят в CSV-отчёт.
Schedule::command('supplier:sync-bania-pricelist --apply --sync-retail-prices --repair-supplier-articles --folder-url=https://drive.google.com/drive/folders/108zmF6VlM-AWiRgXaMK9BFFqgz2iAdNG')
    ->dailyAt('07:10')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/bania-pricelist-sync.log'));

// RN-Profi: обновляем цены и наличие кусками по вкладкам Google Sheet.
// Только уже связанные товары; создание карточек и обогащение источников запускаем отдельно.
Schedule::command('supplier:sync-rn-profi-chunks --apply --sync-retail-prices --only-linked')
    ->dailyAt('07:20')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/rn-profi-price-stock-sync.log'));

// Майтек Групп: ежедневно обновляем цены, наличие и source_url по уже связанным товарам.
// Новые товары и обогащение карточек запускаются отдельно после проверки.
Schedule::command('supplier:sync-maitek-group --apply --sync-retail-prices')
    ->dailyAt('07:40')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/maitek-group-price-stock-sync.log'));

// Thermostudio: frequent safe price/stock refresh for already linked products only.
Schedule::command('supplier:sync-thermostudio-pricelist --apply --only-linked --sync-retail-prices --mark-missing')
    ->hourlyAt(20)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/thermostudio-pricelist-sync.log'));

// Akvatermex: safe daily price/stock refresh for already linked Thermex group products only.
Schedule::command('supplier:sync-akvatermex --apply --only-linked --sync-retail-prices --prefer-teplodvor-source')
    ->dailyAt('08:00')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/akvatermex-pricelist-sync.log'));

// Лигмет: ежедневно обновляем цены и остатки всего каталога.
// Поставщик заменяет файл в общей папке, поэтому каждый запуск сам выбирает
// самый свежий XLS/XLSX. Новые позиции получают непубличные карточки и
// постоянную связь по артикулу; публикация — после фото и проверки контента.
Schedule::command('supplier:sync-ligmet --apply --all-categories --create-new --link-existing-suggestions --min-suggestion-score=99.9 --folder-url=https://drive.google.com/drive/folders/1pQQRGMKBEHHEjUF3AYsi_dTvlxxTFxzz')
    ->dailyAt('08:10')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ligmet-sync.log'));

// Страховка витрины: печи без активной связи с поставщиком не должны
// показывать устаревшие цены или неподтверждённое наличие.
Schedule::command('supplier:reconcile-availability --apply')
    ->hourlyAt(20)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/supplier-availability-reconcile.log'));

// ТСК Насосы: ежедневно обновляем цены и наличие насосного оборудования.
Schedule::command('supplier:sync-tsk-nasosy --apply')
    ->dailyAt('08:20')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tsk-nasosy-sync.log'));

// Курсы НБРБ: ежедневно загружаем EUR/USD/RUB и пересчитываем BYN цены
// для поставщиков с иностранной валютой (S-TANK и др.).
Schedule::command('currency:fetch-nbrb-rates')
    ->dailyAt('06:55')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/nbrb-rates.log'));

// ГазКотелБел (ЖИТОМИР / GKB): ежедневно обновляем цены, остатки и РРЦ.
// Прайс в BYN — конвертация не нужна.
Schedule::command('supplier:sync-gazkotelbel --apply')
    ->dailyAt('08:30')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/gazkotelbel-sync.log'));

// СВАГ Груп / TATRA / SVAH / HOT: партнёрская цена, отпускная цена и точный остаток
// из последнего загруженного XLS/XLSX. Новый файл сначала применяется вручную
// с --price-file; после успешной проверки он становится источником daily-sync.
// Новые позиции создаются автоматически как непубличные карточки-черновики.
Schedule::command('supplier:sync-svah-group --apply --sync-retail-prices')
    ->dailyAt('08:35')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/svah-group-price-stock-sync.log'));

// TM Management: once a day refresh all price-list sheets in one run.
// This also keeps the synchronization journal concise: one supplier entry per day.
Schedule::command('supplier:sync-tm-management --apply')
    ->dailyAt('08:40')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(180)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tm-management-sync.log'));

// S-TANK: daily refresh after the NBRB rate and the main supplier chain.
// Price-list values are maintained in EUR; the command recalculates the BYN retail price.
Schedule::command('supplier:sync-stank')
    ->dailyAt('08:50')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stank-sync.log'));

// После всех основных прайсов страховка блокирует оформление товара, если
// розница оказалась ниже закупки, маржа упала ниже минимума или цена выглядит
// кратно завышенной. Цена не переписывается автоматически: менеджер видит
// карточку, но продажа ждёт подтверждения.
Schedule::command('catalog:enforce-price-integrity-safety --apply')
    ->hourlyAt(45)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/catalog-price-safety.log'));

// Сначала возвращаем проверенные факты производителей, затем приводим их к
// единому фильтру площади. Объём переводится в ориентировочную площадь только
// по утверждённой высоте потолка 2,5 м; мощность для этого не используется.
Schedule::command('catalog:apply-official-stove-heating-areas --apply')
    ->dailyAt('09:04')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stove-official-heating-areas.log'));

Schedule::command('catalog:apply-official-stove-heating-volumes --apply')
    ->dailyAt('09:05')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stove-official-heating-volumes.log'));

Schedule::command('catalog:normalize-stove-heating-areas --apply --sample=0')
    ->dailyAt('09:06')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stove-heating-area-normalization.log'));

Schedule::command('catalog:normalize-stove-materials --apply --sample=0')
    ->dailyAt('09:10')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stove-material-normalization.log'));

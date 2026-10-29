<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('integration:scan-issues')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground();

// Deal.by orders first land in an encrypted, idempotent inbox and are then
// projected into the regular manager workflow. Capture stays inert until
// explicitly enabled.
Schedule::command('deal:pull-orders')
    ->everyTwoMinutes()
    ->when(fn (): bool => (bool) config('services.deal_by.orders_capture_enabled'))
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/deal-by-orders.log'));

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

// New supplier cards inherit the same litre filter automatically. The command
// only links an explicit tank volume and ignores membranes, brackets and kits.
Schedule::command('catalog:sync-membrane-tank-volume-filter --apply')
    ->dailyAt('04:10')
    ->timezone('Europe/Minsk')
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/catalog-filter-sync.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$supplierSyncTimezone = 'Europe/Minsk';

// Omenergo publishes a live Google Sheet. Refresh it every hour so the
// qualitative stock markers cannot become stale between daily runs. This
// maintenance never creates products or rewrites content/media.
Schedule::command('supplier:sync-omenergo --apply')
    ->hourlyAt(5)
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

// ООО «СаунаОпт»: двери для бани Cooper с cooper.by — розничная цена и наличие.
// Новые карточки создаются только ручным запуском с --create-new.
// Чистый берег: the morning xls is uploaded on the supplier page; the sync follows within 10 minutes.
Schedule::command('supplier:sync-chisty-bereg --apply --if-new')
    ->everyTenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chisty-bereg-sync.log'));

// New heating offers of Чистый берег become cards (bereg.by photos and specs), a batch at a time.
Schedule::command('supplier:chisty-bereg-create --limit=120 --apply')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chisty-bereg-create.log'));

// Cards made before the specs fix get the bereg.by specs; each card is tried once, so the run soon has nothing to do.
Schedule::command('supplier:chisty-bereg-create --refill-specs --limit=150 --apply')
    ->everyFifteenMinutes()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chisty-bereg-refill-specs.log'));

// Cards whose Чистый берег РРЦ equals опт get the market retail price (web research, a few cards at a time).
Schedule::command('supplier:chisty-bereg-market-prices --limit=10 --apply')
    ->everyThirtyMinutes()
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/chisty-bereg-market.log'));

Schedule::command('supplier:sync-cooper-doors --apply')
    ->dailyAt('07:50')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/saunaopt-cooper-doors-sync.log'));

// Термокрафт: live-прайс UNIVAT и ТЭНов TENKO/СТЭН. Новые карточки
// создаются только в первом проверенном ручном запуске; cron обновляет связи.
Schedule::command('supplier:sync-thermokraft --apply --all-sheets --sync-retail-prices')
    ->hourlyAt(15)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/thermokraft-price-stock-sync.log'));

// ТД «АТМОС» (price.teplota.by): опт, РРЦ и «на складе»/«под заказ» для
// привязанных карточек. Новые карточки — только catalog:publish-atmos-cards.
Schedule::command('supplier:sync-atmos --apply --sync-retail-prices')
    ->hourlyAt(25)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/atmos-price-stock-sync.log'));

// ООО «Теплошоп» (BIOTEP): the newest Prajs-Teploshop xlsx on biodom.by,
// uploaded every few months. Retail follows the RRP for BIOTEP only (sole supplier).
Schedule::command('supplier:sync-teploshop --apply --sync-retail-prices --retail-family=biotep')
    ->dailyAt('07:40')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/teploshop-price-sync.log'));

// TENKO: enrich only manually verified exact modifications from the official
// manufacturer catalogue. Small batches avoid accidental broad replacements.
Schedule::command('supplier:enrich-thermokraft-official --apply --limit=3')
    ->dailyAt('08:35')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/thermokraft-official-enrichment.log'));

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

// Product pages from the supplier catalogue contain richer photos and
// specifications than the compact price export. Refresh one brand per night
// so the catalogue grows without one long network-heavy job.
foreach ([
    1 => 'De Dietrich',
    2 => 'Shinhoo',
    3 => 'SFA',
    4 => 'Джилекс',
    5 => 'Watrix',
] as $weekday => $brand) {
    Schedule::command('supplier:enrich-tm-tmarket', ['--apply' => true, '--brand' => $brand])
        ->weeklyOn($weekday, '03:20')
        ->timezone($supplierSyncTimezone)
        ->withoutOverlapping(240)
        ->runInBackground()
        ->appendOutputTo(storage_path('logs/tm-management-enrichment.log'));
}

Schedule::command('supplier:enrich-tm-workbook', ['--apply' => true])
    ->weeklyOn(7, '02:40')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(240)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/tm-management-workbook.log'));

// S-TANK: daily refresh after the NBRB rate and the main supplier chain.
// Price-list values are maintained in EUR; the command recalculates the BYN retail price.
Schedule::command('supplier:sync-stank')
    ->dailyAt('08:50')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/stank-sync.log'));

// VENMA pellet burners from Dom kotlov: storefront retail price stays equal
// to the public source price; procurement is stored separately with 15% discount.
Schedule::command('supplier:sync-domkotlov-venma --apply')
    ->dailyAt('09:00')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping(120)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/domkotlov-venma-sync.log'));

// Акции поставщиков со сроком: после даты окончания возвращаем обычную цену.
// Supplier cards hidden for lack of a photo come back once one is found.
Schedule::command('catalog:hide-cards-awaiting-photos --publish --apply')
    ->dailyAt('06:20')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/awaiting-photos.log'));

// Cards a manager created from a supplier offer in a КП: AI description.
Schedule::command('catalog:finish-offer-cards')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/offer-cards.log'));

Schedule::command('catalog:expire-sale-campaigns --apply')
    ->hourlyAt(5)
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/catalog-sale-campaigns.log'));

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

// Новые импорты иногда добавляют числовые характеристики, но не создают
// привязку к select-диапазонам каталога. Восстанавливаем только однозначные
// значения мощности/площади; площадь из мощности не вычисляем.
Schedule::command('catalog:sync-boiler-range-filters --apply --sample=0')
    ->dailyAt('09:15')
    ->timezone($supplierSyncTimezone)
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/boiler-range-filters.log'));

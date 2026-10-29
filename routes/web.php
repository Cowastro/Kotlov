<?php

use App\Http\Controllers\A1CallRecordingController;
use App\Http\Controllers\A1TelephonyWebhookController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountOrderController;
use App\Http\Controllers\AdminChimneyCalculationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\B2bMarketplaceController;
use App\Http\Controllers\B2bPriceRequestController;
use App\Http\Controllers\B2bCabinetController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogIndexController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ChimneyCalculationRequestController;
use App\Http\Controllers\ChimneySelectorController;
use App\Http\Controllers\CommercialDocumentPdfController;
use App\Http\Controllers\CommercialDocumentPortalController;
use App\Http\Controllers\CommercialContractFileController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ContactRequestController;
use App\Http\Controllers\CrmMailAttachmentController;
use App\Http\Controllers\CrmMailAttachmentPreviewController;
use App\Http\Controllers\CrmMailPdfTextController;
use App\Http\Controllers\FireplaceInstallationController;
use App\Http\Controllers\HeatPumpInstallationController;
use App\Http\Controllers\HeatPumpLandingController;
use App\Http\Controllers\InstallerAccountController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\InstallRequestController;
use App\Http\Controllers\MarketResearchReportPdfController;
use App\Http\Controllers\OneCExchangeController;
use App\Http\Controllers\OrderProcurementController;
use App\Http\Controllers\OrderPortalController;
use App\Http\Controllers\PartnerApplicationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReleaseNotesController;
use App\Http\Controllers\ProductPurchasePriceSourceAttachmentController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicAiSelectionController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SupportChatController;
use App\Http\Controllers\TaxpayerLookupController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\TisStandard30PriceListController;
use App\Http\Controllers\TisFerrit1mmPriceListController;
use App\Http\Controllers\TisStandard50PriceListController;
use App\Http\Controllers\WebpayPaymentController;
use App\Http\Controllers\WishlistController;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ContactRequest;
use App\Models\EmailSubscriber;
use App\Models\Product;
use App\Rules\NoHtmlOrLinks;
use App\Rules\PhoneNotSpam;
use App\Support\Catalog\BelomoGasMeterDesignations;
use App\Support\Catalog\BelomoSelectorLineup;
use App\Support\Catalog\GasMeterSelectionGuide;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::match(['get', 'post'], '/1c/exchange', OneCExchangeController::class)
    ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
    ->name('onec.exchange');

Route::post('/ai-selection/start', [PublicAiSelectionController::class, 'start'])
    ->middleware('throttle:8,1')
    ->name('ai-selection.start');
Route::get('/ai-selection/categories', [PublicAiSelectionController::class, 'categories'])
    ->middleware('throttle:30,1')
    ->name('ai-selection.categories');
Route::post('/ai-selection/recommend', [PublicAiSelectionController::class, 'recommend'])
    ->middleware('throttle:5,1')
    ->name('ai-selection.recommend');
Route::post('/ai-selection/event', [PublicAiSelectionController::class, 'event'])
    ->middleware('throttle:20,1')
    ->name('ai-selection.event');
Route::post('/ai-selection/contact', [PublicAiSelectionController::class, 'contact'])
    ->middleware('throttle:4,1')
    ->name('ai-selection.contact');

Route::match(['get', 'post'], '/1c/exchange/{source}', OneCExchangeController::class)
    ->where('source', '[a-z0-9][a-z0-9_-]{1,99}')
    ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
    ->name('onec.exchange.source');

Route::post('/webpay/notify', [WebpayPaymentController::class, 'notify'])
    ->middleware('throttle:60,1')
    ->name('webpay.notify');
Route::get('/webpay/return', [WebpayPaymentController::class, 'completed'])->name('webpay.return');
Route::get('/webpay/cancel', [WebpayPaymentController::class, 'cancelled'])->name('webpay.cancel');

Route::get('/admin/commercial-documents/{commercialDocument}/pdf', CommercialDocumentPdfController::class)
    ->middleware('auth')
    ->name('admin.commercial-documents.pdf');

Route::get('/admin/commercial-contracts/{commercialContract}/pdf', [CommercialContractFileController::class, 'pdf'])
    ->whereNumber('commercialContract')
    ->middleware('auth')
    ->name('admin.commercial-contracts.pdf');
Route::get('/admin/commercial-contracts/{commercialContract}/docx', [CommercialContractFileController::class, 'docx'])
    ->whereNumber('commercialContract')
    ->middleware('auth')
    ->name('admin.commercial-contracts.docx');
Route::get('/admin/commercial-contracts/{commercialContract}/source', [CommercialContractFileController::class, 'source'])
    ->whereNumber('commercialContract')
    ->middleware('auth')
    ->name('admin.commercial-contracts.source');

Route::get('/admin/orders/{order}/procurement', OrderProcurementController::class)
    ->whereNumber('order')
    ->middleware('auth')
    ->name('admin.orders.procurement');

// Внутренние релизы: список для сотрудников, релиз — по ссылке на 48 часов без входа.
Route::get('/admin/releases', [ReleaseNotesController::class, 'index'])
    ->name('admin.releases.index');
Route::get('/releases/{date}', [ReleaseNotesController::class, 'show'])
    ->where('date', '\d{4}-\d{2}-\d{2}')
    ->name('releases.show');

Route::get('/admin/orders/{order}/online-preview', [OrderPortalController::class, 'preview'])
    ->whereNumber('order')
    ->middleware('auth')
    ->name('admin.orders.online-preview');

Route::prefix('order-view')->group(function (): void {
    Route::get('/access/{token}/print', [OrderPortalController::class, 'print'])
        ->where('token', '[A-Za-z0-9]{80}')
        ->name('order-portal.print');
    Route::get('/access/{token}', [OrderPortalController::class, 'access'])
        ->where('token', '[A-Za-z0-9]{80}')
        ->name('order-portal.access');
    Route::post('/access/{token}/action', [OrderPortalController::class, 'action'])
        ->where('token', '[A-Za-z0-9]{80}')
        ->middleware('throttle:20,1')
        ->name('order-portal.action');
});

Route::get('/admin/purchase-price-sources/{source}/attachment', ProductPurchasePriceSourceAttachmentController::class)
    ->whereNumber('source')
    ->middleware('auth')
    ->name('admin.purchase-price-sources.attachment');

Route::get('/admin/market-research-reports/{marketResearchReport}/pdf', MarketResearchReportPdfController::class)
    ->middleware('auth')
    ->name('admin.market-research-reports.pdf');

Route::get('/admin/commercial-documents/{commercialDocument}/online-preview', [CommercialDocumentPortalController::class, 'preview'])
    ->middleware('auth')
    ->name('admin.commercial-documents.online-preview');

Route::prefix('offer')->group(function (): void {
    Route::get('/access/{token}', [CommercialDocumentPortalController::class, 'access'])
        ->where('token', '[A-Za-z0-9]{80}')
        ->name('commercial-portal.access');
    Route::post('/access/{token}', [CommercialDocumentPortalController::class, 'activate'])
        ->where('token', '[A-Za-z0-9]{80}')
        ->middleware('throttle:10,1')
        ->name('commercial-portal.activate');
    Route::get('/verify/{challenge:public_id}', [CommercialDocumentPortalController::class, 'verifyForm'])
        ->name('commercial-portal.verify-form');
    Route::post('/verify/{challenge:public_id}', [CommercialDocumentPortalController::class, 'verify'])
        ->middleware('throttle:10,10')
        ->name('commercial-portal.verify');
    Route::get('/{link:public_id}', [CommercialDocumentPortalController::class, 'show'])
        ->name('commercial-portal.show');
    Route::get('/{link:public_id}/pdf', [CommercialDocumentPortalController::class, 'pdf'])
        ->name('commercial-portal.pdf');
    Route::post('/{link:public_id}/accept', [CommercialDocumentPortalController::class, 'accept'])
        ->middleware('throttle:10,1')
        ->name('commercial-portal.accept');
    Route::post('/{link:public_id}/revision', [CommercialDocumentPortalController::class, 'requestRevision'])
        ->middleware('throttle:10,1')
        ->name('commercial-portal.revision');
    Route::post('/{link:public_id}/reject', [CommercialDocumentPortalController::class, 'reject'])
        ->middleware('throttle:10,1')
        ->name('commercial-portal.reject');
});

Route::get('/admin/crm-mail-messages/{message}/attachments/{attachment}', CrmMailAttachmentController::class)
    ->whereNumber('attachment')
    ->middleware('auth')
    ->name('admin.crm-mail-messages.attachments.download');

Route::get('/admin/crm-mail-messages/{message}/attachments/{attachment}/preview', CrmMailAttachmentPreviewController::class)
    ->whereNumber('attachment')
    ->middleware('auth')
    ->name('admin.crm-mail-messages.attachments.preview');

Route::get('/admin/crm-mail-messages/{message}/attachments/{attachment}/read-pdf', CrmMailPdfTextController::class)
    ->whereNumber('attachment')
    ->middleware('auth')
    ->name('admin.crm-mail-messages.attachments.read-pdf');

Route::post('/admin/crm-mail-messages/{message}/attachments/{attachment}/recognize-pdf', [CrmMailPdfTextController::class, 'recognizeImages'])
    ->whereNumber('attachment')
    ->middleware('auth')
    ->name('admin.crm-mail-messages.attachments.recognize-pdf');

Route::get('/robots.txt', function (Request $request) {
    if ($request->getHost() === 'admin.'.config('app.base_domain', 'kotlov.by')) {
        return response("User-agent: *\nDisallow: /\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    return response(file_get_contents(public_path('robots.txt')), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
});

// ===== Главная =====
Route::get('/', function () {
    // Все данные блоков главной — тяжёлые (~50 запросов), кешируем на 10 минут.
    // Изменения товаров/баннеров в админке появятся на главной с задержкой до 10 мин.
    $data = Cache::remember('home:data:v6', 600, function () {
        $categoryBranchIds = function ($categoryId) {
            $categories = Category::where('is_active', true)
                ->get(['id', 'parent_id'])
                ->groupBy('parent_id');

            $ids = collect([$categoryId]);
            $queue = [$categoryId];

            while ($queue) {
                $parentId = array_shift($queue);

                foreach ($categories->get($parentId, collect()) as $child) {
                    $ids->push($child->id);
                    $queue[] = $child->id;
                }
            }

            return $ids->unique()->values();
        };

        $sellableProducts = fn () => Product::query()->orderable();

        $popularCategories = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', [
                'kotly', 'teplovyie-nasosyi', 'kaminy', 'pechki',
                'dymohody', 'bani-i-sauny', 'vodonagrevateli',
                'pelletnye-gorelki', 'komplektuyushhie-dlya-otopleniya', 'vodosnabzhenie',
            ])
            ->orderBy('sort_order')
            ->limit(10)
            ->get()
            ->each(function ($category) use ($categoryBranchIds, $sellableProducts) {
                $category->products_count = $sellableProducts()
                    ->whereIn('category_id', $categoryBranchIds($category->id))
                    ->count();
            });

        $kotlyCatId = Category::where('slug', 'kotly')->value('id');
        $kotlyIds = $kotlyCatId ? $categoryBranchIds($kotlyCatId) : collect();

        $nasosCatId = Category::where('slug', 'teplovyie-nasosyi')->value('id');
        $nasosIds = $nasosCatId ? $categoryBranchIds($nasosCatId) : collect();

        $kaminCatId = Category::where('slug', 'kaminy')->value('id');
        $kaminIds = $kaminCatId ? $categoryBranchIds($kaminCatId) : collect();

        // Главная розничная витрина ЭкоКамин: сначала модели АЛЬФА, затем
        // другие доступные топки бренда. Служебные данные поставщиков здесь
        // намеренно не показываются, а выбранные товары исключаются из хитов.
        $ecokaminBrandId = Brand::query()
            ->where('slug', 'ecokamin')
            ->orWhere('name', 'ЭкоКамин')
            ->value('id');
        $popularOrderProducts = collect();

        // Акция ЭкоКамин: акционные товары первыми в витрине бренда; хиты серий
        // — для слайда наверху и баннеров внизу главной.
        $ecokaminPromo = \App\Support\Promotions\EcokaminPromotion::showcase();
        $ecokaminPromoAll = $ecokaminPromo['all'];
        $ecokaminPromoHits = $ecokaminPromo['hits'];
        $ecokaminPromoMaxDiscount = $ecokaminPromo['maxDiscount'];
        $ecokaminPromoFrom = $ecokaminPromo['from'];
        foreach ($ecokaminPromoHits->concat($ecokaminPromoAll)->unique('id')->take(8) as $candidate) {
            $popularOrderProducts->put($candidate->id, $candidate);
        }

        if ($ecokaminBrandId && $popularOrderProducts->count() < 8) {
            $alphaProducts = $sellableProducts()
                ->withSupplierChannels()
                ->with(['category', 'brand'])
                ->where('brand_id', $ecokaminBrandId)
                ->whereIn('category_id', $kaminIds)
                ->where(function ($query) {
                    $query->where('name', 'like', '%АЛЬФА%')
                        ->orWhere('name', 'like', '%Альфа%')
                        ->orWhere('name', 'like', '%ALPHA%')
                        ->orWhere('name', 'like', '%Alpha%');
                })
                ->homepageRatingOrder(true)
                ->limit(24)
                ->get();

            foreach ($alphaProducts as $candidate) {
                if ($popularOrderProducts->count() >= 8) {
                    break;
                }

                if ($candidate->canBeAddedToCart()) {
                    $popularOrderProducts->put($candidate->id, $candidate);
                }

                if ($popularOrderProducts->count() >= 8) {
                    break;
                }
            }

            if ($popularOrderProducts->count() < 8) {
                $moreEcokaminProducts = $sellableProducts()
                    ->withSupplierChannels()
                    ->with(['category', 'brand'])
                    ->where('brand_id', $ecokaminBrandId)
                    ->whereIn('category_id', $kaminIds)
                    ->whereNotIn('id', $popularOrderProducts->keys())
                    ->homepageRatingOrder(true)
                    ->limit(40)
                    ->get();

                foreach ($moreEcokaminProducts as $candidate) {
                    if ($candidate->canBeAddedToCart()) {
                        $popularOrderProducts->put($candidate->id, $candidate);
                    }

                    if ($popularOrderProducts->count() >= 8) {
                        break;
                    }
                }
            }
        }

        $popularOrderProducts = $popularOrderProducts->take(8)->values();
        $featuredEcokaminIds = $popularOrderProducts->pluck('id');

        $productsKotly = $sellableProducts()
            ->withSupplierChannels()
            ->whereIn('category_id', $kotlyIds)
            ->whereNotIn('id', $featuredEcokaminIds)
            ->with(['category', 'brand'])
            ->homepageRatingOrder(true)
            ->limit(8)->get();

        $productsNasosy = $sellableProducts()
            ->withSupplierChannels()
            ->whereIn('category_id', $nasosIds)
            ->whereNotIn('id', $featuredEcokaminIds)
            ->with(['category', 'brand'])
            ->homepageRatingOrder(true)
            ->limit(8)->get();

        $productsKaminy = $ecokaminBrandId
            ? $sellableProducts()
                ->withSupplierChannels()
                ->where('brand_id', $ecokaminBrandId)
                ->whereIn('category_id', $kaminIds)
                ->whereNotIn('id', $featuredEcokaminIds)
                ->with(['category', 'brand'])
                ->homepageRatingOrder(true)
                ->limit(8)->get()
            : collect();

        if ($productsKaminy->count() < 8) {
            $productsKaminy = $productsKaminy->concat(
                $sellableProducts()
                    ->withSupplierChannels()
                    ->whereIn('category_id', $kaminIds)
                    ->whereNotIn('id', $featuredEcokaminIds->merge($productsKaminy->pluck('id')))
                    ->with(['category', 'brand'])
                    ->homepageRatingOrder(true)
                    ->limit(8 - $productsKaminy->count())
                    ->get()
            )->values();
        }

        $productsAkcii = $sellableProducts()
            ->withSupplierChannels()
            ->where('is_sale', true)
            ->whereNotIn('id', $featuredEcokaminIds)
            ->with(['category', 'brand'])
            ->homepageRatingOrder()
            ->limit(8)->get();

        $bannersHero = Banner::active()->byPosition('hero')->get();
        $bannerPromoKotly = Banner::active()->byPosition('promo_kotly')->first();
        $bannerPromoNasosy = Banner::active()->byPosition('promo_nasosy')->first();
        $bannerPromoKaminy = Banner::active()->byPosition('promo_kaminy')->first();
        $bannerPromoAkcii = Banner::active()->byPosition('promo_akcii')->first();
        $bannerPartners = Banner::active()->byPosition('partners')->first();
        $ecokaminSaleCount = Product::query()->orderable()
            ->inActiveSaleCampaign(\App\Console\Commands\ApplyEcokaminOctoberSaleCommand::CAMPAIGN)
            ->count();

        $homeBlogPosts = BlogPost::published()
            ->with('category:id,name,slug')
            ->orderByDesc('published_at')
            ->limit(6)
            ->get();

        return compact(
            'popularCategories',
            'popularOrderProducts',
            'productsKotly', 'productsNasosy',
            'productsKaminy', 'productsAkcii',
            'bannersHero',
            'bannerPromoKotly', 'bannerPromoNasosy',
            'bannerPromoKaminy', 'bannerPromoAkcii',
            'bannerPartners', 'homeBlogPosts', 'ecokaminSaleCount',
            'ecokaminPromoHits', 'ecokaminPromoMaxDiscount', 'ecokaminPromoFrom'
        );
    });

    return view('pages.home-new', $data);
});

// ===== Статичные страницы — ДО динамических! =====
Route::view('/about', 'pages.about');
Route::get('/akcii', [PromotionController::class, 'index'])->name('promotions.index');
Route::get('/akcii/ecokamin-skidki', [PromotionController::class, 'ecokamin'])->name('promotions.ecokamin');
Route::get('/akcii/kotlov-xo-ceramic-pro', [PromotionController::class, 'xoCeramicPro'])->name('promotions.xo-ceramic-pro');
Route::get('/akcii/kotlov-xo-evo-26', [PromotionController::class, 'xoEvo26'])->name('promotions.xo-evo-26');
Route::get('/akcii/hotta-ceramik-20-30', [PromotionController::class, 'hottaCeramik'])->name('promotions.hotta-ceramik');
Route::view('/dostavka', 'pages.dostavka');
Route::get('/partners', fn () => view('pages.partners'))->name('partners');
Route::get('/rekvizity', fn () => view('pages.requisites', [
    'company' => config('legal.commercial_documents'),
]))->name('company.requisites');
Route::get(KnowledgeBase::URL, fn (Request $request) => view('pages.knowledge-base', [
    'items' => KnowledgeBase::filter($request->query('category'), $request->query('tag'), $request->query('q')),
    'all' => KnowledgeBase::items(),
    'categories' => KnowledgeBase::categories(),
    'tags' => KnowledgeBase::tags(),
]))->name('knowledge-base');
Route::get(GasMeterSelectionGuide::PAGE_URL, function () {
    $products = Product::query()
        ->active()
        ->notArchived()
        ->whereHas('category', fn ($query) => $query->where('slug', 'schetchiki-gaza'))
        ->whereHas('brand', fn ($query) => $query->where('slug', 'belomo'))
        ->withSupplierChannels()
        ->with(['category', 'brand'])
        ->get();

    $selectorModels = collect(BelomoSelectorLineup::models());
    $stockModels = $selectorModels->map(function (array $model) use ($products): array {
        $product = $products->first(fn (Product $candidate) => BelomoSelectorLineup::matchesProduct($candidate, $model));
        if (! $product) {
            return $model;
        }

        return BelomoSelectorLineup::attachProduct($model, $product);
    });

    return view('pages.knowledge-base-gas-meter-selection', [
        'sizes' => BelomoSelectorLineup::sizes(),
        'families' => BelomoSelectorLineup::families($selectorModels->all()),
        'models' => $stockModels->values()->all(),
    ]);
})->name('knowledge-base.gas-meter-selection');
Route::permanentRedirect(BelomoGasMeterDesignations::LEGACY_PAGE_URL, BelomoGasMeterDesignations::PAGE_URL);
Route::permanentRedirect(BelomoGasMeterDesignations::LEGACY_PAGE_URL . '.csv', BelomoGasMeterDesignations::CSV_URL);
Route::get(BelomoGasMeterDesignations::PAGE_URL, fn () => view('pages.knowledge-base-belomo-designations', [
    'models' => BelomoGasMeterDesignations::models(),
    'variants' => BelomoGasMeterDesignations::variants(),
]))->name('knowledge-base.belomo-designations');
Route::get(BelomoGasMeterDesignations::CSV_URL, function () {
    return response()->streamDownload(function () {
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Модель', 'Обозначение', 'Типоразмер', 'Доп. устройство', 'Резьба', 'Связь', 'Адаптер', 'Наименование при заказе'], ';');
        foreach (BelomoGasMeterDesignations::variants() as $row) {
            fputcsv($out, [
                BelomoGasMeterDesignations::models()[$row['model']]['name'],
                $row['code'],
                $row['size'],
                $row['option'] ?? '',
                $row['thread'] ? $row['thread'] . ' (' . BelomoGasMeterDesignations::THREADS[$row['thread']] . ')' : '',
                $row['network'] ?? '',
                $row['adapter'] === 'pc' ? 'ПК' : ($row['adapter'] === 'modbus' ? 'Modbus' : ''),
                $row['name'],
            ], ';');
        }
        fclose($out);
    }, 'belomo-kata-skat-oboznacheniya.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
})->name('knowledge-base.belomo-designations.csv');
Route::get('/dymohody/tis-standart-50', TisStandard50PriceListController::class)
    ->name('chimneys.tis-standard-50');
Route::get('/dymohody/tis-standart-50.csv', [TisStandard50PriceListController::class, 'csv'])
    ->name('chimneys.tis-standard-50.csv');
Route::get('/dymohody/tis-standart-30', TisStandard30PriceListController::class)
    ->name('chimneys.tis-standard-30');
Route::get('/dymohody/tis-standart-30.csv', [TisStandard30PriceListController::class, 'csv'])
    ->name('chimneys.tis-standard-30.csv');
Route::get('/dymohody/tis-ferrit-1-mm', TisFerrit1mmPriceListController::class)
    ->name('chimneys.tis-ferrit-1mm');
Route::get('/dymohody/tis-ferrit-1-mm.csv', [TisFerrit1mmPriceListController::class, 'csv'])
    ->name('chimneys.tis-ferrit-1mm.csv');
Route::post('/partners/apply-installer', [PartnerApplicationController::class, 'storeInstaller'])->middleware('public.form.protect:installer')->name('partners.apply-installer');
Route::get('/suppliers', fn () => view('pages.suppliers'))->name('suppliers');
Route::post('/suppliers/apply', [PartnerApplicationController::class, 'storeSupplier'])->middleware('public.form.protect:supplier')->name('suppliers.apply');
Route::get('/become-installer', fn () => view('pages.become-installer'))->name('become-installer');
Route::get('/montazhnikam', fn () => redirect('/become-installer?ref=messenger#apply'))
    ->name('installers.outreach');
Route::view('/demo-installer-profile', 'pages.demo-installer-profile')->name('demo-installer-profile');
Route::get('/installers', [InstallerController::class, 'index'])->name('installers.index');
Route::get('/montazh-teplovyh-nasosov', HeatPumpInstallationController::class)->name('heat-pumps.installation');
Route::get('/{landing}', HeatPumpLandingController::class)
    ->whereIn('landing', [
        'teplovye-nasosy-r290',
        'teplovye-nasosy-dlya-radiatorov',
        'teplovye-nasosy-dlya-teplogo-pola',
        'teplovye-nasosy-dlya-doma',
    ])
    ->name('heat-pumps.landing');
Route::get('/montazh-kaminov', FireplaceInstallationController::class)->name('fireplaces.installation');
Route::get('/installers/{slug}', [InstallerController::class, 'show'])->name('installers.show');
Route::get('/install-request', [InstallRequestController::class, 'create'])->name('install-requests.create');
Route::post('/install-request', [InstallRequestController::class, 'store'])->middleware('public.form.protect:install-request')->name('install-requests.store');
Route::view('/reviews', 'pages.reviews');
Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->middleware('public.form.protect:reviews')->name('reviews.store');
Route::view('/rassrochka-do-6-mesyatsev', 'pages.installment')->name('installment.info');
Route::view('/faq', 'pages.faq');
Route::view('/privacy', 'pages.privacy');
// ===== Корзина =====
Route::get('/cart', [CartController::class, 'index'])->name('cart');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update', [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/data', [CartController::class, 'data'])->name('cart.data');
Route::post('/cart/note', [CartController::class, 'saveNote'])->name('cart.note');
Route::post('/cart/delivery', [CartController::class, 'saveDelivery'])->name('cart.delivery');
Route::post('/cart/coupon', [CartController::class, 'saveCoupon'])->name('cart.coupon');

// ===== Checkout =====
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store')->middleware('public.form.protect:checkout');
Route::post('/checkout/company-lookup', TaxpayerLookupController::class)
    ->middleware('throttle:20,1')
    ->name('checkout.company-lookup');
Route::get('/checkout/success/{number}', [CheckoutController::class, 'success'])->name('checkout.success');

// ===== Блог =====
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// ===== Контакты =====
Route::get('/contacts', fn () => view('pages.contacts'))->name('contacts');
Route::post('/contacts', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:100', new NoHtmlOrLinks],
        'phone' => ['required', 'string', 'max:30', new PhoneNotSpam],
        'email' => 'nullable|email|max:150',
        'message' => ['required', 'string', 'max:1000', new NoHtmlOrLinks],
    ]);
    ContactRequest::create($data);

    return back()->with('success', 'Сообщение отправлено! Мы свяжемся с вами в течение рабочего дня.');
})->middleware('public.form.protect:contacts')->name('contact.store');

// ===== Сравнение =====
Route::get('/compare', [CompareController::class, 'index'])->name('compare');
Route::post('/compare/add', [CompareController::class, 'add'])->name('compare.add');
Route::post('/compare/remove', [CompareController::class, 'remove'])->name('compare.remove');
Route::post('/compare/clear', [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/clear-ajax', [CompareController::class, 'clearAjax'])->name('compare.clear-ajax');
Route::get('/compare/items', [CompareController::class, 'items'])->name('compare.items');

// ===== Избранное =====
Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/add', [WishlistController::class, 'add'])->name('wishlist.add');
Route::post('/wishlist/remove', [WishlistController::class, 'remove'])->name('wishlist.remove');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

// ===== Поиск =====
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

// ===== Редиректы старых URL =====
Route::get('/pechi-dlya-bani/pechi-drovyanye', fn () => redirect('/drovyanye-pechi-dlya-bani', 301));
Route::get('/pechi-dlya-bani/{any}', fn () => redirect('/bani-i-sauny', 301))->where('any', '.*');
Route::get('/brands/{brand}/{category}', fn () => redirect('/brands', 301))->where('brand', '[a-z0-9\-]+')->where('category', '[a-z0-9\-]+');
Route::get('/aktsiiiskidki', fn () => redirect('/akcii', 301));

// Категория "Отопление" расформирована — редиректим на Комплектующие
Route::get('/otoplenie', fn () => redirect('/komplektuyushhie-dlya-otopleniya', 301));
Route::get('/otoplenie/{any}', fn () => redirect('/komplektuyushhie-dlya-otopleniya', 301))->where('any', '.*');

// ===== Каталог =====
Route::get('/catalog', [CatalogIndexController::class, 'index'])->name('catalog');
Route::get('/podbor-dymohoda', ChimneySelectorController::class)->name('chimney-selector');
Route::post('/chimney-calculation-requests', [ChimneyCalculationRequestController::class, 'store'])
    ->middleware('throttle:4,1')
    ->name('chimney-calculation-requests.store');
Route::post('/admin/chimney-calculator/save', [AdminChimneyCalculationController::class, 'store'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('admin.chimney-calculator.save');

// ===== Бренды =====
Route::get('/brands', [BrandController::class, 'index'])->name('brands');
Route::get('/brands/{slug}', [BrandController::class, 'show'])->name('brand.show');

// ===== Telegram Webhook =====
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// ===== ВАТС A1 =====
Route::get('/admin/telephony-calls/{telephonyCall}/recording', A1CallRecordingController::class)
    ->middleware('auth')
    ->name('telephony.a1.recording');

// Секрет находится в пути, поскольку Webhook A1 не подписывает запросы.
Route::match(['get', 'post'], '/integrations/a1/telephony/{secret}', A1TelephonyWebhookController::class)
    ->where('secret', '[A-Za-z0-9_-]{24,128}')
    ->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
    ->name('telephony.a1.webhook');

// sitemap.xml — вынесен в routes/sitemap.php без session middleware

// ===== SVG-иконки каталога =====
Route::get('/icons/{file}.svg', function (string $file) {
    if (! preg_match('/^[a-z0-9_-]+$/i', $file)) {
        abort(404);
    }

    $path = public_path("icons/{$file}.svg");

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/svg+xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
    PreventRequestForgery::class,
]);

Route::get('/catalog-icons/{file}', function (string $file) {
    if (! preg_match('/^[a-z0-9_-]+$/i', $file)) {
        abort(404);
    }

    $path = public_path("icons/{$file}.svg");

    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'image/svg+xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=604800',
    ]);
})->name('catalog.icon')->withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
    PreventRequestForgery::class,
]);

// ===== Прочие формы =====
Route::post('/ask', [ContactRequestController::class, 'store'])
    ->middleware('public.form.protect:ask')
    ->name('ask.store');

Route::prefix('support-chat')->name('support-chat.')->group(function (): void {
    Route::get('/', [SupportChatController::class, 'show'])->name('show');
    Route::post('/start', [SupportChatController::class, 'start'])->middleware('throttle:6,1')->name('start');
    Route::post('/messages', [SupportChatController::class, 'send'])->middleware('throttle:30,1')->name('send');
});

// ===== Подписка на рассылку =====
Route::post('/subscribe', function (Request $request) {
    $request->validate(['email' => 'required|email|max:150']);
    EmailSubscriber::firstOrCreate(
        ['email' => $request->email],
        ['is_active' => true, 'confirmed_at' => now()]
    );

    return back()->with('subscribe_success', 'Вы подписались на рассылку KOTLOV.BY!');
})->name('subscribe');

// ===== Auth страницы (GET) =====
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect('/account');
    }

    return view('pages.login');
})->name('login');

Route::get('/forgot-password', function () {
    if (auth()->check()) {
        return redirect('/account');
    }

    return view('pages.forgot-password');
})->name('password.request');

// ===== Auth действия (POST) =====
Route::post('/register', [AuthController::class, 'register'])->name('register')->middleware('throttle:5,10');
Route::post('/registration/phone/send-code', [\App\Http\Controllers\PhoneVerificationController::class, 'store'])
    ->name('registration.phone.send-code')
    ->middleware('throttle:10,10');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

// ===== Личный кабинет =====
Route::middleware('auth')->group(function () {
    Route::get('/b2b/cabinet', [B2bCabinetController::class, 'show'])->name('b2b.cabinet');
    Route::put('/b2b/cabinet/profile', [B2bCabinetController::class, 'updateProfile'])->name('b2b.cabinet.profile');
    Route::post('/b2b/cabinet/carts', [B2bCabinetController::class, 'saveCart'])->name('b2b.cabinet.carts.store');
    Route::post('/b2b/cabinet/carts/{savedCart}/restore', [B2bCabinetController::class, 'restoreCart'])->name('b2b.cabinet.carts.restore');
    Route::delete('/b2b/cabinet/carts/{savedCart}', [B2bCabinetController::class, 'deleteCart'])->name('b2b.cabinet.carts.destroy');
    Route::get('/b2b/cabinet/contracts/{contract}/{format}', [B2bCabinetController::class, 'contractFile'])
        ->where('format', 'pdf|docx')
        ->name('b2b.cabinet.contracts.file');
    Route::get('/b2b/catalog', B2bMarketplaceController::class)->name('b2b.catalog');
    Route::get('/b2b/catalog/suggest', [B2bMarketplaceController::class, 'suggest'])
        ->middleware('throttle:120,1')
        ->name('b2b.catalog.suggest');
    Route::post('/b2b/price-requests', B2bPriceRequestController::class)
        ->middleware('throttle:20,10')
        ->name('b2b.price-requests.store');
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/account/orders/{order}', [AccountOrderController::class, 'show'])->name('account.orders.show');
    Route::post('/account/orders/{order}/webpay', [WebpayPaymentController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('account.orders.webpay.start');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/b2b-request', [AccountController::class, 'b2bRequest'])->name('account.b2b-request');

    Route::get('/account/installer-profile', [InstallerAccountController::class, 'edit'])
        ->name('account.installer-profile');
    Route::put('/account/installer-profile', [InstallerAccountController::class, 'update'])
        ->name('account.installer-profile.update');
    Route::post('/account/installer-works', [InstallerAccountController::class, 'storeWork'])
        ->name('account.installer-works.store');
    Route::put('/account/installer-works/{work}', [InstallerAccountController::class, 'updateWork'])
        ->name('account.installer-works.update');
    Route::delete('/account/installer-works/{work}', [InstallerAccountController::class, 'destroyWork'])
        ->name('account.installer-works.destroy');

    Route::get('/installer-cabinet-preview/{profile}', [InstallerAccountController::class, 'preview'])
        ->name('admin.installer-cabinet-preview');
});

// ===== Прокси изображений со старого сайта =====
Route::get('/proxy-image/{path}', function ($path) {
    if (str_contains($path, '..')) {
        abort(403);
    }

    $placeholder = public_path('img/products/product-placeholder.jpg');

    // На продакшене сначала ищем локальный файл (скопированный rsync)
    if (app()->environment('production')) {
        $localFile = public_path('images/'.$path);
        if (file_exists($localFile)) {
            $mime = mime_content_type($localFile) ?: 'image/jpeg';

            return response()->file($localFile, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=604800',
            ]);
        }

        // Файла нет — сразу placeholder. Внешний запрос делать НЕЛЬЗЯ:
        // после переезда DNS kotlov.by указывает на этот же сервер,
        // и fallback превращался в запрос сервера к самому себе (петля).
        return response()->file($placeholder, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    // На локалке: берём с боевого сервера (там файлы отдаются статикой)
    $baseUrl = rtrim(env('LEGACY_SITE_URL', 'https://kotlov.by'), '/');
    $url = "{$baseUrl}/images/{$path}";

    try {
        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=604800');
        }

        return response()->file($placeholder, ['Content-Type' => 'image/jpeg']);
    } catch (Exception $e) {
        return response()->file($placeholder, ['Content-Type' => 'image/jpeg']);
    }
})->where('path', '.*')
  // Картинкам сессия не нужна — иначе каждый запрос краулера создаёт строку в `sessions`
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        ValidateCsrfToken::class,
        PreventRequestForgery::class,
    ]);

// ===== Динамические роуты — ПОСЛЕДНИМИ =====

// 1 сегмент — категория
Route::get('/{category}', [CatalogController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+');

// 2 сегмента — подкатегория ИЛИ товар
Route::get('/{category}/{product}', [ProductController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+')
    ->where('product', '[a-z0-9\-\_,\(\)\.]+');

// 3 сегмента — категория/подкатегория/товар
Route::get('/{category}/{subcategory}/{product}', [ProductController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+')
    ->where('subcategory', '[a-z0-9\-\_]+')
    ->where('product', '[a-z0-9\-\_,\(\)\.]+');

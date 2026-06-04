<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InstallerController;
use App\Http\Controllers\InstallRequestController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogIndexController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\WishlistController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

// ===== Главная =====
Route::get('/', function () {
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

    $popularCategories = Category::query()
        ->where('is_active', true)
        ->whereIn('slug', [
            'kotly', 'teplovye-nasosy', 'kaminy', 'pechki',
            'dymohody', 'dlya-bani', 'vodonagrevateli',
            'otoplenie', 'nasosy', 'klimat',
        ])
        ->orderBy('sort_order')
        ->limit(10)
        ->get()
        ->each(function ($category) use ($categoryBranchIds) {
            $category->products_count = Product::where('is_active', true)
                ->whereIn('category_id', $categoryBranchIds($category->id))
                ->count();
        });

    $kotlyCatId = Category::where('slug', 'kotly')->value('id');
    $kotlyIds = $kotlyCatId ? $categoryBranchIds($kotlyCatId) : collect();

    $nasosCatId = Category::where('slug', 'teplovye-nasosy')->value('id');
    $nasosIds = $nasosCatId ? $categoryBranchIds($nasosCatId) : collect();

    $kaminCatId = Category::where('slug', 'kaminy')->value('id');
    $kaminIds = $kaminCatId ? $categoryBranchIds($kaminCatId) : collect();

    $productsKotly = Product::where('is_active', true)
        ->whereIn('category_id', $kotlyIds)
        ->with(['category', 'brand'])
        ->orderByDesc('is_featured')->orderByDesc('rating')
        ->limit(8)->get();

    $productsNasosy = Product::where('is_active', true)
        ->whereIn('category_id', $nasosIds)
        ->with(['category', 'brand'])
        ->orderByDesc('is_featured')->orderByDesc('rating')
        ->limit(8)->get();

    $productsKaminy = Product::where('is_active', true)
        ->whereIn('category_id', $kaminIds)
        ->with(['category', 'brand'])
        ->orderByDesc('is_featured')->orderByDesc('rating')
        ->limit(8)->get();

    $productsAkcii = Product::where('is_active', true)
        ->where('is_sale', true)
        ->with(['category', 'brand'])
        ->orderByDesc('rating')
        ->limit(8)->get();

    return view('pages.home-new', compact(
        'popularCategories',
        'productsKotly', 'productsNasosy',
        'productsKaminy', 'productsAkcii'
    ));
});

// ===== Статичные страницы — ДО динамических! =====
Route::view('/about',      'pages.about');
Route::view('/akcii',      'pages.akcii');
Route::view('/dostavka',   'pages.dostavka');
Route::view('/partners',   'pages.partners');
Route::view('/suppliers',  'pages.suppliers')->name('suppliers');
Route::get('/installers', [InstallerController::class, 'index'])->name('installers.index');
Route::get('/installers/{slug}', [InstallerController::class, 'show'])->name('installers.show');
Route::get('/install-request', [InstallRequestController::class, 'create'])->name('install-requests.create');
Route::post('/install-request', [InstallRequestController::class, 'store'])->name('install-requests.store');
Route::view('/reviews',    'pages.reviews');
Route::view('/faq',        'pages.faq');
Route::view('/privacy',    'pages.privacy');
// ===== Корзина =====
Route::get('/cart',          [CartController::class, 'index'])->name('cart');
Route::post('/cart/add',     [CartController::class, 'add'])->name('cart.add');
Route::post('/cart/update',  [CartController::class, 'update'])->name('cart.update');
Route::post('/cart/remove',  [CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/clear',   [CartController::class, 'clear'])->name('cart.clear');
Route::get('/cart/data',     [CartController::class, 'data'])->name('cart.data');
Route::post('/cart/note',     [CartController::class, 'saveNote'])->name('cart.note');
Route::post('/cart/delivery', [CartController::class, 'saveDelivery'])->name('cart.delivery');
Route::post('/cart/coupon',   [CartController::class, 'saveCoupon'])->name('cart.coupon');

// ===== Checkout =====
Route::get('/checkout',                      [CheckoutController::class, 'index'])->name('checkout');
Route::post('/checkout',                     [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/success/{number}',     [CheckoutController::class, 'success'])->name('checkout.success');

// ===== Блог =====
Route::get('/blog',       [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}',[BlogController::class, 'show'])->name('blog.show');

// ===== Контакты =====
Route::get('/contacts',   fn() => view('pages.contacts'))->name('contacts');
Route::post('/contacts',  fn() => back()->with('success', 'Сообщение отправлено!'))->name('contact.store');

// ===== Сравнение =====
Route::get('/compare',          [CompareController::class, 'index'])->name('compare');
Route::post('/compare/add',     [CompareController::class, 'add'])->name('compare.add');
Route::post('/compare/remove',  [CompareController::class, 'remove'])->name('compare.remove');
Route::post('/compare/clear',   [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/clear-ajax', [CompareController::class, 'clearAjax'])->name('compare.clear-ajax');
Route::get('/compare/items',    [CompareController::class, 'items'])->name('compare.items');

// ===== Избранное =====
Route::get('/wishlist',          [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/add',     [WishlistController::class, 'add'])->name('wishlist.add');
Route::post('/wishlist/remove',  [WishlistController::class, 'remove'])->name('wishlist.remove');
Route::post('/wishlist/toggle',  [WishlistController::class, 'toggle'])->name('wishlist.toggle');

// ===== Поиск =====
Route::get('/search',         [SearchController::class, 'index'])->name('search');
Route::get('/search/suggest', [SearchController::class, 'suggest'])->name('search.suggest');

// ===== Каталог =====
Route::get('/catalog', [CatalogIndexController::class, 'index'])->name('catalog');

// ===== Бренды =====
Route::get('/brands',        [BrandController::class, 'index'])->name('brands');
Route::get('/brands/{slug}', [BrandController::class, 'show'])->name('brand.show');

// ===== Прочие формы =====
Route::post('/ask', fn() => back()->with('success', 'Вопрос отправлен!'))->name('ask.store');

// ===== Auth страницы (GET) =====
Route::get('/login', function () {
    if (auth()->check()) return redirect('/account');
    return view('pages.login');
})->name('login');

Route::get('/forgot-password', function () {
    if (auth()->check()) return redirect('/account');
    return view('pages.forgot-password');
})->name('password.request');

// ===== Auth действия (POST) =====
Route::post('/register',        [AuthController::class, 'register'])->name('register');
Route::post('/login',           [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout',          [AuthController::class, 'logout'])->name('logout');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password',        [AuthController::class, 'resetPassword'])->name('password.update');

// ===== Личный кабинет =====
Route::middleware('auth')->group(function () {
    Route::get('/account',              [AccountController::class, 'index'])->name('account');
    Route::put('/account/profile',      [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password',     [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/b2b-request', [AccountController::class, 'b2bRequest'])->name('account.b2b-request');
});


// ===== Прокси изображений со старого сайта =====
Route::get('/proxy-image/{path}', function ($path) {
    if (str_contains($path, '..')) {
        abort(403);
    }

    $baseUrl = rtrim(env('LEGACY_SITE_URL', 'https://kotlov.by'), '/');
    $url = "{$baseUrl}/images/{$path}";

    try {
        $response = Http::timeout(10)->get($url);

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type') ?? 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=604800');
        }

        return response()->file(public_path('images/default-image.jpg'), [
            'Content-Type' => 'image/jpeg',
        ]);
    } catch (\Exception $e) {
        return response()->file(public_path('images/default-image.jpg'), [
            'Content-Type' => 'image/jpeg',
        ]);
    }
})->where('path', '.*');

// ===== Динамические роуты — ПОСЛЕДНИМИ =====

// 1 сегмент — категория
Route::get('/{category}', [CatalogController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+');

// 2 сегмента — подкатегория ИЛИ товар
Route::get('/{category}/{product}', [ProductController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+')
    ->where('product',  '[a-z0-9\-\_]+');

// 3 сегмента — категория/подкатегория/товар
Route::get('/{category}/{subcategory}/{product}', [ProductController::class, 'show'])
    ->where('category',    '[a-z0-9\-\_]+')
    ->where('subcategory', '[a-z0-9\-\_]+')
    ->where('product',     '[a-z0-9\-\_]+');

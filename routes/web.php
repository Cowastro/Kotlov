<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CompareController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogIndexController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AccountController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// ===== Главная =====
Route::get('/', function () {
    $popularCategories = Category::query()
        ->where('is_active', true)
        ->whereIn('slug', [
            'kotly', 'teplovye-nasosy', 'kaminy', 'pechki',
            'dymohody', 'dlya-bani', 'vodonagrevateli',
            'otoplenie', 'nasosy', 'klimat',
        ])
        ->withCount('products')
        ->orderBy('sort_order')
        ->limit(10)
        ->get();

    $kotlyCatId = Category::where('slug', 'kotly')->value('id');
    $kotlyIds = $kotlyCatId
        ? Category::where('id', $kotlyCatId)->orWhere('parent_id', $kotlyCatId)->pluck('id')
        : collect();

    $nasosCatId = Category::where('slug', 'teplovye-nasosy')->value('id');
    $nasosIds = $nasosCatId
        ? Category::where('id', $nasosCatId)->orWhere('parent_id', $nasosCatId)->pluck('id')
        : collect();

    $kaminCatId = Category::where('slug', 'kaminy')->value('id');
    $kaminIds = $kaminCatId
        ? Category::where('id', $kaminCatId)->orWhere('parent_id', $kaminCatId)->pluck('id')
        : collect();

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
Route::get('/blog',         [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}',   [BlogController::class, 'show'])->name('blog.show');
Route::view('/dostavka',   'pages.dostavka');
Route::get('/contacts',    fn() => view('pages.contacts'))->name('contacts');
Route::view('/partners',   'pages.partners');
Route::view('/installers', 'pages.installers');
Route::view('/reviews',    'pages.reviews');
Route::view('/faq',        'pages.faq');
Route::view('/privacy',    'pages.privacy');
Route::get('/compare',         [CompareController::class, 'index'])->name('compare');
Route::post('/compare/add',    [CompareController::class, 'add'])->name('compare.add');
Route::post('/compare/remove', [CompareController::class, 'remove'])->name('compare.remove');
Route::post('/compare/clear',      [CompareController::class, 'clear'])->name('compare.clear');
Route::post('/compare/clear-ajax', [CompareController::class, 'clearAjax'])->name('compare.clear-ajax');
Route::get('/compare/items',       [CompareController::class, 'items'])->name('compare.items');
Route::view('/cart',       'pages.cart');
Route::view('/checkout',   'pages.checkout');
Route::get('/wishlist',          [WishlistController::class, 'index'])->name('wishlist');
Route::post('/wishlist/add',    [WishlistController::class, 'add'])->name('wishlist.add');
Route::post('/wishlist/remove', [WishlistController::class, 'remove'])->name('wishlist.remove');
Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

Route::get('/search',  fn() => view('pages.catalog'))->name('search');
Route::get('/catalog', [CatalogIndexController::class, 'index'])->name('catalog');

// ===== Бренды =====
Route::get('/brands',         [BrandController::class, 'index'])->name('brands');
Route::get('/brands/{slug}',  [BrandController::class, 'show'])->name('brand.show');

// ===== Формы =====
Route::post('/ask',      fn() => back()->with('success', 'Вопрос отправлен!'))->name('ask.store');
Route::post('/contacts', fn() => back()->with('success', 'Сообщение отправлено!'))->name('contact.store');

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

// ===== Личный кабинет — только авторизованным =====
Route::middleware('auth')->group(function () {
    Route::get('/account',              [AccountController::class, 'index'])->name('account');
    Route::put('/account/profile',      [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password',     [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/b2b-request', [AccountController::class, 'b2bRequest'])->name('account.b2b-request');
});

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
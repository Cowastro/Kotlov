<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Route;

// Главная
Route::get('/', function () {
    $popularCategories = Category::query()
        ->where('is_active', true)
        ->whereIn('slug', [
            'kotly', 'teplovyie-nasosyi', 'kaminy', 'pechki',
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

    $nasosCatId = Category::where('slug', 'teplovyie-nasosyi')->value('id');
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

// Статичные страницы — ДО динамических!
Route::view('/about', 'pages.about');
Route::view('/brands', 'pages.brands');
Route::view('/akcii', 'pages.akcii');
Route::view('/blog', 'pages.blog');
Route::view('/dostavka', 'pages.dostavka');
Route::view('/contacts', 'pages.contacts');
Route::view('/partners', 'pages.partners');
Route::view('/installers', 'pages.installers');
Route::view('/reviews', 'pages.reviews');
Route::view('/faq', 'pages.faq');
Route::view('/privacy', 'pages.privacy');
Route::view('/compare', 'pages.compare');
Route::view('/cart', 'pages.cart');
Route::view('/checkout', 'pages.checkout');
Route::view('/account', 'pages.account');
Route::view('/wishlist', 'pages.wishlist');

Route::get('/search', fn() => view('pages.catalog'))->name('search');

// Формы
Route::post('/ask', fn() => back()->with('success', 'Вопрос отправлен!'))->name('ask.store');
Route::post('/contacts', fn() => back()->with('success', 'Сообщение отправлено!'))->name('contact.store');
Route::post('/register', fn() => back())->name('register');
Route::post('/login', fn() => back())->name('login');
Route::post('/logout', fn() => redirect('/'))->name('logout');
Route::post('/forgot-password', fn() => back()->with('success', 'Инструкция отправлена'))->name('password.email');

// Динамические роуты — каталог и товары
// 1 сегмент — категория
Route::get('/{category}', [CatalogController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+');

// 2 сегмента — подкатегория ИЛИ товар
Route::get('/{category}/{product}', [ProductController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+')
    ->where('product', '[a-z0-9\-\_]+');

// 3 сегмента — категория/подкатегория/товар
Route::get('/{category}/{subcategory}/{product}', [ProductController::class, 'show'])
    ->where('category', '[a-z0-9\-\_]+')
    ->where('subcategory', '[a-z0-9\-\_]+')
    ->where('product', '[a-z0-9\-\_]+');
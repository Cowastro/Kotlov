<?php

use Illuminate\Support\Facades\Route;

// Главная
Route::view('/', 'pages.home-new');

// Каталог и категории
Route::view('/catalog', 'pages.catalog');
Route::view('/kotly', 'pages.catalog');
Route::view('/teplovye-nasosy', 'pages.catalog');
Route::view('/pelletnye-gorelki', 'pages.catalog');
Route::view('/kaminy', 'pages.catalog');
Route::view('/dymohody', 'pages.catalog');
Route::view('/otoplenie', 'pages.catalog');
Route::view('/dlya-bani', 'pages.catalog');
Route::view('/vodosnabzhenie', 'pages.catalog');
Route::view('/klimat', 'pages.catalog');

// Статичные страницы
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

// Поиск
Route::get('/search', fn() => view('pages.catalog'))->name('search');

// Формы — временные заглушки, заменим на контроллеры после Filament
Route::post('/ask', fn() => back()->with('success', 'Вопрос отправлен!'))->name('ask.store');
Route::post('/contacts', fn() => back()->with('success', 'Сообщение отправлено!'))->name('contact.store');

// Авторизация — временные заглушки
Route::post('/register', fn() => back())->name('register');
Route::post('/login', fn() => back())->name('login');
Route::post('/logout', fn() => redirect('/'))->name('logout');
Route::post('/forgot-password', fn() => back()->with('success', 'Инструкция отправлена на email'))->name('password.email');
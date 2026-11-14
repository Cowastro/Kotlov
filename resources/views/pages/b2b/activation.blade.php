@extends('layouts.amerce')

@section('title', 'Вход в B2B-кабинет — KOTLOV.BY')

@section('content')
<main class="b2b-invite-page">
    <section class="b2b-invite-card">
        <a class="b2b-invite-brand" href="{{ url('/') }}"><img src="{{ asset('img/logo-kotlov.png') }}" alt="KOTLOV.BY"><span>B2B</span></a>
        @if(!$valid || !$client)
            <div class="b2b-invite-state is-error"><span>Ссылка уже использована</span><h1>Пароль уже задан</h1><p>Войдите с e-mail {{ $user->email }} и своим паролем. Если не помните пароль — воспользуйтесь восстановлением на странице входа.</p><a href="{{ route('login') }}">Войти</a></div>
        @elseif(auth()->check() && auth()->id() !== $user->id)
            <div class="b2b-invite-state is-error"><span>Другой аккаунт</span><h1>Войдите под {{ $user->email }}</h1><p>Ссылка персональная и открывает только этот аккаунт.</p><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Выйти и сменить аккаунт</button></form></div>
        @else
            <header><span>B2B-кабинет монтажника</span><h1>{{ $client->company_name }}</h1><p>Ваш профиль монтажника остаётся на сайте. В B2B-кабинете — партнёрские цены, заказ со склада с резервом, счета и документы компании.</p></header>
            <dl><div><dt>E-mail для входа</dt><dd>{{ $user->email }}</dd></div></dl>
            <form method="POST" action="{{ request()->fullUrl() }}" class="b2b-invite-form">@csrf
                <h2>Задайте пароль</h2>
                <label><span>Пароль *</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                <label><span>Повторите пароль *</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                <button type="submit">Сохранить и войти в кабинет</button>
                <small>Если вы помните свой пароль, можно просто <a href="{{ route('login') }}">войти</a>.</small>
            </form>
            @if($errors->any())<div class="b2b-invite-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @endif
    </section>
</main>
@include('pages.b2b.partials.invite-styles')
@endsection

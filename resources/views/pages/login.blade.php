@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    <section class="flat-spacing">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-5 col-lg-6 col-md-8">

                    {{-- Логотип / заголовок --}}
                    <div class="text-center mb-32">
                        <a href="/" class="d-inline-block mb-16">
                            {{-- logo-dark.png — тёмная версия логотипа для светлого фона --}}
                            {{-- если есть только одно лого — используем filter: invert() через CSS --}}
                            <img src="{{ asset('img/logo.png') }}" alt="KOTLOV.BY" height="36" class="login-logo">
                        </a>
                        <p class="text-body-1 cl-text-2">Маркетплейс отопительного оборудования</p>
                    </div>

                    {{-- Flash --}}
                    @if (session('success'))
                        <div class="kotlov-alert kotlov-alert-success mb-20">
                            <i class="icon icon-CheckCircle"></i> {{ session('success') }}
                        </div>
                    @endif

                    {{-- Табы --}}
                    <div class="login-card">

                        <div class="login-tabs">
                            <button class="login-tab-btn active" data-target="tab-login">Войти</button>
                            <button class="login-tab-btn" data-target="tab-register">Регистрация</button>
                        </div>

                        {{-- ===== ТАБ ВХОД ===== --}}
                        <div id="tab-login" class="login-tab-content active">

                            @if ($errors->any() && old('_form') === 'login')
                                <div class="kotlov-alert kotlov-alert-error mb-16">
                                    @foreach ($errors->all() as $error)
                                        <p class="mb-0"><i class="icon icon-Warning"></i> {{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <form action="/login" method="POST" class="form-log">
                                @csrf
                                <input type="hidden" name="_form" value="login">

                                <div class="form-content">
                                    <fieldset class="tf-field">
                                        <label for="login-email" class="tf-lable fw-medium">
                                            Email <span class="text-primary">*</span>
                                        </label>
                                        <input type="email" id="login-email" name="email"
                                            value="{{ old('email') }}"
                                            placeholder="Ваш email"
                                            required autofocus>
                                    </fieldset>

                                    <fieldset class="tf-field">
                                        <label for="login-password" class="tf-lable fw-medium">
                                            Пароль <span class="text-primary">*</span>
                                        </label>
                                        <div class="password-wrapper w-100">
                                            <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                            <input class="password-field" type="password"
                                                id="login-password" name="password"
                                                placeholder="Ваш пароль" required>
                                        </div>
                                    </fieldset>

                                    <fieldset class="field-bottom">
                                        <div class="checkbox-wrap">
                                            <input class="tf-check style-2" type="checkbox"
                                                name="remember" id="remember-page">
                                            <label for="remember-page">Запомнить меня</label>
                                        </div>
                                        <a href="#modalForgot" data-bs-toggle="modal"
                                            class="link text-decoration-underline">
                                            <span class="text-caption-01 fw-semibold">Забыли пароль?</span>
                                        </a>
                                    </fieldset>
                                </div>

                                <div class="group-action">
                                    <button type="submit" class="tf-btn animate-btn w-100">Войти</button>
                                </div>
                            </form>
                        </div>

                        {{-- ===== ТАБ РЕГИСТРАЦИЯ ===== --}}
                        <div id="tab-register" class="login-tab-content">

                            @if ($errors->any() && old('_form') === 'register')
                                <div class="kotlov-alert kotlov-alert-error mb-16">
                                    @foreach ($errors->all() as $error)
                                        <p class="mb-0"><i class="icon icon-Warning"></i> {{ $error }}</p>
                                    @endforeach
                                </div>
                            @endif

                            <form action="/register" method="POST" class="form-log">
                                @csrf
                                <input type="hidden" name="_form" value="register">

                                <div class="form-content">
                                    <fieldset class="tf-field">
                                        <label for="reg-name" class="tf-lable fw-medium">
                                            Имя <span class="text-primary">*</span>
                                        </label>
                                        <input type="text" id="reg-name" name="name"
                                            value="{{ old('name') }}"
                                            placeholder="Ваше имя" required>
                                    </fieldset>

                                    <fieldset class="tf-field">
                                        <label for="reg-email" class="tf-lable fw-medium">
                                            Email <span class="text-primary">*</span>
                                        </label>
                                        <input type="email" id="reg-email" name="email"
                                            value="{{ old('email') }}"
                                            placeholder="Ваш email" required>
                                    </fieldset>

                                    <fieldset class="tf-field">
                                        <label for="reg-password" class="tf-lable fw-medium">
                                            Пароль <span class="text-primary">*</span>
                                        </label>
                                        <div class="password-wrapper w-100">
                                            <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                            <input class="password-field" type="password"
                                                id="reg-password" name="password"
                                                placeholder="Минимум 8 символов" required>
                                        </div>
                                    </fieldset>

                                    <fieldset class="tf-field">
                                        <label for="reg-password-confirm" class="tf-lable fw-medium">
                                            Повторите пароль <span class="text-primary">*</span>
                                        </label>
                                        <div class="password-wrapper w-100">
                                            <span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span>
                                            <input class="password-field" type="password"
                                                id="reg-password-confirm" name="password_confirmation"
                                                placeholder="Повторите пароль" required>
                                        </div>
                                    </fieldset>
                                </div>

                                <div class="checkbox-wrap">
                                    <input class="tf-check flex-shrink-0" type="checkbox"
                                        id="reg-subscribe" name="subscribe" value="1"
                                        {{ old('subscribe') ? 'checked' : 'checked' }}>
                                    <label for="reg-subscribe">
                                        Подписаться на новости и акции KOTLOV.BY
                                    </label>
                                </div>

                                <div class="group-action">
                                    <button type="submit" class="tf-btn animate-btn w-100">
                                        Создать аккаунт
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                    {{-- /login-card --}}

                    <p class="text-center text-caption-01 cl-text-3 mt-20">
                        Продолжая, вы соглашаетесь с
                        <a href="/privacy" class="link text-decoration-underline">политикой конфиденциальности</a>
                    </p>

                </div>
            </div>
        </div>
    </section>

</main>

@push('styles')
<style>
.login-card {
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 16px;
    overflow: hidden;
}

.login-tabs {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-bottom: 1px solid #e8e8e8;
}

.login-tab-btn {
    padding: 16px;
    background: #fafafa;
    border: none;
    font-size: 15px;
    font-weight: 600;
    color: #999;
    cursor: pointer;
    transition: .2s;
}

.login-tab-btn:first-child {
    border-right: 1px solid #e8e8e8;
}

.login-tab-btn.active {
    background: #fff;
    color: #111;
}

.login-tab-content {
    display: none;
    padding: 28px;
}

.login-tab-content.active {
    display: block;
}

.kotlov-alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
}
.kotlov-alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
.kotlov-alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

.mb-32 { margin-bottom: 32px; }

/* Логотип: если файл белый — инвертируем на тёмный */
.login-logo {
    filter: brightness(0); /* делает любой логотип чёрным */
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btns    = document.querySelectorAll('.login-tab-btn');
    const contents = document.querySelectorAll('.login-tab-content');

    function showTab(target) {
        btns.forEach(b => b.classList.remove('active'));
        contents.forEach(c => c.classList.remove('active'));

        document.querySelector('[data-target="' + target + '"]').classList.add('active');
        document.getElementById(target).classList.add('active');
    }

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            showTab(this.dataset.target);
        });
    });

    // При ошибке — открываем нужный таб
    @if (old('_form') === 'register' && $errors->any())
        showTab('tab-register');
    @endif
});
</script>
@endpush

@endsection
@extends('layouts.amerce')

@section('content')
<main id="wrapper">

    {{-- Заголовок --}}
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="/login" class="text-caption-01 cl-text-3 link">Вход</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">Восстановление пароля</p>
                </div>
                <h3 class="letter-space-0">Восстановление пароля</h3>
            </div>
        </div>
    </section>

    <section class="section-log flat-spacing">
        <div class="container">
            <div class="row align-items-center gy-30">

                {{-- Форма --}}
                <div class="col-md-5 ms-auto">
                    <div class="col-left">
                        <h4 class="title mb-10">Сбросить пароль</h4>
                        <p class="cl-text-2 mb-24">
                            Укажите email вашего аккаунта — пришлём ссылку для создания нового пароля.
                        </p>

                        {{-- Flash сообщения --}}
                        @if (session('success'))
                            <div class="kotlov-alert kotlov-alert-success mb-20">
                                <i class="icon icon-CheckCircle"></i> {{ session('success') }}
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="kotlov-alert kotlov-alert-error mb-20">
                                @foreach ($errors->all() as $error)
                                    <p class="mb-0"><i class="icon icon-Warning"></i> {{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form action="/forgot-password" method="POST" class="form-log">
                            @csrf
                            <div class="form-content">
                                <fieldset class="tf-field">
                                    <label for="forgot-email" class="tf-lable fw-medium">
                                        Email адрес <span class="text-primary">*</span>
                                    </label>
                                    <input type="email" id="forgot-email" name="email"
                                        value="{{ old('email') }}"
                                        placeholder="Ваш email*"
                                        required autofocus>
                                </fieldset>
                            </div>
                            <div class="group-action mt-20">
                                <button type="submit" class="tf-btn animate-btn w-100">
                                    Отправить инструкцию
                                </button>
                                <a href="/login" class="tf-btn btn-stroke w-100">
                                    Вернуться ко входу
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Правый блок --}}
                <div class="col-md-5 me-auto">
                    <div class="col-right">
                        <h4 class="mb-8">Вспомнили пароль?</h4>
                        <p class="cl-text-2 mb-20">
                            Войдите в личный кабинет для управления заказами, просмотра истории покупок
                            и доступа к специальным условиям.
                        </p>
                        <a href="/login" class="tf-btn animate-btn">
                            Войти в аккаунт
                        </a>

                        <div class="br-line my-24"></div>

                        <h6 class="mb-8">Нет аккаунта?</h6>
                        <p class="cl-text-2 mb-16">
                            Зарегистрируйтесь — это займёт меньше минуты.
                        </p>
                        <a href="/login" class="tf-btn btn-stroke">
                            Создать аккаунт
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

</main>

@push('styles')
<style>
.kotlov-alert {
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 14px;
    margin-bottom: 0;
}
.kotlov-alert p { margin: 0; }
.kotlov-alert-success { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
.kotlov-alert-error   { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }

.col-right .br-line { border-top: 1px solid #e8e8e8; }
.my-24 { margin-top: 24px; margin-bottom: 24px; }
</style>
@endpush

@endsection

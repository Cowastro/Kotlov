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
                    <p class="text-caption-01">Новый пароль</p>
                </div>
                <h3 class="letter-space-0">Новый пароль</h3>
            </div>
        </div>
    </section>

    <section class="section-log flat-spacing">
        <div class="container">
            <div class="row align-items-center gy-30">

                {{-- Форма --}}
                <div class="col-md-5 ms-auto">
                    <div class="col-left">
                        <h4 class="title mb-10">Задайте новый пароль</h4>
                        <p class="cl-text-2 mb-24">
                            Минимум 8 символов. После сохранения войдите с новым паролем.
                        </p>

                        @if ($errors->any())
                            <div class="kotlov-alert kotlov-alert-error mb-20">
                                @foreach ($errors->all() as $error)
                                    <p class="mb-0"><i class="icon icon-Warning"></i> {{ $error }}</p>
                                @endforeach
                            </div>
                        @endif

                        <form action="{{ route('password.update') }}" method="POST" class="form-log">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">
                            <div class="form-content">
                                <fieldset class="tf-field">
                                    <label for="reset-email" class="tf-lable fw-medium">
                                        Email адрес <span class="text-primary">*</span>
                                    </label>
                                    <input type="email" id="reset-email" name="email"
                                        value="{{ old('email', $email) }}"
                                        placeholder="Ваш email*"
                                        autocomplete="email" required>
                                </fieldset>
                                <fieldset class="tf-field">
                                    <label for="reset-password" class="tf-lable fw-medium">
                                        Новый пароль <span class="text-primary">*</span>
                                    </label>
                                    <input type="password" id="reset-password" name="password"
                                        placeholder="Новый пароль*" autocomplete="new-password"
                                        minlength="8" required autofocus>
                                </fieldset>
                                <fieldset class="tf-field">
                                    <label for="reset-password-confirmation" class="tf-lable fw-medium">
                                        Повторите пароль <span class="text-primary">*</span>
                                    </label>
                                    <input type="password" id="reset-password-confirmation" name="password_confirmation"
                                        placeholder="Повторите пароль*" autocomplete="new-password"
                                        minlength="8" required>
                                </fieldset>
                            </div>
                            <div class="group-action mt-20">
                                <button type="submit" class="tf-btn animate-btn w-100">
                                    Сохранить пароль
                                </button>
                                <a href="/forgot-password" class="tf-btn btn-stroke w-100">
                                    Запросить новую ссылку
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Правый блок --}}
                <div class="col-md-5 me-auto">
                    <div class="col-right">
                        <h4 class="mb-8">Ссылка не сработала?</h4>
                        <p class="cl-text-2 mb-20">
                            Ссылка из письма действует ограниченное время и срабатывает один раз.
                            Если она устарела, запросите новую — письмо придёт на тот же email.
                        </p>
                        <a href="/forgot-password" class="tf-btn btn-stroke">
                            Восстановить пароль заново
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
.kotlov-alert-error { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
</style>
@endpush

@endsection

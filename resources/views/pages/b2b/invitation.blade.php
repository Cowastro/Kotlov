@extends('layouts.amerce')

@section('title', 'Приглашение в B2B-кабинет — KOTLOV.BY')

@section('content')
<main class="b2b-invite-page">
    <section class="b2b-invite-card">
        <a class="b2b-invite-brand" href="{{ url('/') }}"><img src="{{ asset('img/logo.svg') }}" alt="KOTLOV.BY"><span>B2B</span></a>
        @if(!$invitation->isAvailable())
            <div class="b2b-invite-state is-error"><span>Ссылка недействительна</span><h1>Приглашение больше не активно</h1><p>Оно уже принято, отозвано владельцем компании или истёк срок действия. Попросите владельца отправить новое приглашение.</p></div>
        @elseif(auth()->check() && mb_strtolower(auth()->user()->email) !== mb_strtolower($invitation->email))
            <div class="b2b-invite-state is-error"><span>Другой аккаунт</span><h1>Войдите под {{ $invitation->email }}</h1><p>Приглашение персональное и не может быть передано другому пользователю.</p><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit">Выйти и сменить аккаунт</button></form></div>
        @else
            <header><span>Приглашение в компанию</span><h1>{{ $invitation->client->company_name }}</h1><p>Один кабинет для заказов, документов и условий компании.</p></header>
            <dl><div><dt>Ваш email</dt><dd>{{ $invitation->email }}</dd></div><div><dt>Роль</dt><dd>{{ $invitation->roleLabel() }}</dd></div><div><dt>Ссылка действует до</dt><dd>{{ $invitation->expires_at->format('d.m.Y H:i') }}</dd></div></dl>
            @if($existingUser && !auth()->check())
                <div class="b2b-invite-state"><h2>Аккаунт уже существует</h2><p>Войдите под указанным email. После входа вы вернётесь сюда для подтверждения.</p><a href="{{ route('login') }}">Войти и продолжить</a></div>
            @elseif($existingUser)
                <form method="POST" action="{{ route('b2b.invitation.accept', ['token' => $token]) }}" class="b2b-invite-form">@csrf<button type="submit">Принять приглашение</button><small>После подтверждения появятся разделы, разрешённые вашей ролью.</small></form>
            @else
                <form method="POST" action="{{ route('b2b.invitation.accept', ['token' => $token]) }}" class="b2b-invite-form">@csrf
                    <h2>Создать рабочий аккаунт</h2>
                    <label><span>Имя и фамилия *</span><input name="name" value="{{ old('name') }}" autocomplete="name" required></label>
                    <label><span>Рабочий телефон *</span><input type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" placeholder="+375 (XX) XXX-XX-XX" required></label>
                    <label><span>Пароль *</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                    <label><span>Повторите пароль *</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label>
                    <label class="b2b-invite-consent"><input type="checkbox" name="privacy_accepted" value="1" required><span>Я принимаю условия <a href="{{ url('/privacy') }}" target="_blank">обработки персональных данных</a></span></label>
                    <button type="submit">Создать аккаунт и войти</button>
                </form>
            @endif
            @if($errors->any())<div class="b2b-invite-errors">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @endif
    </section>
</main>
@include('pages.b2b.partials.invite-styles')
@endsection

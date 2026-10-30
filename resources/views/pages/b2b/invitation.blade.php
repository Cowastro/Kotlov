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
<style>
.b2b-invite-page{min-height:70vh;padding:56px 16px;background:#f3f6fa}.b2b-invite-card{max-width:620px;margin:auto;padding:34px;border:1px solid #e2e8f0;border-radius:20px;background:#fff;box-shadow:0 18px 55px rgba(20,36,58,.09)}.b2b-invite-brand{display:flex;align-items:center;gap:10px;width:max-content;margin-bottom:30px}.b2b-invite-brand img{width:145px}.b2b-invite-brand span{padding:5px 7px;border-radius:7px;background:#ff6268;color:#fff;font-size:12px;font-weight:800}.b2b-invite-card header>span,.b2b-invite-state>span{color:#15946e;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.b2b-invite-card h1{margin:9px 0 10px;font-size:31px}.b2b-invite-card header p,.b2b-invite-state p{color:#68778a;line-height:1.55}.b2b-invite-card dl{display:grid;gap:1px;margin:25px 0;background:#e8edf3;border:1px solid #e8edf3;border-radius:12px;overflow:hidden}.b2b-invite-card dl div{display:grid;grid-template-columns:160px 1fr;gap:15px;padding:14px 16px;background:#f8fafc}.b2b-invite-card dt{color:#748195}.b2b-invite-card dd{margin:0;font-weight:700;overflow-wrap:anywhere}.b2b-invite-form{display:grid;gap:15px;margin-top:25px}.b2b-invite-form h2{margin:0 0 3px;font-size:21px}.b2b-invite-form label>span{display:block;margin-bottom:6px;font-size:13px;font-weight:700}.b2b-invite-form input:not([type=checkbox]){width:100%;height:50px;padding:0 14px;border:1px solid #d9e0e8;border-radius:9px}.b2b-invite-form button,.b2b-invite-state a,.b2b-invite-state button{display:block;width:100%;padding:15px;border:0;border-radius:10px;background:#14243a;color:#fff;text-align:center;text-decoration:none;font-weight:800}.b2b-invite-form small{color:#748195;text-align:center}.b2b-invite-consent{display:flex;align-items:flex-start;gap:9px}.b2b-invite-consent input{margin-top:3px}.b2b-invite-state{padding:20px;border-radius:12px;background:#f2faf7}.b2b-invite-state h2{margin:0 0 8px}.b2b-invite-state.is-error{background:#fff4f4}.b2b-invite-errors{margin-top:18px;padding:12px 16px;border-radius:10px;background:#fff1f1;color:#a82e2e}.b2b-invite-errors p{margin:3px 0}@media(max-width:600px){.b2b-invite-page{padding:24px 10px}.b2b-invite-card{padding:24px 18px}.b2b-invite-card h1{font-size:26px}.b2b-invite-card dl div{grid-template-columns:1fr;gap:4px}}
</style>
@endsection

@extends('layouts.amerce')

@section('title', 'Подтверждение email — KOTLOV.BY')
@section('robots', 'noindex, nofollow')

@section('content')
<section class="verification-page">
    <div class="container">
        <article class="verification-card">
            <div class="verification-card__mark" aria-hidden="true">✉</div>
            <span class="verification-card__eyebrow">Активация аккаунта</span>
            <h1>Проверьте вашу почту</h1>
            <p>Мы отправили фирменное письмо со ссылкой активации на:</p>
            <strong class="verification-card__email">{{ $user->email }}</strong>
            <p class="verification-card__hint">Перейдите по ссылке в письме. До подтверждения email вход в личный и B2B-кабинет закрыт.</p>

            @if(session('status'))
                <div class="verification-card__alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="verification-card__alert is-error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit">Отправить письмо повторно</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="verification-card__secondary">Указать другой email</button>
            </form>
            <small>Не нашли письмо? Проверьте папки «Спам» и «Промоакции».</small>
        </article>
    </div>
</section>

<style>
.verification-page{min-height:68vh;display:flex;align-items:center;padding:64px 0;background:#f5f5f5}.verification-card{width:min(100%,620px);margin:0 auto;padding:42px;border:1px solid #e1e1e1;border-radius:20px;background:#fff;text-align:center;box-shadow:0 18px 55px rgba(0,0,0,.06)}.verification-card__mark{display:grid;place-items:center;width:64px;height:64px;margin:0 auto 22px;border-radius:50%;background:#111;color:#fff;font-size:27px}.verification-card__eyebrow{color:#dc4646;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}.verification-card h1{margin:8px 0 12px;font-size:34px;line-height:1.2}.verification-card p{margin:0;color:#6b6f76;line-height:1.65}.verification-card__email{display:block;margin:12px 0 18px;font-size:18px;word-break:break-word}.verification-card__hint{max-width:500px;margin:0 auto 24px!important}.verification-card form+form{margin-top:10px}.verification-card button{width:100%;min-height:52px;border:1px solid #111;border-radius:999px;background:#111;color:#fff;font-weight:700}.verification-card__secondary{background:#fff!important;color:#111!important}.verification-card small{display:block;margin-top:20px;color:#8a8d92}.verification-card__alert{margin:0 0 18px;padding:12px 14px;border-radius:9px;font-size:14px}.verification-card__alert.is-success{background:#eef9f1;color:#18733b}.verification-card__alert.is-error{background:#fff0f0;color:#a92121}@media(max-width:575px){.verification-page{padding:28px 0}.verification-card{padding:30px 20px;border-radius:14px}.verification-card h1{font-size:28px}}
</style>
@endsection

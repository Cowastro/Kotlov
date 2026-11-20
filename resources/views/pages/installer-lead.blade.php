<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Заявка на монтаж №{{ $installRequest->id }} — KOTLOV</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f6f8;color:#18212a;font:16px/1.5 Arial,sans-serif}main{max-width:640px;margin:36px auto;padding:0 18px 40px}header{display:flex;justify-content:center;margin-bottom:24px}header img{width:155px;height:auto}.card{background:#fff;border:1px solid #e1e5e9;border-radius:20px;padding:28px;margin-bottom:16px}h1{font-size:26px;line-height:1.25;margin:0 0 8px;letter-spacing:-.4px}h2{font-size:18px;margin:0 0 12px}.muted{color:#697480;font-size:14px;margin:0 0 20px}dl{display:grid;grid-template-columns:minmax(110px,38%) 1fr;gap:8px 14px;margin:0 0 20px}dt{color:#697480}dd{margin:0;overflow-wrap:anywhere}.desc{white-space:pre-line;background:#f7f8fa;border-radius:12px;padding:14px;margin:0 0 20px;overflow-wrap:anywhere}button{width:100%;border:0;background:#de4147;color:#fff;border-radius:12px;font:600 16px/1.3 Arial,sans-serif;padding:15px;cursor:pointer}button:hover{background:#c9353c}button:focus-visible{outline:3px solid #18212a;outline-offset:3px}.link-button{background:none;color:#697480;padding:6px;font:13px Arial,sans-serif;text-decoration:underline;width:auto}.link-button:hover{background:none;color:#de4147}.notice{background:#ecf8f0;border:1px solid #bde1ca;color:#21653b;padding:14px;border-radius:12px;margin-bottom:16px}.closed{background:#fff6e5;border:1px solid #f0d9a8;color:#7a5410;padding:14px;border-radius:12px;margin:0 0 4px}.contacts{border-color:#bde1ca;background:#f6fcf8}.contacts a{color:#18212a;font-weight:600}.slots{font-size:14px;color:#697480;margin:12px 0 0;text-align:center}.footer{text-align:center;font-size:13px;color:#697480}@media(max-width:480px){main{margin-top:20px;padding-inline:14px}.card{padding:22px 18px;border-radius:16px}h1{font-size:23px}dl{grid-template-columns:1fr;gap:2px}dd{margin-bottom:8px}}
    </style>
</head>
<body>
@php
    $details = (array) ($installRequest->project_details ?? []);
    $mine = $state === \App\Services\InstallerLeads\InstallerLeadTaker::ALREADY_YOURS;
    $phoneHref = 'tel:'.preg_replace('/[^\d+]/', '', (string) $installRequest->customer_phone);
@endphp
<main>
    <header><img src="{{ asset('img/logo-black.png') }}" alt="KOTLOV.BY"></header>

    @if(session('lead_status'))
        <div class="notice" role="status">{{ session('lead_status') }}</div>
    @endif

    <section class="card" aria-labelledby="lead-title">
        <h1 id="lead-title">{{ $kind }}</h1>
        <p class="muted">Заявка №{{ $installRequest->id }} от {{ $installRequest->created_at?->timezone(config('app.display_timezone', 'Europe/Minsk'))->format('d.m.Y H:i') }}</p>

        <dl>
            @if($installRequest->city)<dt>Город</dt><dd>{{ $installRequest->city }}</dd>@endif
            @if($installRequest->region)<dt>Регион</dt><dd>{{ $installRequest->region }}</dd>@endif
            @if($installRequest->product)<dt>Оборудование</dt><dd>{{ $installRequest->product->name }}</dd>@endif
            @if($installRequest->preferred_date)<dt>Желаемая дата</dt><dd>{{ $installRequest->preferred_date->format('d.m.Y') }}</dd>@endif
            @if($installRequest->budget)<dt>Бюджет</dt><dd>{{ number_format((float) $installRequest->budget, 0, ',', ' ') }} BYN</dd>@endif
            @if(! empty($details['property_area']))<dt>Площадь</dt><dd>{{ $details['property_area'] }} м²</dd>@endif
        </dl>

        @if(trim($description) !== '')
            <h2>Что нужно сделать</h2>
            <p class="desc">{{ $description }}</p>
        @endif

        @if($mine)
            @if($lead->taken_at)
                <p class="muted" style="margin:0">Вы взяли заявку {{ $lead->taken_at->timezone(config('app.display_timezone', 'Europe/Minsk'))->format('d.m.Y H:i') }}.</p>
            @endif
        @elseif($state === 'open')
            <form method="post" action="{{ $formUrl }}">
                @csrf
                <input type="hidden" name="action" value="take">
                <button type="submit">Взять заявку</button>
            </form>
            <p class="slots">Контакты клиента получат первые {{ $maxTakers }} монтажника. Уже взяли: {{ $takenCount }} из {{ $maxTakers }}.</p>
        @elseif($state === \App\Services\InstallerLeads\InstallerLeadTaker::FULL)
            <p class="closed">Эту заявку уже взяли {{ $maxTakers }} монтажника. Следующие заявки будут приходить на почту.</p>
        @else
            <p class="closed">Заявка уже закрыта.</p>
        @endif
    </section>

    @if($mine)
        <section class="card contacts" aria-labelledby="contacts-title">
            <h2 id="contacts-title">Контакты клиента</h2>
            <dl>
                <dt>Имя</dt><dd>{{ $installRequest->customer_name }}</dd>
                <dt>Телефон</dt><dd><a href="{{ $phoneHref }}">{{ $installRequest->customer_phone }}</a></dd>
                @if($installRequest->customer_email)<dt>E-mail</dt><dd><a href="mailto:{{ $installRequest->customer_email }}">{{ $installRequest->customer_email }}</a></dd>@endif
                @if($installRequest->address)<dt>Адрес</dt><dd>{{ $installRequest->address }}</dd>@endif
            </dl>
            <p class="muted" style="margin:0">Клиент оставил заявку на KOTLOV.BY. Позвоните ему в течение дня и скажите, что вы с KOTLOV.BY. Заявку получили не больше {{ $maxTakers }} монтажников.</p>
        </section>
    @endif

    <form method="post" action="{{ $formUrl }}" class="footer">
        @csrf
        @if($subscribed)
            <input type="hidden" name="action" value="unsubscribe">
            <button type="submit" class="link-button">Не присылать мне заявки на монтаж</button>
        @else
            <input type="hidden" name="action" value="subscribe">
            <button type="submit" class="link-button">Снова получать заявки на монтаж</button>
        @endif
    </form>
</main>
</body>
</html>

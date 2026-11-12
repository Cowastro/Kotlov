<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Отзыв о заказе — KOTLOV</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f6f8;color:#18212a;font:16px/1.5 Arial,sans-serif}main{max-width:640px;margin:36px auto;padding:0 18px 40px}header{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;gap:16px}header img{width:155px;height:auto}header span{font-size:13px;color:#697480}.card{background:#fff;border:1px solid #e1e5e9;border-radius:20px;padding:28px}h1{font-size:28px;line-height:1.2;margin:0 0 10px;letter-spacing:-.6px}p{margin:0 0 22px;color:#65717d}.order{font-size:13px;color:#697480;margin-bottom:20px}fieldset{border:0;padding:0;margin:0 0 24px;min-width:0}legend,.label{font-weight:600;margin-bottom:10px;display:block}.targets{display:grid;gap:8px}.target{display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #e1e5e9;border-radius:12px;cursor:pointer;min-width:0}.target:has(input:checked){border-color:#18212a;background:#f7f8fa}.target:has(input:disabled){opacity:.65;cursor:default}.target img{width:48px;height:48px;object-fit:contain;flex-shrink:0}.target span{min-width:0;font-size:14px;overflow-wrap:anywhere}.target small{display:block;color:#298450;font-size:12px}.target input{accent-color:#de4147;flex-shrink:0;width:18px;height:18px}.stars{display:flex;gap:8px}.star{flex:1;position:relative;cursor:pointer;text-align:center}.star input{position:absolute;opacity:0}.star span{display:block;border:1px solid #dce1e6;border-radius:12px;padding:7px 2px;color:#8c949d;font-size:29px}.star small{font-size:12px;display:block;margin-top:-4px}.star input:checked+span{color:#aa6810;border-color:#aa6810;background:#fff8e8}.star input:focus-visible+span{outline:3px solid #de4147;outline-offset:2px}input[type=text],textarea{width:100%;border:1px solid #cfd6dd;border-radius:12px;padding:12px;font:inherit;background:#fff;color:inherit}textarea{resize:vertical;min-height:135px}input:focus,textarea:focus{outline:2px solid #18212a;outline-offset:1px}.field{margin-bottom:22px}.hint{font-size:13px;color:#697480;margin-top:6px}.consent{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#65717d;margin:0 0 22px}.consent input{accent-color:#de4147;margin-top:4px;width:17px;height:17px;flex-shrink:0}button{width:100%;border:0;background:#de4147;color:#fff;border-radius:12px;font:600 16px/1.3 Arial,sans-serif;padding:15px;cursor:pointer}button:hover{background:#c9353c}.notice{background:#ecf8f0;border:1px solid #bde1ca;color:#21653b;padding:14px;border-radius:12px;margin-bottom:22px}.errors{background:#fff0f0;border:1px solid #efc3c3;color:#922a2a;padding:14px;border-radius:12px;margin-bottom:20px}.errors ul{margin:0;padding-left:20px}.footer{text-align:center;font-size:13px;color:#697480;margin-top:20px}a{color:inherit}a:hover{color:#de4147}@media(max-width:480px){main{margin-top:20px;padding-inline:14px}.card{padding:22px 18px;border-radius:16px}h1{font-size:25px}header img{width:140px}.stars{gap:6px}}
        header{justify-content:center}.thank-you{text-align:center;padding-top:36px;padding-bottom:32px}.success-icon{width:72px;height:72px;margin:0 auto 24px;background:#e8f7ee;color:#208750;border-radius:50%;display:grid;place-items:center}.success-icon svg{width:38px;height:38px}.thank-you h1{font-size:28px;line-height:1.25;margin-bottom:16px}.thank-you p{margin-bottom:28px}.catalog-link{display:flex;align-items:center;justify-content:center;min-height:48px;padding:13px 20px;background:#de4147;color:#fff;border-radius:12px;font-weight:600;text-decoration:none}.catalog-link:hover{background:#c9353c;color:#fff}.catalog-link:focus-visible{outline:3px solid #18212a;outline-offset:3px}@media(max-width:480px){.thank-you h1{font-size:26px}.thank-you{padding-top:30px;padding-bottom:26px}}
    </style>
</head>
<body>
@php
    $complete = $submitted->isNotEmpty();
    $selected = old('target', 'order');
@endphp
<main>
    <header><img src="{{ asset('img/logo-black.png') }}" alt="KOTLOV.BY"></header>
    @if($complete)
    <section class="card thank-you" aria-labelledby="review-thanks-title">
        <div class="success-icon" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="m8 16 5 5 11-11" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <h1 id="review-thanks-title">Спасибо!<br>Ваш отзыв отправлен</h1>
        <p>Мы получили ваш отзыв. После проверки он появится на сайте.</p>
        <a class="catalog-link" href="/catalog">Перейти в каталог</a>
    </section>
    @else
    <section class="card">
        <div class="order">Заказ {{ $orderNumber }}</div>
        <h1>Как прошёл ваш заказ?</h1>
        <p>Расскажите об обслуживании или о купленном товаре. Это поможет другим покупателям сделать выбор.</p>
        @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <form method="post" action="{{ $formUrl }}">
            @csrf
            <fieldset>
                <legend>О чём хотите написать?</legend>
                <div class="targets">
                    <label class="target"><input type="radio" name="target" value="order" @checked($selected === 'order')><span>Обслуживание и заказ в целом</span></label>
                    @foreach($products as $item)
                    <label class="target">
                        <input type="radio" name="target" value="{{ $item->product_id }}" @checked((string) $selected === (string) $item->product_id)>
                        <img src="{{ $item->product->imageUrl(0) }}" alt="" loading="lazy">
                        <span>{{ $item->product_name ?: $item->product->name }}</span>
                    </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset><legend>Ваша оценка</legend><div class="stars">
                @foreach([1 => 'Плохо', 2 => 'Не понравилось', 3 => 'Нормально', 4 => 'Хорошо', 5 => 'Отлично'] as $rating => $label)
                <label class="star" title="{{ $label }}"><input type="radio" name="rating" value="{{ $rating }}" required aria-label="{{ $rating }} из 5 — {{ $label }}" @checked((int) old('rating') === $rating)><span aria-hidden="true">★<small>{{ $rating }}</small></span></label>
                @endforeach
            </div></fieldset>
            <div class="field"><label for="author_name" class="label">Как вас подписать?</label><input id="author_name" type="text" name="author_name" value="{{ old('author_name') }}" maxlength="100" placeholder="Ваше имя" autocomplete="given-name" required><div class="hint">Имя будет видно рядом с отзывом. Телефон и адрес не публикуются.</div></div>
            <div class="field"><label for="text" class="label">Ваш отзыв</label><textarea id="text" name="text" minlength="10" maxlength="2000" placeholder="Что понравилось? Что нам стоит улучшить?" required>{{ old('text') }}</textarea></div>
            <label class="consent"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))><span>Согласен на публикацию имени и текста отзыва на KOTLOV.BY и <a href="/privacy" target="_blank" rel="noopener noreferrer">обработку этих данных</a>.</span></label>
            <button type="submit">Отправить отзыв</button>
            <div class="hint">Оценка может быть любой. Отзыв поступит на модерацию.</div>
        </form>
    </section>
    @endif
    @unless($complete)<div class="footer">Отзывы публикуются после модерации · <a href="/">KOTLOV.BY</a></div>@endunless
</main>
</body>
</html>

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
    </style>
</head>
<body>
@php
    $orderReviewed = $submitted->contains(fn ($review) => $review->reviewable_type === (new \App\Models\Order)->getMorphClass() && (int) $review->reviewable_id === (int) $orderId);
    $productType = (new \App\Models\Product)->getMorphClass();
    $productReviewed = fn ($id) => $submitted->contains(fn ($review) => $review->reviewable_type === $productType && (int) $review->reviewable_id === (int) $id);
    $availableProducts = $products->reject(fn ($item) => $productReviewed($item->product_id));
    $complete = $orderReviewed && $availableProducts->isEmpty();
    $selected = old('target', ! $orderReviewed ? 'order' : (string) $availableProducts->first()?->product_id);
@endphp
<main>
    <header><img src="{{ asset('img/logo-black.png') }}" alt="KOTLOV.BY"><span>Ваше мнение важно</span></header>
    <section class="card">
        <div class="order">Заказ {{ $orderNumber }}</div>
        <h1>{{ $complete ? 'Спасибо за ваши отзывы!' : 'Как прошёл ваш заказ?' }}</h1>
        <p>{{ $complete ? 'Все отзывы получены. Мы проверим их перед публикацией.' : 'Расскажите об обслуживании или о купленном товаре. Это поможет другим покупателям сделать выбор.' }}</p>
        @if(session('review_status'))<div class="notice" role="status">{{ session('review_status') }}</div>@endif
        @if($errors->any())<div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @unless($complete)
        <form method="post" action="{{ $formUrl }}">
            @csrf
            <fieldset>
                <legend>О чём хотите написать?</legend>
                <div class="targets">
                    <label class="target"><input type="radio" name="target" value="order" @checked($selected === 'order') @disabled($orderReviewed)><span>Обслуживание и заказ в целом @if($orderReviewed)<small>Отзыв уже получен</small>@endif</span></label>
                    @foreach($products as $item)
                    <label class="target">
                        <input type="radio" name="target" value="{{ $item->product_id }}" @checked((string) $selected === (string) $item->product_id) @disabled($productReviewed($item->product_id))>
                        <img src="{{ $item->product->imageUrl(0) }}" alt="" loading="lazy">
                        <span>{{ $item->product_name ?: $item->product->name }} @if($productReviewed($item->product_id))<small>Отзыв уже получен</small>@endif</span>
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
            <div class="hint">Можно оставить отзыв об обслуживании и отдельно о каждом товаре. Оценка может быть любой.</div>
        </form>
        @endunless
    </section>
    <div class="footer">Отзывы публикуются после модерации · <a href="/">KOTLOV.BY</a></div>
</main>
</body>
</html>

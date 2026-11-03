@extends('layouts.amerce')

@section('title', 'Анкета партнёра KOTLOV')

@php
    $value = fn (string $key, $default = '') => old($key, $data[$key] ?? $default);
    $points = old('points', $data['points'] ?? []);
    $points = collect($points)->map(fn ($point) => array_merge($point, [
        'phones' => is_array($point['phones'] ?? null) ? implode(', ', $point['phones']) : ($point['phones'] ?? ''),
    ]))->all();
    if ($points === []) {
        $points = [['city_id' => '', 'name' => '', 'address' => '', 'phones' => '', 'working_hours' => '']];
    }
    $locked = ! $onboarding->isEditableByPartner();
@endphp

@push('styles')
<style>
    .po-page { padding:56px 0 90px; background:var(--bg-2); }
    .po-kicker { display:inline-flex; align-items:center; gap:10px; color:var(--primary); font-size:13px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
    .po-kicker::before { content:""; width:28px; height:2px; background:currentColor; }
    .po-page h1 { margin:14px 0 12px; font-size:clamp(34px,4.4vw,56px); line-height:1.02; letter-spacing:-.045em; }
    .po-lead { max-width:680px; color:var(--text-2); font-size:18px; line-height:1.55; }
    .po-card { margin-top:28px; padding:clamp(22px,4vw,40px); border-radius:20px; background:var(--white); box-shadow:0 22px 60px rgba(35,44,48,.08); }
    .po-card h2 { margin:0 0 18px; font-size:22px; }
    .po-card h2 + p { margin:-10px 0 18px; color:var(--text-2); }
    .po-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; }
    .po-grid .wide { grid-column:1/-1; }
    .po-field label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; }
    .po-field input, .po-field select, .po-field textarea { width:100%; padding:12px 14px; border:1px solid var(--line); border-radius:10px; background:#fff; }
    .po-types { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .po-type { display:flex; gap:12px; padding:16px; border:1px solid var(--line); border-radius:14px; cursor:pointer; }
    .po-type:has(input:checked) { border-color:#1d252c; box-shadow:0 0 0 2px #1d252c inset; }
    .po-type strong { display:block; }
    .po-type small { color:var(--text-2); }
    .po-point { position:relative; margin-bottom:14px; padding:18px; border:1px solid var(--line); border-radius:14px; background:var(--bg-2); }
    .po-point__remove { position:absolute; top:12px; right:12px; border:0; background:transparent; color:var(--text-2); font-size:13px; }
    .po-add { padding:10px 16px; border:1px dashed #9aa3ad; border-radius:999px; background:transparent; font-weight:600; }
    .po-errors { margin:24px 0 0; padding:16px 18px; border-radius:12px; background:#fff0f1; color:#b62635; }
    .po-done { margin-top:28px; padding:28px; border-radius:20px; background:#e8f6ef; color:#0b6b48; font-size:17px; }
    @media (max-width:767px) { .po-grid, .po-types { grid-template-columns:1fr; } .po-page { padding:32px 0 60px; } }
</style>
@endpush

@section('content')
<main id="wrapper" class="po-page">
    <div class="container" style="max-width:920px">
        <span class="po-kicker">Партнёрская сеть KOTLOV</span>
        <h1>Анкета партнёра</h1>
        <p class="po-lead">Расскажите о компании и ваших точках продаж. После проверки менеджер откроет доступ в B2B-кабинет, а ваши точки появятся в блоке «Где купить» на kotlov.by.</p>

        @if(session('success'))
            <div class="po-done" role="status">{{ session('success') }}</div>
        @elseif($onboarding->status === 'approved')
            <div class="po-done">Компания уже подключена к KOTLOV. Войдите в <a href="{{ route('b2b.cabinet') }}" class="link">B2B-кабинет</a>.</div>
        @elseif($onboarding->status === 'rejected')
            <div class="po-errors">Анкета закрыта. Свяжитесь с KOTLOV: <a href="mailto:info@kotlov.by">info@kotlov.by</a>.</div>
        @endif

        @if($errors->any())
            <ul class="po-errors" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        @endif

        @unless($locked)
        <form method="POST" action="{{ route('partner-onboarding.submit', $onboarding->token) }}">
            @csrf
            <section class="po-card">
                <h2>Компания</h2>
                <div class="po-grid">
                    <div class="po-field"><label for="po-company">Название *</label><input id="po-company" name="company_name" required maxlength="255" value="{{ $value('company_name', $onboarding->company_name) }}" placeholder="ООО «Тепло»"></div>
                    <div class="po-field"><label for="po-unp">УНП *</label><input id="po-unp" name="unp" required inputmode="numeric" pattern="\d{9}" maxlength="9" value="{{ $value('unp', $onboarding->unp) }}" placeholder="9 цифр"></div>
                    <div class="po-field wide"><label for="po-legal">Юридический адрес</label><input id="po-legal" name="legal_address" maxlength="500" value="{{ $value('legal_address') }}"></div>
                    <div class="po-field wide">
                        <label>Тип партнёра *</label>
                        <div class="po-types">
                            <label class="po-type"><input type="radio" name="partner_type" value="wholesale" @checked($value('partner_type', 'wholesale') === 'wholesale')><span><strong>Магазин или филиал в городе</strong><small>Продаёте оборудование клиентам своего города</small></span></label>
                            <label class="po-type"><input type="radio" name="partner_type" value="installer" @checked($value('partner_type') === 'installer')><span><strong>Монтажная организация</strong><small>Монтируете системы, нужна страница монтажника</small></span></label>
                        </div>
                    </div>
                    <div class="po-field"><label for="po-contact">Контактное лицо *</label><input id="po-contact" name="contact_name" required maxlength="255" value="{{ $value('contact_name') }}"></div>
                    <div class="po-field"><label for="po-phone">Телефон *</label><input id="po-phone" name="phone" required maxlength="50" value="{{ $value('phone') }}" placeholder="+375 (29) 000-00-00"></div>
                    <div class="po-field"><label for="po-email">E-mail для входа *</label><input id="po-email" type="email" name="email" required maxlength="255" value="{{ $value('email', $onboarding->email) }}"></div>
                    <div class="po-field"><label for="po-site">Сайт</label><input id="po-site" name="website" maxlength="255" value="{{ $value('website') }}" placeholder="example.by"></div>
                    <div class="po-field wide"><label for="po-desc">Коротко о компании</label><textarea id="po-desc" name="description" rows="3" maxlength="2000" placeholder="Что продаёте, какие бренды, монтаж и сервис">{{ $value('description') }}</textarea></div>
                </div>
            </section>

            <section class="po-card">
                <h2>Точки продаж</h2>
                <p>Адреса, где клиенты могут купить оборудование. Телефоны точек увидят покупатели на kotlov.by.</p>
                <div data-po-points>
                    @foreach($points as $index => $point)
                        <div class="po-point" data-po-point>
                            <button type="button" class="po-point__remove" data-po-remove>Удалить</button>
                            <div class="po-grid">
                                <div class="po-field"><label>Город *</label>
                                    <select name="points[{{ $index }}][city_id]">
                                        <option value="">Выберите город</option>
                                        @foreach($cities as $cityId => $cityName)<option value="{{ $cityId }}" @selected((string) ($point['city_id'] ?? '') === (string) $cityId)>{{ $cityName }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="po-field"><label>Название точки</label><input name="points[{{ $index }}][name]" maxlength="255" value="{{ $point['name'] ?? '' }}" placeholder="Например, магазин «Тепло»"></div>
                                <div class="po-field wide"><label>Адрес *</label><input name="points[{{ $index }}][address]" maxlength="255" value="{{ $point['address'] ?? '' }}" placeholder="г. Борисов, ул. Демина, 39"></div>
                                <div class="po-field"><label>Телефоны *</label><input name="points[{{ $index }}][phones]" maxlength="255" value="{{ $point['phones'] ?? '' }}" placeholder="Через запятую"></div>
                                <div class="po-field"><label>Режим работы</label><input name="points[{{ $index }}][working_hours]" maxlength="255" value="{{ $point['working_hours'] ?? '' }}" placeholder="Пн–Пт 9:00–18:00"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="po-add" data-po-add>+ Добавить точку продаж</button>
            </section>

            <div class="po-card d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                <p class="mb-0" style="color:var(--text-2)">Отправляя анкету, вы соглашаетесь на обработку данных компании для размещения на kotlov.by.</p>
                <button type="submit" class="tf-btn animate-btn">{{ $onboarding->status === 'submitted' ? 'Обновить анкету' : 'Отправить анкету' }}</button>
            </div>
        </form>
        @endunless
    </div>
</main>
@endsection

@push('scripts')
<script>
    (function () {
        var list = document.querySelector('[data-po-points]');
        var add = document.querySelector('[data-po-add]');
        if (!list || !add) return;
        add.addEventListener('click', function () {
            var rows = list.querySelectorAll('[data-po-point]');
            if (rows.length >= 20) return;
            var clone = rows[0].cloneNode(true);
            var index = Date.now();
            clone.querySelectorAll('input, select').forEach(function (field) {
                field.name = field.name.replace(/points\[[^\]]+\]/, 'points[' + index + ']');
                field.value = '';
            });
            list.appendChild(clone);
        });
        list.addEventListener('click', function (event) {
            if (!event.target.closest('[data-po-remove]')) return;
            var rows = list.querySelectorAll('[data-po-point]');
            var row = event.target.closest('[data-po-point]');
            if (rows.length > 1) { row.remove(); } else { row.querySelectorAll('input, select').forEach(function (f) { f.value = ''; }); }
        });
    })();
</script>
@endpush

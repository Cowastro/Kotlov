@extends('layouts.amerce')

@section('title', 'Реквизиты ООО «СанБизнесГруп» — KOTLOV.BY')
@section('description', 'Актуальные юридические и банковские реквизиты ООО «СанБизнесГруп» для договоров, счетов и платежей.')

@push('styles')
<style>
    .requisites-page { --rq-ink:#18191c; --rq-muted:#747981; --rq-line:#e2e4e8; --rq-soft:#f5f6f7; padding:48px 0 80px; background:#f7f7f8; }
    .requisites-shell { width:min(980px, calc(100% - 32px)); margin:0 auto; }
    .requisites-breadcrumbs { display:flex; align-items:center; gap:8px; margin-bottom:22px; color:var(--rq-muted); font-size:13px; }
    .requisites-breadcrumbs a { color:inherit; text-decoration:none; }
    .requisites-hero { display:flex; justify-content:space-between; gap:32px; align-items:flex-end; padding:32px; margin-bottom:18px; color:#fff; background:var(--rq-ink); border-radius:20px; }
    .requisites-kicker { display:block; margin-bottom:10px; color:#ffbd00; font-size:12px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; }
    .requisites-hero h1 { max-width:680px; margin:0 0 10px; color:#fff; font-size:clamp(28px, 4vw, 44px); line-height:1.08; }
    .requisites-hero p { max-width:650px; margin:0; color:#c8cbd0; font-size:15px; line-height:1.55; }
    .requisites-actions { display:flex; flex-wrap:wrap; gap:10px; flex:0 0 auto; }
    .requisites-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:0 16px; border:1px solid #ffbd00; border-radius:10px; color:#18191c; background:#ffbd00; font-size:14px; font-weight:700; cursor:pointer; transition:.2s ease; }
    .requisites-button:hover { color:#18191c; background:#ffc928; }
    .requisites-button--ghost { border-color:#575a60; color:#fff; background:transparent; }
    .requisites-button--ghost:hover { border-color:#fff; color:#fff; background:#303238; }
    .requisites-card { padding:10px 28px; background:#fff; border:1px solid var(--rq-line); border-radius:20px; box-shadow:0 10px 30px rgba(16,18,22,.04); }
    .requisites-section { padding:24px 0; border-bottom:1px solid var(--rq-line); }
    .requisites-section:last-child { border-bottom:0; }
    .requisites-section h2 { margin:0 0 16px; font-size:19px; }
    .requisites-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px 32px; }
    .requisites-field { position:relative; min-width:0; padding:13px 48px 13px 0; }
    .requisites-field--wide { grid-column:1 / -1; }
    .requisites-label { display:block; margin-bottom:5px; color:var(--rq-muted); font-size:12px; }
    .requisites-value { display:block; color:var(--rq-ink); font-size:15px; font-weight:600; overflow-wrap:anywhere; }
    .requisites-copy { position:absolute; top:16px; right:0; width:36px; height:36px; padding:0; border:1px solid var(--rq-line); border-radius:9px; color:#5f646c; background:var(--rq-soft); cursor:pointer; font-size:17px; }
    .requisites-copy:hover { color:#111; border-color:#b8bcc3; }
    .requisites-note { display:flex; gap:12px; align-items:flex-start; margin-top:18px; padding:16px 18px; border-radius:13px; color:#5d6269; background:#fff8de; font-size:13px; line-height:1.5; }
    .requisites-note strong { color:#24262a; }
    .requisites-toast { position:fixed; z-index:9999; right:20px; bottom:20px; padding:12px 16px; border-radius:10px; color:#fff; background:#1f8a4c; box-shadow:0 10px 30px rgba(0,0,0,.18); font-size:14px; font-weight:700; opacity:0; transform:translateY(12px); pointer-events:none; transition:.2s ease; }
    .requisites-toast.is-visible { opacity:1; transform:translateY(0); }
    @media (max-width:767px) {
        .requisites-page { padding:26px 0 54px; }
        .requisites-hero { display:block; padding:24px; }
        .requisites-actions { margin-top:22px; }
        .requisites-actions .requisites-button { flex:1 1 150px; }
        .requisites-card { padding:6px 20px; }
        .requisites-grid { grid-template-columns:1fr; gap:4px; }
        .requisites-field--wide { grid-column:auto; }
    }
    @media print {
        @page { size:A4; margin:14mm; }
        header, footer, .requisites-breadcrumbs, .requisites-actions, .requisites-copy, .online-order-promo, .requisites-toast { display:none !important; }
        .requisites-page { padding:0; background:#fff; }
        .requisites-shell { width:100%; }
        .requisites-hero { padding:0 0 18px; color:#000; background:#fff; border-radius:0; border-bottom:2px solid #000; }
        .requisites-hero h1, .requisites-hero p { color:#000; }
        .requisites-kicker { color:#000; }
        .requisites-card { padding:0; border:0; box-shadow:none; }
        .requisites-section { break-inside:avoid; }
        .requisites-note { border:1px solid #bbb; background:#fff; }
    }
</style>
@endpush

@section('content')
@php
    $rows = [
        'Полное наименование' => $company['name'],
        'Краткое наименование' => $company['short_name'],
        'УНП' => $company['unp'],
        'ОКПО' => $company['okpo'],
        'Расчётный счёт (IBAN)' => $company['iban'],
        'Банк' => $company['bank'],
        'BIC / SWIFT' => $company['bic'],
        'Юридический адрес' => $company['legal_address'],
        'Адрес склада' => $company['warehouse_address'],
        'Телефоны' => implode(', ', $company['phones']),
        'Email' => $company['email'],
        'Сайт' => $company['website'],
        'Директор' => $company['director'],
    ];
    $copyText = collect($rows)->map(fn ($value, $label) => $label . ': ' . $value)->implode("\n");
@endphp
<main class="requisites-page">
    <div class="requisites-shell">
        <nav class="requisites-breadcrumbs" aria-label="Хлебные крошки">
            <a href="/">Главная</a><span>›</span><a href="{{ route('partners') }}">Партнёрам</a><span>›</span><span>Реквизиты</span>
        </nav>

        <section class="requisites-hero">
            <div>
                <span class="requisites-kicker">Для партнёров и поставщиков</span>
                <h1>Реквизиты ООО «СанБизнесГруп»</h1>
                <p>Актуальные данные для оформления договора, выставления счёта и проведения платежа.</p>
            </div>
            <div class="requisites-actions">
                <button type="button" class="requisites-button" data-copy-all>Скопировать всё</button>
                <button type="button" class="requisites-button requisites-button--ghost" onclick="window.print()">Распечатать</button>
            </div>
        </section>

        <section class="requisites-card" aria-label="Реквизиты организации">
            <div class="requisites-section">
                <h2>Организация</h2>
                <div class="requisites-grid">
                    @foreach (['Полное наименование', 'Краткое наименование', 'УНП', 'ОКПО', 'Директор'] as $label)
                        <div class="requisites-field {{ $label === 'Полное наименование' ? 'requisites-field--wide' : '' }}">
                            <span class="requisites-label">{{ $label }}</span>
                            <span class="requisites-value">{{ $rows[$label] }}</span>
                            <button type="button" class="requisites-copy" data-copy="{{ $rows[$label] }}" aria-label="Скопировать: {{ $label }}" title="Скопировать">⧉</button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="requisites-section">
                <h2>Банковские реквизиты</h2>
                <div class="requisites-grid">
                    @foreach (['Расчётный счёт (IBAN)', 'Банк', 'BIC / SWIFT'] as $label)
                        <div class="requisites-field {{ $label === 'Банк' ? 'requisites-field--wide' : '' }}">
                            <span class="requisites-label">{{ $label }}</span>
                            <span class="requisites-value">{{ $rows[$label] }}</span>
                            <button type="button" class="requisites-copy" data-copy="{{ $rows[$label] }}" aria-label="Скопировать: {{ $label }}" title="Скопировать">⧉</button>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="requisites-section">
                <h2>Адреса и контакты</h2>
                <div class="requisites-grid">
                    @foreach (['Юридический адрес', 'Адрес склада', 'Телефоны', 'Email', 'Сайт'] as $label)
                        <div class="requisites-field {{ str_contains($label, 'адрес') || $label === 'Адрес склада' ? 'requisites-field--wide' : '' }}">
                            <span class="requisites-label">{{ $label }}</span>
                            <span class="requisites-value">{{ $rows[$label] }}</span>
                            <button type="button" class="requisites-copy" data-copy="{{ $rows[$label] }}" aria-label="Скопировать: {{ $label }}" title="Скопировать">⧉</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="requisites-note">
            <span aria-hidden="true">ⓘ</span>
            <div><strong>Проверьте назначение платежа.</strong> Если для договора или счёта нужны дополнительные документы, напишите на <a href="mailto:{{ $company['email'] }}">{{ $company['email'] }}</a>.</div>
        </div>
    </div>
    <div class="requisites-toast" role="status" aria-live="polite">Скопировано</div>
    <textarea id="requisites-copy-source" hidden>{{ $copyText }}</textarea>
</main>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var toast = document.querySelector('.requisites-toast');
    var timer;

    function showCopied() {
        if (!toast) return;
        toast.classList.add('is-visible');
        clearTimeout(timer);
        timer = setTimeout(function () { toast.classList.remove('is-visible'); }, 1600);
    }

    function copy(value) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(value).then(showCopied);
            return;
        }
        var input = document.createElement('textarea');
        input.value = value;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        input.remove();
        showCopied();
    }

    document.querySelectorAll('[data-copy]').forEach(function (button) {
        button.addEventListener('click', function () { copy(button.dataset.copy); });
    });
    document.querySelector('[data-copy-all]')?.addEventListener('click', function () {
        copy(document.querySelector('#requisites-copy-source').value);
    });
});
</script>
@endpush

@extends('layouts.amerce')

@section('title', 'ТиС «Феррит 1 мм»: Моно и Термо — цены в BYN | KOTLOV')
@section('description', 'Прайс дымоходов ТиС «Феррит 1 мм»: Моно AISI 430 толщиной 1 мм и Термо с керамоволокном для бань и печей. Цены в BYN, поставка 5–7 рабочих дней.')

@push('styles')
<style>
    .tis50{padding:34px 0 76px}.tis50__lead{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:24px;margin-bottom:28px}.tis50__intro,.tis50__delivery{padding:26px;border:1px solid var(--line);border-radius:20px;background:#fff}.tis50__intro h2{margin:0 0 10px;font-size:clamp(24px,2.5vw,34px)}.tis50__intro p,.tis50__delivery p{margin:0;color:var(--text-2);line-height:1.6}.tis50__delivery strong{display:block;margin-bottom:8px;font-size:18px}.tis50__nav{display:flex;gap:10px;overflow-x:auto;margin-bottom:24px;padding-bottom:4px}.tis50__nav a{flex:0 0 auto;padding:11px 16px;border:1px solid #111;border-radius:999px;color:#111;font-weight:650}.tis50__nav a:first-child{background:#111;color:#fff}.tis50__series{scroll-margin-top:100px;margin-top:34px}.tis50__series-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:14px}.tis50__series-head h2{margin:0 0 6px;font-size:clamp(25px,3vw,38px)}.tis50__series-head p{max-width:850px;margin:0;color:var(--text-2)}.tis50__table-wrap{overflow:auto;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 12px 34px rgba(0,0,0,.04)}.tis50__table{width:100%;min-width:1080px;border-collapse:separate;border-spacing:0}.tis50__table th,.tis50__table td{padding:13px 12px;border-right:1px solid #ececec;border-bottom:1px solid #ececec;text-align:center;vertical-align:middle}.tis50__table thead th{position:sticky;top:0;z-index:2;background:#171717;color:#fff;font-size:12px}.tis50__table th:first-child,.tis50__table td:first-child{position:sticky;left:0;z-index:1;width:330px;min-width:330px;text-align:left}.tis50__table thead th:first-child{z-index:3}.tis50__table tbody td:first-child{background:#fff;font-weight:600}.tis50__table tbody tr:hover td{background:#fff9f7}.tis50__price{display:inline-flex;flex-direction:column;gap:2px;color:#111;font-weight:700;white-space:nowrap}.tis50__price small{color:var(--text-2);font-size:10px;font-weight:500}.tis50__empty{color:#bbb}.tis50__note{margin-top:22px;padding:17px 19px;border-left:3px solid #111;background:#f7f7f7;color:var(--text-2);font-size:13px;line-height:1.55}.tis50__actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:24px}.tis50__actions .tf-btn{min-height:48px}@media(max-width:767px){.tis50{padding-top:22px}.tis50__lead{grid-template-columns:1fr}.tis50__intro,.tis50__delivery{padding:20px}.tis50__series-head{align-items:start;flex-direction:column}.tis50__table th:first-child,.tis50__table td:first-child{width:230px;min-width:230px}.tis50__table th,.tis50__table td{padding:11px 9px}.tis50__actions .tf-btn{width:100%;justify-content:center}}
</style>
@endpush

@section('content')
<main id="wrapper">
    <section class="section-page-title text-center flat-spacing-2 pb-0">
        <div class="container">
            <div class="main-page-title">
                <div class="breadcrumbs">
                    <a href="/" class="text-caption-01 cl-text-3 link">Главная</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <a href="/dymohody" class="text-caption-01 cl-text-3 link">Дымоходы</a>
                    <i class="icon icon-CaretRightThin cl-text-3"></i>
                    <p class="text-caption-01">ТиС «Феррит 1 мм»</p>
                </div>
                <h1>Дымоходы ТиС «Феррит 1 мм»</h1>
                <p class="text-body-1 cl-text-2">Моно из стали AISI 430 толщиной 1 мм и утеплённая система Термо с керамоволокном — для банных и отопительных печей. Выберите элемент и диаметр прямо в таблице.</p>
            </div>
        </div>
    </section>

    <section class="tis50">
        <div class="container">
            <div class="tis50__lead">
                <article class="tis50__intro">
                    <h2>Один прайс вместо сотен карточек</h2>
                    <p>Строки — элементы системы, столбцы — диаметры. Цена ведёт в точную карточку товара, где можно сразу переключаться между диаметрами той же модели. Все цены показаны в белорусских рублях.</p>
                </article>
                <aside class="tis50__delivery">
                    <strong>Поставка 5–7 рабочих дней</strong>
                    <p>Элементы доступны под заказ. Перед подтверждением менеджер проверит актуальный срок и совместимость всех деталей одной системы.</p>
                </aside>
            </div>

            <nav class="tis50__nav" aria-label="Разделы прайса">
                <a href="#mono">Моно</a>
                <a href="#thermo">Термо</a>
            </nav>

            @foreach ($series as $key => $group)
                <section class="tis50__series" id="{{ $key }}">
                    <div class="tis50__series-head">
                        <div>
                            <h2>{{ $group['title'] }}</h2>
                            <p>{{ $group['description'] }}</p>
                        </div>
                    </div>

                    <div class="tis50__table-wrap" tabindex="0" aria-label="Прайс {{ $group['title'] }}">
                        <table class="tis50__table">
                            <thead>
                                <tr>
                                    <th>Элемент / диаметр</th>
                                    @foreach ($group['diameters'] as $diameter)
                                        <th>Ø {{ $diameter }} мм</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($group['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        @foreach ($group['diameters'] as $diameter)
                                            @php($product = $row['variants']->get($diameter))
                                            <td>
                                                @if ($product)
                                                    <a class="tis50__price" href="/{{ $product->category->slug }}/{{ $product->slug }}">
                                                        {{ number_format((float) $product->price, 2, ',', ' ') }} BYN
                                                        <small>{{ $product->publicAvailabilityLabel() }}</small>
                                                    </a>
                                                @else
                                                    <span class="tis50__empty">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach

            <div class="tis50__note">
                Цены указаны в BYN и обновлены 27 октября 2026 года. Итоговая комплектация дымохода зависит от высоты, трассы, проходов перекрытий, материала стен и параметров отопительного прибора.
            </div>
            <div class="tis50__actions">
                <a href="/dymohody" class="tf-btn btn-fill">Все дымоходы</a>
                <a href="{{ route('chimneys.tis-ferrit-1mm.csv') }}" class="tf-btn btn-white btn-stroke">Скачать прайс CSV</a>
                <a href="/contacts" class="tf-btn btn-white btn-stroke">Помощь с комплектацией</a>
            </div>
        </div>
    </section>
</main>
@endsection

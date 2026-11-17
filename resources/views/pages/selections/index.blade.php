@extends('layouts.amerce')
@section('title', 'Мои заявки | KOTLOV')
@php $robots = 'noindex, nofollow'; $canUseDesk = \App\Http\Controllers\B2bSelectionDeskController::canUse(auth()->user()); @endphp
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/b2b-pagination.css') }}?v={{ filemtime(public_path('assets/css/b2b-pagination.css')) }}"><link rel="stylesheet" href="{{ asset('assets/css/my-requests.css') }}?v={{ filemtime(public_path('assets/css/my-requests.css')) }}">@endpush
@section('content')
<main class="container py-4 request-hub">
    <nav class="request-actions"><a href="{{ route('account') }}">Личный кабинет</a><a href="{{ route('cart') }}">Текущая корзина</a>@if(\App\Services\StaffCatalogWorkspace::allowed(auth()->user()))<a href="{{ route('b2b.workspace') }}">B2B-каталог →</a>@endif</nav>
    <h1>Мои заявки</h1>
    <form class="request-search" method="get" action="{{ route('selections.index') }}"><label for="request-search">Найти заявку по названию клиента или объекта</label><div><input id="request-search" type="search" name="q" value="{{ $search }}" maxlength="100" placeholder="Например, котельная на Лесной"><button class="tf-btn btn-primary small">Найти</button>@if($search !== '')<a href="{{ route('selections.index') }}">Сбросить</a>@endif</div></form>
    <p class="request-intro">Корзины и подборы для клиентов и объектов в одном месте. Сохранённая потребность и ожидаемые товары не являются резервом.</p>
    @foreach(['success'=>'success','error'=>'danger'] as $key=>$style) @if(session($key))<p role="status" class="alert alert-{{ $style }}">{{ session($key) }}</p>@endif @endforeach
    <div class="request-grid">
        @forelse($selections as $selection)
        @php $allocation = collect(app(\App\Services\SelectionAvailability::class)->annotate(array_values($selection->items), $selection->meta['availability'] ?? []))->pluck('allocation')->filter(); @endphp
        <article class="request-card">
            <small>{{ $selection->updated_at->format('d.m.Y H:i') }}</small>
            <h2><a href="{{ route($canUseDesk ? 'b2b.selections.show' : 'selections.show', $selection) }}">{{ $selection->name }}</a></h2>
            <p>{{ count($selection->items) }} позиций · {{ number_format($selection->total(), 2, ',', ' ') }} BYN</p>
            @if($allocation->isNotEmpty())<p class="request-availability">По последней проверке: доступно {{ $allocation->sum('ready') }} шт. · ожидается {{ $allocation->sum('waiting') }} шт.</p>@endif
            <small>Непроверенных позиций: {{ count($selection->items)-$allocation->count() }}. Цены на момент сохранения.</small>
            <a class="tf-btn btn-stroke small" href="{{ route($canUseDesk ? 'b2b.selections.show' : 'selections.show', $selection) }}">Открыть заявку →</a>
        </article>
        @empty @if($legacyCarts->isEmpty())<p>{{ $search !== '' ? 'По этому названию заявок не найдено.' : 'Сохраните товары из корзины или создайте заявку в B2B-каталоге.' }}</p>@endif @endforelse
        @foreach($legacyCarts as $legacy)
        <article class="request-card">
            <small>Ранее сохранённая корзина · {{ $legacy->updated_at->format('d.m.Y') }}</small><h2>{{ $legacy->name }}</h2>
            <p>{{ count($legacy->items) }} позиций · {{ number_format($legacy->total,2,',',' ') }} BYN на момент сохранения</p>
            <p>Все позиции будут сохранены, включая отсутствующие.</p>
            <form method="post" action="{{ route('requests.import-cart',$legacy) }}">@csrf<button class="tf-btn btn-stroke small">Открыть как заявку →</button></form>
        </article>
        @endforeach
    </div>
    <div class="mt-4">@include('partials.b2b-pagination', ['paginator' => $selections, 'paginationLabel' => 'Заявки'])</div>
    <section class="request-reserves" aria-labelledby="request-reserves-title"><h2 id="request-reserves-title">Резервы по заказам</h2><p>Отдельно от ожидания: здесь только активные резервы, зарегистрированные менеджером в CRM. Резерв у поставщика подтверждается менеджером.</p>
        @forelse($reservations as $reservation)
        <article><strong>{{ $reservation->order->number }} · {{ $reservation->item?->product_name }}</strong><span>{{ (float)$reservation->quantity }} шт. в резерве</span><small>{{ $reservation->warehouse_name }}</small></article>
        @empty <p>Активных резервов нет.</p> @endforelse
        @include('partials.b2b-pagination', ['paginator' => $reservations, 'paginationLabel' => 'Резервы'])
    </section>
</main>
@endsection

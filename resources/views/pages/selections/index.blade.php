@extends('layouts.amerce')
@php $robots = 'noindex, nofollow'; @endphp
@section('content')
<main class="container py-4">
    <nav class="d-flex flex-wrap gap-3 mb-3"><a class="link" href="{{ route('account') }}">Личный кабинет</a><a class="link" href="{{ route('cart') }}">Текущая корзина</a></nav>
    <h1 class="mb-3">Мои подборы</h1>
    <p class="cl-text-2 mb-4">Сохранённые списки товаров для клиентов и объектов. Их можно редактировать, печатать и открывать в корзине для оформления заявки.</p>
    @if (session('success'))<p class="alert alert-success">{{ session('success') }}</p>@endif
    <div class="row g-3">
        @forelse ($selections as $selection)
            <article class="col-md-6 col-xl-4"><div class="border rounded-4 p-3 h-100">
                <p class="text-caption-01 cl-text-2 mb-2">{{ $selection->updated_at->format('d.m.Y H:i') }}</p>
                <h2 class="h5 mb-2"><a class="link" href="{{ route('selections.show', $selection) }}">{{ $selection->name }}</a></h2>
                <p class="mb-3">{{ count($selection->items) }} позиций · {{ number_format($selection->total(), 2, ',', ' ') }} BYN</p>
                <a class="tf-btn btn-stroke small" href="{{ route('selections.show', $selection) }}">Открыть подбор</a>
            </div></article>
        @empty
            <div class="col-12 border rounded-4 p-4"><p>Пока нет сохранённых подборов. Добавьте товары в корзину и нажмите «Сохранить подбор».</p><a class="tf-btn btn-primary small mt-3" href="{{ route('cart') }}">Перейти в корзину</a></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $selections->links() }}</div>
</main>
@endsection

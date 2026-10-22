@extends('layouts.amerce')

@section('content')
<main id="wrapper">
    <section class="flat-spacing text-center">
        <div class="container" style="max-width: 680px;">
            <h3 class="mb-16">{{ $successful ? 'Оплата выполнена' : 'Статус оплаты' }}</h3>
            <p class="text-body-1 cl-text-2 mb-24">{{ $message }}</p>
            <a href="{{ auth()->check() ? route('account') : url('/') }}" class="tf-btn animate-btn">
                {{ auth()->check() ? 'В личный кабинет' : 'На главную' }}
            </a>
        </div>
    </section>
</main>
@endsection

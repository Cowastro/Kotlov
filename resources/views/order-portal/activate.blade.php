@extends('order-portal.layout', ['title' => 'Открыть заказ '.$link->order->number])

@section('content')
<div class="card center">
    <div class="eyebrow">Онлайн-заказ</div>
    <h1>{{ $link->order->number }}</h1>
    <p>Здесь отображается весь заказ: товары, цены, поставщики, остатки и текущие статусы исполнения.</p>
    <p>Для защиты данных заказ откроется только на подтверждённом устройстве.</p>
    @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('order-portal.activate', $token) }}">
        @csrf
        <button class="button" type="submit">{{ $hasDevices ? 'Получить код на email' : 'Открыть заказ' }}</button>
    </form>
    @if($hasDevices)<p class="small">Заказ уже открывали на другом устройстве. Код придёт на {{ preg_replace('/(^.).*(@.*$)/', '$1••••$2', $link->access_email) }}.</p>@endif
</div>
@endsection

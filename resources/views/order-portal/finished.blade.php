@extends('order-portal.layout', ['title' => 'Заказ '.$order->number.' завершён'])

@section('content')
<div class="card center">
    <div class="eyebrow">Заказ завершён</div>
    <h1>{{ $order->number }}</h1>
    <div class="notice">Доставка и оплата подтверждены.</div>
    <p>Действие выполнил: <strong>{{ $actor }}</strong>.</p>
    <p>Заказ закрыт, поэтому общая ссылка больше не действует.</p>
</div>
@endsection

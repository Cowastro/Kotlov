@extends('order-portal.layout', ['title' => 'Заказ недоступен'])

@section('content')
<div class="card center">
    <div class="eyebrow">KOTLOV.BY</div>
    <h1>Заказ недоступен</h1>
    <p>{{ $message }}</p>
    <p>Обратитесь к менеджеру — он проверит доступ и создаст новую ссылку.</p>
</div>
@endsection

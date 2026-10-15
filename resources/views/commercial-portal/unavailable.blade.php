@extends('commercial-portal.layout', ['title' => 'Предложение недоступно'])

@section('content')
<div class="card center">
    <div class="eyebrow">KOTLOV.BY</div>
    <h1>Предложение недоступно</h1>
    <p>{{ $message }}</p>
    <p>Обратитесь к менеджеру — он проверит срок предложения и отправит новую ссылку.</p>
</div>
@endsection

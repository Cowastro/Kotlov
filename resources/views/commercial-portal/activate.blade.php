@extends('commercial-portal.layout', ['title' => 'Открыть предложение '.$link->document->reference()])

@section('content')
<div class="card center">
    <div class="eyebrow">Персональное предложение</div>
    <h1>{{ $link->document->number }}</h1>
    <p>Редакция №{{ $link->document->revision_number ?: 1 }}</p>
    <p>{{ $link->document->client_company ?: ($link->document->client_name ?: $link->document->client_email) }}</p>
    <p>Для защиты цен и данных предложение откроется только на подтверждённом устройстве.</p>
    @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('commercial-portal.activate', $token) }}">
        @csrf
        <button class="button" type="submit">{{ $hasDevices ? 'Получить код на email' : 'Открыть предложение' }}</button>
    </form>
    @if($hasDevices)<p class="small">Предложение уже открывали на другом устройстве. Отправим код на {{ preg_replace('/(^.).*(@.*$)/', '$1••••$2', $link->document->client_email) }}.</p>@endif
</div>
@endsection

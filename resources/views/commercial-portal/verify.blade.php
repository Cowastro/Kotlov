@extends('commercial-portal.layout', ['title' => 'Подтверждение устройства'])

@section('content')
<div class="card center">
    <div class="eyebrow">Новое устройство</div>
    <h1>Введите код из письма</h1>
    <p>Мы отправили шестизначный код на email получателя предложения {{ $challenge->link->document->reference() }}.</p>
    @if($errors->any())<div class="notice error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('commercial-portal.verify', $challenge->public_id) }}">
        @csrf
        <input class="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
        <button class="button" type="submit">Подтвердить устройство</button>
    </form>
</div>
@endsection

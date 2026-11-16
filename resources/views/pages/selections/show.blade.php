@extends('layouts.amerce')
@php $robots = 'noindex, nofollow'; @endphp
@section('content')
<main class="container py-4">
    <nav class="d-flex flex-wrap gap-3 mb-3"><a class="link" href="{{ route('selections.index') }}">← Мои подборы</a><a class="link" href="{{ route('cart') }}">Текущая корзина</a></nav>
    <h1 class="mb-3">{{ $selection->name }}</h1>
    <p class="cl-text-2 mb-3">{{ count($selection->items) }} позиций · сохранено {{ $selection->updated_at->format('d.m.Y H:i') }}. Цены на момент сохранения. При оформлении проверим актуальную цену и доступность.</p>
    @foreach (['success' => 'success', 'error' => 'danger'] as $key => $style)
        @if (session($key))<p role="status" class="alert alert-{{ $style }}">{{ session($key) }}</p>@endif
    @endforeach
    @if ($errors->any())<div role="alert" class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="d-flex flex-wrap gap-2 mb-4">
        <form method="post" action="{{ route('selections.restore', $selection) }}">@csrf<button class="tf-btn btn-primary small">Открыть в корзине / оформить заявку</button></form>
        <a class="tf-btn btn-stroke small" href="{{ route('selections.show', [$selection, 'print' => 1]) }}" target="_blank" rel="noopener">Печать / PDF</a>
    </div>
    <form method="post" action="{{ route('selections.update', $selection) }}">
        @csrf @method('PUT')
        <input type="hidden" name="revision" value="{{ $selection->revision }}">
        <label for="selection-title" class="fw-semibold">Название подбора</label>
        <input id="selection-title" name="name" value="{{ old('name', $selection->name) }}" required maxlength="160" class="form-control mb-3">
        <div class="table-responsive border rounded-4 mb-3">
            <table class="table align-middle mb-0"><thead><tr><th>Товар / артикул</th><th class="text-end">Цена, BYN</th><th style="min-width:110px">Количество</th><th class="text-end">Сумма, BYN</th></tr></thead>
                <tbody>@foreach ($selection->items as $id => $item)<tr>
                    <td><span class="fw-semibold">{{ $item['name'] }}</span><br><small class="cl-text-2">{{ $item['sku'] ?? '' }}</small></td>
                    <td class="text-end text-nowrap">{{ number_format((float) $item['price'], 2, ',', ' ') }}</td>
                    <td><input aria-label="Количество: {{ $item['name'] }}" type="number" min="0" max="999" name="quantities[{{ $id }}]" value="{{ old('quantities.'.$id, $item['quantity']) }}" class="form-control" style="min-width:80px"></td>
                    <td class="text-end text-nowrap">{{ number_format($item['price'] * $item['quantity'], 2, ',', ' ') }}</td>
                </tr>@endforeach</tbody>
                <tfoot><tr><th colspan="3">Итого</th><th class="text-end text-nowrap">{{ number_format($selection->total(), 2, ',', ' ') }}</th></tr></tfoot>
            </table>
        </div>
        <p class="text-caption-01 cl-text-2 mb-3">Для удаления позиции укажите количество 0. Суммы обновятся после сохранения. Чтобы добавить товары, откройте подбор в корзине, дополните его из каталога и нажмите «Обновить этот подбор».</p>
        <label for="selection-note" class="fw-semibold">Комментарий к подбору</label>
        <textarea id="selection-note" name="note" maxlength="1000" rows="3" class="form-control mb-3">{{ old('note', $selection->note) }}</textarea>
        <button class="tf-btn btn-primary" type="submit">Сохранить изменения</button>
    </form>
</main>
@endsection

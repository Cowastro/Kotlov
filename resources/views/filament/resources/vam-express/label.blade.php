<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Этикетка ВАМ Экспресс · {{ $shipment->display_id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 24px auto; padding: 0 16px; max-width: 1000px; font: 15px/1.5 system-ui, sans-serif; color: #17212b; background: #f5f7f9; }
        h1 { margin: 0 0 8px; font-size: 22px; line-height: 1.3; }
        p { margin: 8px 0; }
        .toolbar { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin: 16px 0; }
        .toolbar a, .toolbar button { display: inline-block; padding: 10px 16px; border: 1px solid #d6dce1; border-radius: 8px; background: #fff; color: #17212b; text-decoration: none; font: inherit; cursor: pointer; }
        .toolbar button { background: #17212b; color: white; border-color: #17212b; }
        .info { color: #52606c; }
        .preview { overflow-x: auto; padding: 12px 0; }
        .sheet { width: {{ $format === 'a4' ? '210mm' : '74mm' }}; margin: 0 auto; background: white; color: black; box-shadow: 0 1px 8px #0002; }
        @page { @if($format === 'a4') size: A4; margin: 0; @else margin: 2mm; @endif }
        @media print {
            body { margin: 0; padding: 0; max-width: none; background: white; }
            .screen-only { display: none !important; }
            .preview { padding: 0; overflow: visible; }
            .sheet { margin: 0; box-shadow: none; }
            .sheet > div:last-child { page-break-after: auto !important; }
        }
    </style>
</head>
<body>
    <header class="screen-only">
        <h1>Этикетка ВАМ Экспресс · {{ $shipment->display_id }}</h1>
        <p>{{ \App\Services\Shipping\VamExpressLabels::FORMATS[$format] }}@if($shipment->order) · Заказ {{ $shipment->order->number }}@endif</p>
        <div class="toolbar">
            @if($label)<button type="button" id="print-label">Печать / сохранить PDF</button>@endif
            <a href="{{ route('admin.vam-express.label', ['shipment' => $shipment->id, 'format' => $format === 'a4' ? '78' : 'a4']) }}">{{ $format === 'a4' ? 'Лента 78 мм' : 'Формат А4' }}</a>
            <a href="{{ \App\Filament\Resources\VamExpress\VamExpressShipmentResource::getUrl('index', panel: 'admin') }}">К заявкам</a>
        </div>
        @if($message)
            <p role="alert">{{ $message }}</p>
            <div class="toolbar"><a href="{{ request()->url() }}">Попробовать ещё раз</a></div>
        @else
            <p class="info">Этикетка получена из кабинета перевозчика. Масштаб печати — 100%, без колонтитулов браузера.
                @if($format === '78')Выберите в настройках принтера бумагу шириной 78 мм.@endif
            </p>
        @endif
    </header>
    @if($label)
        <main class="preview"><div class="sheet">{!! $label['html'] !!}</div></main>
        <script nonce="{{ $nonce }}">document.getElementById('print-label').addEventListener('click', function () { window.print(); });</script>
    @endif
</body>
</html>

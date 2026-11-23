<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Этикетки ВАМ Экспресс · {{ $order->number }}</title>
    <style>
        body { margin: 24px auto; padding: 0 16px; max-width: 760px; font: 16px/1.5 system-ui, sans-serif; color: #17212b; background: #f5f7f9; }
        h1 { font-size: 24px; }
        article { background: white; border: 1px solid #d6dce1; border-radius: 10px; padding: 16px; margin: 16px 0; }
        nav { display: flex; gap: 10px; flex-wrap: wrap; }
        nav a { padding: 8px 14px; background: white; border: 1px solid #d6dce1; border-radius: 8px; color: #17212b; text-decoration: none; }
    </style>
</head>
<body>
    <h1>Этикетки ВАМ Экспресс</h1>
    <p>Заказ {{ $order->number }}</p>
    @forelse($shipments as $shipment)
        <article>
            <strong>ID {{ $shipment->display_id }}</strong>
            <p>{{ $shipment->city }} · {{ $shipment->carrier_status }}</p>
            <nav>
                @foreach(\App\Services\Shipping\VamExpressLabels::FORMATS as $format => $name)
                    <a href="{{ route('admin.vam-express.label', ['shipment' => $shipment->id, 'format' => $format]) }}">Печать · {{ $name }}</a>
                @endforeach
            </nav>
        </article>
    @empty
        <p>К заказу ещё не привязана заявка ВАМ Экспресс. Найдите существующую заявку в разделе доставки и нажмите «Привязать заказ».</p>
    @endforelse
    <nav>
        <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $order], panel: 'admin') }}">Вернуться в заказ</a>
        <a href="{{ \App\Filament\Resources\VamExpress\VamExpressShipmentResource::getUrl('index', panel: 'admin') }}">Заявки ВАМ Экспресс</a>
    </nav>
</body>
</html>

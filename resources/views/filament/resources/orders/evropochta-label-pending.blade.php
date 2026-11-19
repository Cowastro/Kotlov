<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ярлык Европочты</title>
    <style>
        body { margin: 2rem auto; padding: 0 1rem; max-width: 38rem; font: 1rem/1.6 system-ui, sans-serif; }
        h1 { font-size: 1.5rem; }
        nav { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <main>
        <h1>Ярлык Европочты · {{ $format }}</h1>
        <p>Трек-номер сохранён в заказе. <strong>{{ $number }}</strong></p>
        <p>{{ $message }}</p>
        <nav>
            <a href="{{ $retryUrl }}">Открыть ярлык ещё раз</a>
            <a href="{{ $orderUrl }}">Вернуться в заказ</a>
        </nav>
    </main>
</body>
</html>

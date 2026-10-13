<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $name }}</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #09090b; color: #e4e4e7; }
        header { position: sticky; top: 0; z-index: 2; display: flex; gap: 16px; align-items: center; justify-content: space-between; padding: 14px 22px; border-bottom: 1px solid #3f3f46; background: rgb(24 24 27 / .96); }
        h1 { min-width: 0; margin: 0; overflow: hidden; font-size: 16px; text-overflow: ellipsis; white-space: nowrap; }
        .actions { display: flex; flex: none; gap: 8px; }
        a, button { border: 1px solid #52525b; border-radius: 8px; padding: 8px 12px; background: #27272a; color: #f4f4f5; font: inherit; text-decoration: none; cursor: pointer; }
        a.download { border-color: #2563eb; background: #2563eb; }
        main { width: min(100% - 32px, 1050px); margin: 24px auto; padding: 34px 42px; border: 1px solid #3f3f46; border-radius: 12px; background: #18181b; box-shadow: 0 18px 50px rgb(0 0 0 / .3); font-family: Arial, sans-serif; font-size: 16px; line-height: 1.55; }
        p { margin: 0 0 12px; white-space: pre-wrap; }
        .spacer { height: 12px; }
        table { width: 100%; margin: 18px 0; border-collapse: collapse; }
        td { border: 1px solid #71717a; padding: 8px 10px; vertical-align: top; }
        pre { margin: 0; overflow: auto; white-space: pre-wrap; word-break: break-word; }
        @media (max-width: 700px) { header { align-items: stretch; flex-direction: column; } .actions { justify-content: flex-end; } main { width: 100%; margin: 0; padding: 22px 16px; border: 0; border-radius: 0; } }
    </style>
</head>
<body>
    <header>
        <h1>Просмотр: {{ $name }}</h1>
        <div class="actions">
            <button type="button" onclick="window.close()">Закрыть</button>
            <a class="download" href="{{ $downloadUrl }}" download="{{ $name }}">Скачать оригинал</a>
        </div>
    </header>
    <main>{!! $content !!}</main>
</body>
</html>

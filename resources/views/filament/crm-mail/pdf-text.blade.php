<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Текст PDF: {{ $name }}</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; background: #09090b; color: #f4f4f5; }
        main { width: min(1180px, calc(100% - 32px)); margin: 28px auto; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 16px; align-items: center; }
        h1 { margin: 0; font-size: 22px; }
        .meta { margin: 8px 0 18px; color: #a1a1aa; font-size: 13px; }
        .actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .button { border: 1px solid #52525b; border-radius: 8px; padding: 9px 13px; background: #27272a; color: #f4f4f5; text-decoration: none; cursor: pointer; }
        .button.primary { border-color: #ea580c; background: #c2410c; }
        textarea { box-sizing: border-box; width: 100%; min-height: calc(100vh - 180px); border: 1px solid #3f3f46; border-radius: 12px; padding: 18px; resize: vertical; background: #18181b; color: #f4f4f5; font: 15px/1.6 ui-monospace, SFMono-Regular, Consolas, monospace; }
        .error { border: 1px solid #7f1d1d; border-radius: 10px; padding: 14px; background: #450a0a; color: #fecaca; }
    </style>
</head>
<body>
<main>
    <div class="header">
        <div>
            <h1>Распознанный текст: {{ $name }}</h1>
            @if (! $error)
                <div class="meta">{{ $method === 'openai-ocr' ? 'Скан распознан с помощью ИИ' : 'Использован текстовый слой PDF' }}</div>
            @endif
        </div>
        <div class="actions">
            <a class="button" href="{{ $threadUrl }}">Вернуться к письму</a>
            <a class="button" href="{{ $downloadUrl }}">Скачать оригинал</a>
            @if (! $error)
                <button class="button primary" id="copy-text" type="button">Копировать текст</button>
            @endif
        </div>
    </div>

    @if ($error)
        <div class="error">{{ $error }}</div>
    @else
        <textarea id="pdf-text" readonly>{{ $text }}</textarea>
    @endif
</main>
<script>
    document.getElementById('copy-text')?.addEventListener('click', async function () {
        await navigator.clipboard.writeText(document.getElementById('pdf-text').value);
        this.textContent = 'Скопировано';
    });
</script>
</body>
</html>

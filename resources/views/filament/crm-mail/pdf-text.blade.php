<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
        .status { border-color: #92400e; background: #431407; color: #fed7aa; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main>
    <div class="header">
        <div>
            <h1>Распознанный текст: {{ $name }}</h1>
            @if (! $error)
                <div class="meta">{{ $method === 'deepseek-ocr' ? 'Скан распознан с помощью DeepSeek' : 'Использован текстовый слой PDF' }}</div>
            @endif
        </div>
        <div class="actions">
            <a class="button" href="{{ $threadUrl }}">Вернуться к письму</a>
            <a class="button" href="{{ $downloadUrl }}">Скачать оригинал</a>
            <button class="button primary" id="copy-text" type="button" @if ($error) hidden @endif>Копировать текст</button>
        </div>
    </div>

    @if ($error)
        <div class="error {{ $browserFallback ? 'status' : '' }}" id="recognition-status">
            {{ $browserFallback ? 'Подготавливаю страницы PDF в браузере и отправляю их в DeepSeek…' : $error }}
        </div>
        <textarea id="pdf-text" readonly hidden></textarea>
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
@if ($browserFallback)
<script type="module">
    import * as pdfjsLib from '/js/pdfjs/pdf.min.js';

    pdfjsLib.GlobalWorkerOptions.workerSrc = '/js/pdfjs/pdf.worker.min.js';

    const status = document.getElementById('recognition-status');
    const textarea = document.getElementById('pdf-text');
    const copyButton = document.getElementById('copy-text');

    try {
        const pdfResponse = await fetch(@json($downloadUrl), { credentials: 'same-origin' });
        if (!pdfResponse.ok) throw new Error('Не удалось загрузить PDF из письма.');

        const pdf = await pdfjsLib.getDocument({ data: await pdfResponse.arrayBuffer() }).promise;
        const images = [];
        const pageCount = Math.min(pdf.numPages, 8);

        for (let pageNumber = 1; pageNumber <= pageCount; pageNumber++) {
            status.textContent = `Подготавливаю страницу ${pageNumber} из ${pageCount}…`;
            const page = await pdf.getPage(pageNumber);
            const baseViewport = page.getViewport({ scale: 1 });
            const scale = Math.min(2, 1400 / baseViewport.width);
            const viewport = page.getViewport({ scale });
            const canvas = document.createElement('canvas');
            canvas.width = Math.ceil(viewport.width);
            canvas.height = Math.ceil(viewport.height);
            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
            images.push(canvas.toDataURL('image/jpeg', 0.78));
        }

        status.textContent = 'DeepSeek распознаёт документ…';
        const response = await fetch(@json($recognizeUrl), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ images }),
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'DeepSeek не смог распознать документ.');

        textarea.value = result.text;
        textarea.hidden = false;
        copyButton.hidden = false;
        status.remove();
    } catch (error) {
        status.classList.remove('status');
        status.textContent = error instanceof Error ? error.message : 'Не удалось распознать PDF.';
    }
</script>
@endif
</body>
</html>

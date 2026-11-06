@php
    $encodedUrl = rawurlencode($canonical);
    $encodedTitle = rawurlencode($post->title);
    $encodedMessage = rawurlencode($post->title.' '.$canonical);
@endphp

<section class="article-share" aria-labelledby="article-share-title" data-article-share data-url="{{ $canonical }}">
    <div class="article-share__heading">
        <h2 id="article-share-title">Поделиться статьёй</h2>
        <p>Отправьте ссылку или сохраните её на потом.</p>
    </div>

    <div class="article-share__actions">
        <a class="article-share__action article-share__action--telegram" href="https://t.me/share/url?url={{ $encodedUrl }}&amp;text={{ $encodedTitle }}" target="_blank" rel="noopener noreferrer">
            <span class="article-share__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m21.4 3.5-3.2 16c-.2 1.1-.9 1.4-1.8.9l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.2-8.3c.4-.4-.1-.6-.6-.3L5.7 13.2.8 11.7c-1.1-.3-1.1-1.1.2-1.6L20 2.8c.9-.3 1.6.2 1.4.7Z"/></svg></span>
            Telegram
        </a>
        <a class="article-share__action article-share__action--facebook" href="https://www.facebook.com/sharer/sharer.php?u={{ $encodedUrl }}" target="_blank" rel="noopener noreferrer">
            <span class="article-share__icon icon icon-FacebookLogo" aria-hidden="true"></span>
            Facebook
        </a>
        <button type="button" class="article-share__action article-share__action--instagram" aria-expanded="false" aria-controls="article-share-instagram" data-share-instagram>
            <span class="article-share__icon icon icon-InstagramLogo" aria-hidden="true"></span>
            Instagram
        </button>
        <a class="article-share__action article-share__action--whatsapp" href="https://wa.me/?text={{ $encodedMessage }}" target="_blank" rel="noopener noreferrer">
            <span class="article-share__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 0 1-12 7l-5 2 1.5-5A8 8 0 1 1 20 12Z"/><path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Z"/></svg></span>
            WhatsApp
        </a>
        <a class="article-share__action article-share__action--viber" href="viber://forward?text={{ $encodedMessage }}">
            <span class="article-share__icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h10a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4h-6l-5 3v-3a4 4 0 0 1-3-4V7a4 4 0 0 1 4-4Z"/><path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Z"/><path d="M13 6a5 5 0 0 1 5 5m-5-2a2 2 0 0 1 2 2"/></svg></span>
            Viber
        </a>
        <button type="button" class="article-share__action" data-share-copy>
            <span class="article-share__icon icon icon-Files" aria-hidden="true"></span>
            Копировать ссылку
        </button>
    </div>

    <div class="article-share__help" id="article-share-instagram" hidden>
        <p><strong>Поделиться в Instagram</strong></p>
        <p>Скопируйте ссылку и вставьте её в сообщение или добавьте в историю через стикер «Ссылка».</p>
        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer">Открыть Instagram ↗</a>
    </div>

    <p class="article-share__status" role="status" data-share-status></p>
    <label class="article-share__manual" data-share-manual hidden>
        Ссылка на статью
        <input type="url" readonly value="{{ $canonical }}">
    </label>
</section>

@once
    @push('styles')
        <style>
            .article-share {
                margin: 28px 0;
                padding: 22px 24px;
                border: 1px solid #e6e8eb;
                border-radius: 16px;
                background: #f7f8f9;
                color: #17212a;
            }

            .article-share .article-share__heading h2 {
                margin: 0 0 5px;
                font-size: 21px;
                line-height: 1.3;
                font-weight: 700;
            }

            .article-share .article-share__heading p,
            .article-share .article-share__help p {
                margin: 0;
                color: #4d5965;
                font-size: 14px;
                line-height: 1.5;
            }

            .article-share__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-top: 15px;
            }

            .article-share .article-share__action {
                --share-color: #334155;
                --share-tint: #edf0f3;
                display: inline-flex;
                align-items: center;
                gap: 7px;
                min-height: 44px;
                padding: 5px 10px 5px 7px;
                border: 1px solid #dce2e7;
                border-radius: 10px;
                background: #fff;
                color: #17212a;
                font: inherit;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                text-align: left;
                cursor: pointer;
                transition: border-color .15s, background-color .15s;
            }

            .article-share .article-share__action:hover {
                border-color: var(--share-color);
                background: var(--share-tint);
            }

            .article-share__action:focus-visible {
                outline: 2px solid var(--share-color);
                outline-offset: 2px;
            }

            .article-share .article-share__action--telegram { --share-color: #086689; --share-tint: #e6f5fb; }
            .article-share .article-share__action--facebook { --share-color: #1454ad; --share-tint: #e9f0ff; }
            .article-share .article-share__action--instagram { --share-color: #a92b64; --share-tint: #fbeaf2; }
            .article-share .article-share__action--whatsapp { --share-color: #08724c; --share-tint: #e7f7ef; }
            .article-share .article-share__action--viber { --share-color: #62439b; --share-tint: #f0ebfb; }

            .article-share__icon {
                display: grid;
                flex: none;
                place-items: center;
                width: 28px;
                height: 28px;
                border-radius: 7px;
                color: var(--share-color);
                background: var(--share-tint);
                font-size: 17px;
                line-height: 1;
            }

            .article-share__icon svg { width: 16px; height: 16px; }
            .article-share__action--telegram .article-share__icon svg { fill: currentColor; }

            .article-share__help {
                margin-top: 12px;
                padding: 12px 14px;
                border-left: 3px solid #a92b64;
                border-radius: 6px;
                background: #fff;
            }

            .article-share__help a { display: inline-block; margin-top: 5px; text-decoration: underline; }
            .article-share__status { color: #2c6250; font-size: 13px; }
            .article-share__status:empty { margin: 0; }
            .article-share__status:not(:empty) { margin: 9px 0 0; }
            .article-share__manual { display: block; margin-top: 8px; font-size: 13px; }
            .article-share__manual input { display: block; width: 100%; margin-top: 4px; }
            .article-share [hidden] { display: none !important; }

            @media (max-width: 575px) {
                .article-share { padding: 16px; }
                .article-share__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .article-share .article-share__action { padding-inline: 6px; }
                .article-share__icon { width: 24px; height: 24px; }
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            document.querySelectorAll('[data-article-share]').forEach(function (panel) {
                const instagram = panel.querySelector('[data-share-instagram]');
                const help = panel.querySelector('#' + instagram.getAttribute('aria-controls'));
                const status = panel.querySelector('[data-share-status]');
                const manual = panel.querySelector('[data-share-manual]');

                instagram.addEventListener('click', function () {
                    help.hidden = !help.hidden;
                    instagram.setAttribute('aria-expanded', String(!help.hidden));
                });

                panel.querySelector('[data-share-copy]').addEventListener('click', async function () {
                    status.textContent = '';
                    try {
                        await navigator.clipboard.writeText(panel.dataset.url);
                        manual.hidden = true;
                        status.textContent = 'Ссылка скопирована';
                    } catch {
                        manual.hidden = false;
                        const input = manual.querySelector('input');
                        input.focus();
                        input.select();
                        status.textContent = 'Выделенная ссылка готова к копированию';
                    }
                });
            });
        </script>
    @endpush
@endonce

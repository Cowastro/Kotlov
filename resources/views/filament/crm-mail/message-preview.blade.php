@php
    $direction = $message->direction;
    $isIncoming = $direction === 'incoming';
    $directionLabel = match ($direction) {
        'draft' => 'Черновик',
        'outgoing' => 'Исходящее',
        default => 'Входящее',
    };
    $recipients = collect($message->to ?? [])->pluck('email')->filter()->implode(', ');
    $copies = collect($message->cc ?? [])->pluck('email')->filter()->implode(', ');
    $attachments = collect($message->metadata['attachments'] ?? [])->values();
    $date = $message->received_at ?? $message->sent_at;
    $hasVisibleBody = $hasVisibleBody ?? true;
    $wasUnread = $wasUnread ?? false;
    $isLatestMessage = $isLatestMessage ?? false;
    $canTakeThread = $canTakeThread ?? false;
    $canMarkPotentialDeal = $canMarkPotentialDeal ?? false;
    $canSkipThread = $canSkipThread ?? false;
    $showBody = $hasVisibleBody || $attachments->isEmpty();
    $hasOriginalHtml = $hasVisibleBody && filled($originalHtml);
@endphp

<article
    class="crm-mail-card {{ $isIncoming ? 'is-incoming' : 'is-outgoing' }} {{ $wasUnread ? 'is-unread' : '' }}"
    x-data="{ originalOpen: @js($hasOriginalHtml) }"
>
    <header class="crm-mail-header">
        <div class="crm-mail-avatar" aria-hidden="true">
            {{ mb_strtoupper(mb_substr($message->from_name ?: $message->from_email ?: '?', 0, 1)) }}
        </div>
        <div class="crm-mail-sender">
            <div class="crm-mail-sender-line">
                <strong>{{ $message->from_name ?: $message->from_email ?: 'Неизвестный отправитель' }}</strong>
                <span class="crm-mail-direction">{{ $directionLabel }}</span>
                @if ($wasUnread)
                    <span class="crm-mail-unread-badge"><span aria-hidden="true"></span>Не прочитано</span>
                @endif
            </div>
            <div class="crm-mail-address">{{ $message->from_email }}</div>
            <div class="crm-mail-recipients">
                <span>Кому: {{ $recipients ?: 'не указано' }}</span>
                @if ($copies !== '')
                    <span>Копия: {{ $copies }}</span>
                @endif
            </div>
        </div>
        <div class="crm-mail-header-actions">
            <time class="crm-mail-date">{{ $date?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?: '—' }}</time>
            <div class="crm-mail-message-actions">
                @if ($isLatestMessage && $canTakeThread)
                    <button
                        type="button"
                        class="crm-mail-take-button"
                        wire:click="takeThread"
                        wire:loading.attr="disabled"
                        wire:target="takeThread"
                    >✋ Взять в работу</button>
                @endif
                @if ($isLatestMessage && $canMarkPotentialDeal)
                    <button
                        type="button"
                        class="crm-mail-potential-button"
                        wire:click="markPotentialDeal"
                        wire:loading.attr="disabled"
                        wire:target="markPotentialDeal"
                    >★ Потенциальный клиент</button>
                @endif
                @if ($isLatestMessage && $canSkipThread)
                    <button
                        type="button"
                        class="crm-mail-skip-button"
                        wire:click="skipThread"
                        wire:loading.attr="disabled"
                        wire:target="skipThread"
                    >✓ Пропустить</button>
                @endif
                @if ($message->direction === 'incoming')
                    <button
                        type="button"
                        class="crm-mail-reply-button"
                        wire:click="openReply({{ (int) $message->id }})"
                        wire:loading.attr="disabled"
                        wire:target="openReply({{ (int) $message->id }})"
                    >↩ Ответить</button>
                @endif
                <button
                    type="button"
                    class="crm-mail-forward-button"
                    wire:click="openForward({{ (int) $message->id }})"
                    wire:loading.attr="disabled"
                    wire:target="openForward({{ (int) $message->id }})"
                >↪ Переслать</button>
            </div>
        </div>
    </header>

    <div class="crm-mail-subject">{{ $message->subject ?: 'Без темы' }}</div>

    @if ($showBody)
        <div class="crm-mail-body" x-show="! originalOpen">
            {!! $safeHtml !!}
        </div>
    @endif

    @if ($hasOriginalHtml)
        <div class="crm-mail-original">
            <button
                type="button"
                class="crm-mail-original-toggle"
                x-on:click="originalOpen = ! originalOpen"
                x-text="originalOpen ? 'Скрыть оригинальное оформление' : 'Показать оригинальное оформление'"
            >Скрыть оригинальное оформление</button>

            <template x-if="originalOpen">
                <iframe
                    class="crm-mail-original-frame"
                    sandbox
                    referrerpolicy="no-referrer"
                    loading="lazy"
                    title="Оригинальное HTML-оформление письма"
                    srcdoc="{{ $originalHtml }}"
                ></iframe>
            </template>
        </div>
    @endif

    @if ($attachments->isNotEmpty())
        <footer class="crm-mail-attachments">
            <span class="crm-mail-attachments-title">Вложения · {{ $attachments->count() }}</span>
            <div class="crm-mail-attachment-list">
                @foreach ($attachments as $index => $attachment)
                    @php
                        $attachmentName = $attachment['name'] ?? 'Вложение '.($index + 1);
                        $attachmentSize = isset($attachment['size']) ? Number::fileSize((int) $attachment['size']) : null;
                        $canDownload = filled($attachment['path'] ?? null);
                        $extension = mb_strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
                        $canPreview = $canDownload && in_array($extension, ['docx', 'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv'], true);
                    @endphp
                    @if ($canDownload)
                        <span class="crm-mail-attachment-group">
                            @if ($canPreview)
                                <a
                                    class="crm-mail-attachment"
                                    href="{{ route('admin.crm-mail-messages.attachments.preview', ['message' => $message, 'attachment' => $index]) }}"
                                    target="_blank"
                                    rel="noopener"
                                >👁 {{ $attachmentName }} @if ($attachmentSize)<small>{{ $attachmentSize }}</small>@endif</a>
                                <a
                                    class="crm-mail-attachment-download"
                                    href="{{ route('admin.crm-mail-messages.attachments.download', ['message' => $message, 'attachment' => $index]) }}"
                                    download="{{ $attachmentName }}"
                                    title="Скачать оригинал"
                                >⬇ Скачать</a>
                                @if ($extension === 'pdf')
                                    <a
                                        class="crm-mail-attachment-ai"
                                        href="{{ route('admin.crm-mail-messages.attachments.read-pdf', ['message' => $message, 'attachment' => $index]) }}"
                                        target="_blank"
                                        rel="noopener"
                                        title="Распознать текст PDF и скопировать его"
                                    >✨ Прочитать ИИ</a>
                                @endif
                            @else
                                <a
                                    class="crm-mail-attachment"
                                    href="{{ route('admin.crm-mail-messages.attachments.download', ['message' => $message, 'attachment' => $index]) }}"
                                    download="{{ $attachmentName }}"
                                >⬇ {{ $attachmentName }} @if ($attachmentSize)<small>{{ $attachmentSize }}</small>@endif</a>
                            @endif
                        </span>
                    @else
                        <span class="crm-mail-attachment">📎 {{ $attachmentName }} @if ($attachmentSize)<small>{{ $attachmentSize }}</small>@endif · {{ ($attachment['error'] ?? null) === 'too_large' ? 'файл превышает 25 МБ' : 'файл не удалось сохранить' }}</span>
                    @endif
                @endforeach
            </div>
        </footer>
    @endif
</article>

@once
    <style>
        .crm-mail-card { overflow: hidden; border: 1px solid rgb(63 63 70); border-left: 4px solid rgb(59 130 246); border-radius: 14px; background: rgb(24 24 27); box-shadow: 0 10px 28px rgb(0 0 0 / .12); }
        .crm-mail-card.is-outgoing { border-left-color: rgb(34 197 94); }
        .crm-mail-card.is-unread { border-color: rgb(245 158 11); border-left-width: 6px; box-shadow: 0 0 0 1px rgb(245 158 11 / .28), 0 12px 30px rgb(0 0 0 / .18); }
        .crm-mail-card.is-unread .crm-mail-header { background: rgb(120 53 15 / .24); }
        .crm-mail-header { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 18px 20px 14px; background: rgb(39 39 42 / .7); }
        .crm-mail-avatar { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 999px; background: rgb(234 88 12); color: white; font-weight: 700; }
        .crm-mail-sender-line { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; color: rgb(244 244 245); }
        .crm-mail-direction { border: 1px solid rgb(82 82 91); border-radius: 999px; padding: 2px 8px; color: rgb(161 161 170); font-size: 12px; }
        .crm-mail-unread-badge { display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgb(245 158 11 / .7); border-radius: 999px; padding: 2px 9px; background: rgb(245 158 11 / .13); color: rgb(253 230 138); font-size: 12px; font-weight: 700; }
        .crm-mail-unread-badge > span { width: 8px; height: 8px; border-radius: 999px; background: rgb(245 158 11); box-shadow: 0 0 0 3px rgb(245 158 11 / .2); }
        .crm-mail-address, .crm-mail-recipients, .crm-mail-date { color: rgb(161 161 170); font-size: 13px; }
        .crm-mail-recipients { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 5px; }
        .crm-mail-date { white-space: nowrap; }
        .crm-mail-header-actions { display: grid; justify-items: end; gap: 9px; }
        .crm-mail-message-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 7px; }
        .crm-mail-forward-button, .crm-mail-reply-button, .crm-mail-take-button, .crm-mail-potential-button, .crm-mail-skip-button { border: 1px solid rgb(82 82 91); border-radius: 8px; padding: 6px 10px; color: rgb(212 212 216); font-size: 13px; font-weight: 600; transition: border-color .15s ease, background-color .15s ease; }
        .crm-mail-forward-button:hover, .crm-mail-reply-button:hover, .crm-mail-take-button:hover, .crm-mail-potential-button:hover, .crm-mail-skip-button:hover { border-color: rgb(161 161 170); background: rgb(63 63 70 / .6); }
        .crm-mail-reply-button { border-color: rgb(217 119 6); color: rgb(251 191 36); }
        .crm-mail-take-button { border-color: rgb(217 119 6); color: rgb(253 186 116); }
        .crm-mail-potential-button { border-color: rgb(14 165 233); color: rgb(125 211 252); }
        .crm-mail-skip-button { border-color: rgb(113 113 122); color: rgb(161 161 170); }
        .crm-mail-forward-button:disabled, .crm-mail-reply-button:disabled, .crm-mail-take-button:disabled, .crm-mail-potential-button:disabled, .crm-mail-skip-button:disabled { cursor: wait; opacity: .55; }
        .crm-mail-subject { padding: 18px 22px 0; color: rgb(250 250 250); font-size: 18px; font-weight: 700; line-height: 1.4; }
        .crm-mail-body { max-width: 1050px; padding: 18px 22px 26px; color: rgb(228 228 231); font-size: 15px; line-height: 1.7; overflow-wrap: anywhere; }
        .crm-mail-body p { margin: 0 0 1em; }
        .crm-mail-body ul, .crm-mail-body ol { margin: .6em 0 1em 1.5em; }
        .crm-mail-body table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
        .crm-mail-body td, .crm-mail-body th { border: 1px solid rgb(82 82 91); padding: 7px 10px; }
        .crm-mail-body a { color: rgb(96 165 250); text-decoration: underline; }
        .crm-mail-body blockquote { margin: 1em 0; border-left: 3px solid rgb(82 82 91); padding-left: 14px; color: rgb(161 161 170); }
        .crm-mail-original { border-top: 1px solid rgb(63 63 70); padding: 14px 22px 18px; }
        .crm-mail-original-toggle { border: 1px solid rgb(234 88 12); border-radius: 8px; padding: 8px 12px; color: rgb(251 146 60); font-size: 13px; font-weight: 600; transition: background-color .15s ease; }
        .crm-mail-original-toggle:hover { background: rgb(234 88 12 / .12); }
        .crm-mail-original-frame { width: 100%; height: min(760px, 72vh); margin-top: 14px; border: 1px solid rgb(82 82 91); border-radius: 10px; background: white; }
        .crm-mail-attachments { border-top: 1px solid rgb(63 63 70); padding: 14px 22px 18px; }
        .crm-mail-attachments-title { color: rgb(161 161 170); font-size: 13px; font-weight: 600; }
        .crm-mail-attachment-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 9px; }
        .crm-mail-attachment-group { display: inline-flex; align-items: stretch; }
        .crm-mail-attachment { border: 1px solid rgb(82 82 91); border-radius: 8px; padding: 7px 10px; color: rgb(228 228 231); font-size: 13px; }
        .crm-mail-attachment-group .crm-mail-attachment { border-radius: 8px 0 0 8px; }
        .crm-mail-attachment-download { display: inline-flex; align-items: center; border: 1px solid rgb(82 82 91); border-left: 0; border-radius: 0 8px 8px 0; padding: 7px 10px; color: rgb(134 239 172); font-size: 12px; text-decoration: none; }
        .crm-mail-attachment-download:hover { border-color: rgb(34 197 94); background: rgb(34 197 94 / .1); }
        .crm-mail-attachment-ai { display: inline-flex; align-items: center; border: 1px solid rgb(124 58 237); border-left: 0; border-radius: 0 8px 8px 0; padding: 7px 10px; color: rgb(196 181 253); font-size: 12px; text-decoration: none; }
        .crm-mail-attachment-group:has(.crm-mail-attachment-ai) .crm-mail-attachment-download { border-radius: 0; }
        .crm-mail-attachment-ai:hover { background: rgb(124 58 237 / .15); }
        a.crm-mail-attachment { color: rgb(96 165 250); text-decoration: none; }
        a.crm-mail-attachment:hover { border-color: rgb(96 165 250); background: rgb(59 130 246 / .1); }
        .crm-mail-attachment small { margin-left: 6px; color: rgb(161 161 170); }
        @media (max-width: 700px) {
            .crm-mail-card { min-width: 0; max-width: 100%; border-radius: 8px; }
            .crm-mail-header { grid-template-columns: 32px minmax(0, 1fr); gap: 9px; padding: 12px; }
            .crm-mail-avatar { width: 32px; height: 32px; }
            .crm-mail-sender, .crm-mail-address, .crm-mail-recipients { min-width: 0; overflow-wrap: anywhere; }
            .crm-mail-header-actions { grid-column: 1 / -1; justify-items: start; }
            .crm-mail-message-actions { justify-content: flex-start; }
            .crm-mail-subject, .crm-mail-body, .crm-mail-attachments { padding-left: 12px; padding-right: 12px; }
            .crm-mail-subject { overflow-wrap: anywhere; }
            .crm-mail-original { min-width: 0; padding: 10px 6px; }
            .crm-mail-original-toggle { max-width: calc(100% - 12px); margin-inline: 6px; text-align: left; }
            .crm-mail-original-frame { display: block; max-width: 100%; margin-top: 10px; border-radius: 6px; }
            .crm-mail-attachment-group { min-width: 0; max-width: 100%; flex-wrap: wrap; }
            .crm-mail-attachment { min-width: 0; overflow-wrap: anywhere; }
        }
    </style>
@endonce

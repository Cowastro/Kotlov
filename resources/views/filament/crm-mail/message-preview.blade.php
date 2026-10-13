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
    $attachments = collect($message->metadata['attachments'] ?? [])->pluck('name')->filter();
    $date = $message->received_at ?? $message->sent_at;
@endphp

<article class="crm-mail-card {{ $isIncoming ? 'is-incoming' : 'is-outgoing' }}">
    <header class="crm-mail-header">
        <div class="crm-mail-avatar" aria-hidden="true">
            {{ mb_strtoupper(mb_substr($message->from_name ?: $message->from_email ?: '?', 0, 1)) }}
        </div>
        <div class="crm-mail-sender">
            <div class="crm-mail-sender-line">
                <strong>{{ $message->from_name ?: $message->from_email ?: 'Неизвестный отправитель' }}</strong>
                <span class="crm-mail-direction">{{ $directionLabel }}</span>
            </div>
            <div class="crm-mail-address">{{ $message->from_email }}</div>
            <div class="crm-mail-recipients">
                <span>Кому: {{ $recipients ?: 'не указано' }}</span>
                @if ($copies !== '')
                    <span>Копия: {{ $copies }}</span>
                @endif
            </div>
        </div>
        <time class="crm-mail-date">{{ $date?->timezone('Europe/Minsk')->format('d.m.Y H:i') ?: '—' }}</time>
    </header>

    <div class="crm-mail-subject">{{ $message->subject ?: 'Без темы' }}</div>

    <div class="crm-mail-body">
        {!! $safeHtml !!}
    </div>

    @if ($attachments->isNotEmpty())
        <footer class="crm-mail-attachments">
            <span class="crm-mail-attachments-title">Вложения · {{ $attachments->count() }}</span>
            <div class="crm-mail-attachment-list">
                @foreach ($attachments as $attachment)
                    <span class="crm-mail-attachment">📎 {{ $attachment }}</span>
                @endforeach
            </div>
        </footer>
    @endif
</article>

@once
    <style>
        .crm-mail-card { overflow: hidden; border: 1px solid rgb(63 63 70); border-left: 4px solid rgb(59 130 246); border-radius: 14px; background: rgb(24 24 27); box-shadow: 0 10px 28px rgb(0 0 0 / .12); }
        .crm-mail-card.is-outgoing { border-left-color: rgb(34 197 94); }
        .crm-mail-header { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; gap: 12px; align-items: start; padding: 18px 20px 14px; background: rgb(39 39 42 / .7); }
        .crm-mail-avatar { display: grid; width: 42px; height: 42px; place-items: center; border-radius: 999px; background: rgb(234 88 12); color: white; font-weight: 700; }
        .crm-mail-sender-line { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; color: rgb(244 244 245); }
        .crm-mail-direction { border: 1px solid rgb(82 82 91); border-radius: 999px; padding: 2px 8px; color: rgb(161 161 170); font-size: 12px; }
        .crm-mail-address, .crm-mail-recipients, .crm-mail-date { color: rgb(161 161 170); font-size: 13px; }
        .crm-mail-recipients { display: flex; flex-wrap: wrap; gap: 6px 18px; margin-top: 5px; }
        .crm-mail-date { white-space: nowrap; }
        .crm-mail-subject { padding: 18px 22px 0; color: rgb(250 250 250); font-size: 18px; font-weight: 700; line-height: 1.4; }
        .crm-mail-body { max-width: 1050px; padding: 18px 22px 26px; color: rgb(228 228 231); font-size: 15px; line-height: 1.7; overflow-wrap: anywhere; }
        .crm-mail-body p { margin: 0 0 1em; }
        .crm-mail-body ul, .crm-mail-body ol { margin: .6em 0 1em 1.5em; }
        .crm-mail-body table { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; }
        .crm-mail-body td, .crm-mail-body th { border: 1px solid rgb(82 82 91); padding: 7px 10px; }
        .crm-mail-body a { color: rgb(96 165 250); text-decoration: underline; }
        .crm-mail-body blockquote { margin: 1em 0; border-left: 3px solid rgb(82 82 91); padding-left: 14px; color: rgb(161 161 170); }
        .crm-mail-attachments { border-top: 1px solid rgb(63 63 70); padding: 14px 22px 18px; }
        .crm-mail-attachments-title { color: rgb(161 161 170); font-size: 13px; font-weight: 600; }
        .crm-mail-attachment-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 9px; }
        .crm-mail-attachment { border: 1px solid rgb(82 82 91); border-radius: 8px; padding: 7px 10px; color: rgb(228 228 231); font-size: 13px; }
        @media (max-width: 700px) { .crm-mail-header { grid-template-columns: 36px minmax(0, 1fr); } .crm-mail-avatar { width: 36px; height: 36px; } .crm-mail-date { grid-column: 2; } .crm-mail-subject, .crm-mail-body { padding-left: 16px; padding-right: 16px; } }
    </style>
@endonce

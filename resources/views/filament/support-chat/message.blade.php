@php
    $isTeam = in_array($message->sender_type, ['manager', 'assistant'], true);
    $senderName = match ($message->sender_type) {
        'manager' => $message->sender?->name ?: 'Консультант KOTLOV',
        'assistant' => 'AI-помощник KOTLOV',
        default => $message->conversation->name,
    };
@endphp

<div style="display:flex;justify-content:{{ $isTeam ? 'flex-end' : 'flex-start' }};margin:8px 0;">
    <div style="max-width:78%;padding:12px 14px;border-radius:16px;{{ $isTeam ? 'background:#171717;color:#fff;border-bottom-right-radius:4px;' : 'background:#f3f4f6;color:#171717;border-bottom-left-radius:4px;' }}">
        <div style="font-size:12px;font-weight:700;margin-bottom:5px;opacity:.72;">
            {{ $senderName }}
        </div>
        <div style="white-space:pre-wrap;line-height:1.45;">{{ $message->body }}</div>
        <div style="font-size:11px;margin-top:6px;opacity:.62;">
            {{ $message->created_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') }}
        </div>
    </div>
</div>

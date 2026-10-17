@php
    $isManager = $message->sender_type === 'manager';
@endphp

<div style="display:flex;justify-content:{{ $isManager ? 'flex-end' : 'flex-start' }};margin:8px 0;">
    <div style="max-width:78%;padding:12px 14px;border-radius:16px;{{ $isManager ? 'background:#171717;color:#fff;border-bottom-right-radius:4px;' : 'background:#f3f4f6;color:#171717;border-bottom-left-radius:4px;' }}">
        <div style="font-size:12px;font-weight:700;margin-bottom:5px;opacity:.72;">
            {{ $isManager ? ($message->sender?->name ?: 'Консультант KOTLOV') : $message->conversation->name }}
        </div>
        <div style="white-space:pre-wrap;line-height:1.45;">{{ $message->body }}</div>
        <div style="font-size:11px;margin-top:6px;opacity:.62;">
            {{ $message->created_at?->timezone('Europe/Minsk')->format('d.m.Y H:i') }}
        </div>
    </div>
</div>

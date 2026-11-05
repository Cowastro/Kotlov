@if($contactBranch->telegram_username || $contactBranch->viber_phone || $contactBranch->instagram_username)
    <div class="branch-social-contacts" style="display:flex;flex-wrap:wrap;gap:10px 20px;margin:16px 0">
        @if($contactBranch->telegram_username)
            <a href="https://t.me/{{ $contactBranch->telegram_username }}" target="_blank" rel="noopener">Telegram: {{ '@'.$contactBranch->telegram_username }}</a>
        @endif
        @if($contactBranch->viber_phone)
            <a href="viber://chat?number=%2B{{ preg_replace('/\D+/', '', $contactBranch->viber_phone) }}">Viber: {{ $contactBranch->viber_phone }}</a>
        @endif
        @if($contactBranch->instagram_username)
            <a href="https://www.instagram.com/{{ $contactBranch->instagram_username }}/" target="_blank" rel="noopener">Instagram: {{ '@'.$contactBranch->instagram_username }}</a>
        @endif
    </div>
@endif

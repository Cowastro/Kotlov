@if($contactBranch->telegram_username || $contactBranch->viber_phone || $contactBranch->whatsapp_phone || $contactBranch->instagram_username)
    <div class="branch-social" aria-label="Мессенджеры и соцсети магазина">
        <p class="branch-social__title">Связаться с магазином</p>
        <div class="branch-social__links">
        @if($contactBranch->telegram_username)
            <a class="branch-social__link is-telegram" href="https://t.me/{{ $contactBranch->telegram_username }}" target="_blank" rel="noopener"><span class="branch-social__icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m21.4 3.5-3.2 16c-.2 1.1-.9 1.4-1.8.9l-4.9-3.6-2.4 2.3c-.3.3-.5.5-1 .5l.4-5 9.2-8.3c.4-.4-.1-.6-.6-.3L5.7 13.2.8 11.7c-1.1-.3-1.1-1.1.2-1.6L20 2.8c.9-.3 1.6.2 1.4.7Z"/></svg></span><span><strong>Telegram</strong><small>{{ '@'.$contactBranch->telegram_username }}</small></span></a>
        @endif
        @if($contactBranch->viber_phone)
            <a class="branch-social__link is-viber" href="viber://chat?number=%2B{{ preg_replace('/\D+/', '', $contactBranch->viber_phone) }}"><span class="branch-social__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h10a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4h-6l-5 3v-3a4 4 0 0 1-3-4V7a4 4 0 0 1 4-4Z"/><path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Z"/><path d="M13 6a5 5 0 0 1 5 5m-5-2a2 2 0 0 1 2 2"/></svg></span><span><strong>Viber</strong><small>{{ $contactBranch->viber_phone }}</small></span></a>
        @endif
        @if($contactBranch->instagram_username)
            <a class="branch-social__link is-instagram" href="https://www.instagram.com/{{ $contactBranch->instagram_username }}/" target="_blank" rel="noopener"><span class="branch-social__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span><span><strong>Instagram</strong><small>{{ '@'.$contactBranch->instagram_username }}</small></span></a>
        @endif
        @if($contactBranch->whatsapp_phone)
            <a class="branch-social__link is-whatsapp" href="https://wa.me/{{ preg_replace('/\D+/', '', $contactBranch->whatsapp_phone) }}" target="_blank" rel="noopener"><span class="branch-social__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12a8 8 0 0 1-12 7l-5 2 1.5-5A8 8 0 1 1 20 12Z"/><path d="m8 7 2 3-1.5 1.5a10 10 0 0 0 4 4L14 14l3 2c-1 4-5 1-7-1S5 8 8 7Z"/></svg></span><span><strong>WhatsApp</strong><small>{{ $contactBranch->whatsapp_phone }}</small></span></a>
        @endif
        </div>
    </div>
@endif

{{--
    Компонент защиты публичных форм от спама.
    Добавлять сразу после @csrf в каждой публичной форме:

    <x-form-protection />
--}}

{{-- Honeypot: поле остаётся пустым у живых пользователей --}}
<input type="text" name="fax_number"
    style="position:absolute;left:-9999px;opacity:0;height:0;width:0"
    tabindex="-1" autocomplete="off" aria-hidden="true">

{{-- Время начала заполнения формы --}}
<input type="hidden" name="form_started_at" value="{{ time() }}">

{{-- Cloudflare Turnstile (включается через .env FORM_TURNSTILE_ENABLED=true) --}}
@if(config('services.turnstile.enabled') && config('services.turnstile.site_key'))
    <div class="cf-turnstile my-3" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif

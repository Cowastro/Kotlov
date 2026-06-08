{{--
    Компонент защиты публичных форм от спама.
    Добавлять сразу после @csrf в каждой публичной форме:

    <x-form-protection />
--}}

{{-- Honeypot: обёрнут в div, сбрасывается JS перед отправкой (боты JS не выполняют) --}}
<div style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden" aria-hidden="true">
    <input type="text" name="_hpf" tabindex="-1" autocomplete="new-password">
</div>

{{-- Время начала заполнения формы --}}
<input type="hidden" name="form_started_at" value="{{ time() }}">

{{-- Cloudflare Turnstile (включается через .env FORM_TURNSTILE_ENABLED=true) --}}
@if(config('services.turnstile.enabled') && config('services.turnstile.site_key'))
    <div class="cf-turnstile my-3" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif

{{-- JS сбрасывает honeypot перед отправкой — браузерный автофил нейтрализован --}}
@once
<script>
document.addEventListener('submit', function (e) {
    var hp = e.target.querySelector('input[name="_hpf"]');
    if (hp) hp.value = '';
}, true);
</script>
@endonce

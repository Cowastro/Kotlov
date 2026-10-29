@php
    $prefix = $prefix ?? 'registration';
    $accountType = old('account_type', 'legal');
@endphp

<div class="registration-kind" data-registration-kind>
    <input type="hidden" name="account_type" value="{{ $accountType }}" data-account-type>
    <button type="button" data-account-kind="legal" class="{{ $accountType === 'legal' ? 'active' : '' }}">Юридическое лицо</button>
    <button type="button" data-account-kind="retail" class="{{ $accountType === 'retail' ? 'active' : '' }}">Физическое лицо</button>
</div>

<div class="form-content">
    <div data-legal-fields @if($accountType !== 'legal') hidden @endif>
        <fieldset class="tf-field">
            <label for="{{ $prefix }}-company" class="tf-lable fw-medium">Компания / ИП <span class="text-primary">*</span></label>
            <input type="text" id="{{ $prefix }}-company" name="company_name" value="{{ old('company_name') }}" placeholder="ООО «Теплосервис»" data-legal-required>
        </fieldset>
        <fieldset class="tf-field">
            <label for="{{ $prefix }}-unp" class="tf-lable fw-medium">УНП / ИНН</label>
            <input type="text" id="{{ $prefix }}-unp" name="company_inn" value="{{ old('company_inn') }}" placeholder="УНП или ИНН организации">
        </fieldset>
    </div>

    <fieldset class="tf-field">
        <label for="{{ $prefix }}-name" class="tf-lable fw-medium"><span data-name-label>{{ $accountType === 'legal' ? 'Контактное лицо' : 'Имя' }}</span> <span class="text-primary">*</span></label>
        <input type="text" id="{{ $prefix }}-name" name="name" value="{{ old('name') }}" placeholder="Имя и фамилия" required autocomplete="name">
    </fieldset>
    <fieldset class="tf-field">
        <label for="{{ $prefix }}-phone" class="tf-lable fw-medium">Телефон <span class="text-primary">*</span></label>
        <input type="tel" id="{{ $prefix }}-phone" name="phone" value="{{ old('phone') }}" placeholder="+375 (XX) XXX-XX-XX" required autocomplete="tel">
    </fieldset>
    @if(config('services.sms_by.enabled'))
        <div class="phone-verification" data-phone-verification data-send-url="{{ route('registration.phone.send-code') }}">
            <input type="hidden" name="phone_verification_token" value="{{ old('phone_verification_token') }}" data-phone-verification-token>
            <button type="button" class="phone-verification-send" data-phone-verification-send>Получить код по SMS</button>
            <div class="phone-verification-code" data-phone-verification-code @if(!old('phone_verification_token')) hidden @endif>
                <label for="{{ $prefix }}-phone-code" class="tf-lable fw-medium">Код из SMS <span class="text-primary">*</span></label>
                <input type="text" id="{{ $prefix }}-phone-code" name="phone_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="6 цифр">
            </div>
            <p class="phone-verification-status" data-phone-verification-status aria-live="polite"></p>
        </div>
    @endif
    <fieldset class="tf-field">
        <label for="{{ $prefix }}-email" class="tf-lable fw-medium">Email <span class="text-primary">*</span></label>
        <input type="email" id="{{ $prefix }}-email" name="email" value="{{ old('email') }}" placeholder="name@company.by" required autocomplete="email">
    </fieldset>
    <fieldset class="tf-field password-wrapper">
        <label for="{{ $prefix }}-password" class="tf-lable fw-medium">Пароль <span class="text-primary">*</span></label>
        <div class="password-wrapper w-100"><span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span><input class="password-field" type="password" name="password" id="{{ $prefix }}-password" placeholder="Минимум 8 символов" required autocomplete="new-password"></div>
    </fieldset>
    <fieldset class="tf-field password-wrapper">
        <label for="{{ $prefix }}-password-confirm" class="tf-lable fw-medium">Повторите пароль <span class="text-primary">*</span></label>
        <div class="password-wrapper w-100"><span class="toggle-pass icon-EyeSlash fs-20 cl-text-3"></span><input class="password-field" type="password" name="password_confirmation" id="{{ $prefix }}-password-confirm" placeholder="Повторите пароль" required autocomplete="new-password"></div>
    </fieldset>
</div>

<div class="registration-consent checkbox-wrap">
    <input class="tf-check flex-shrink-0" type="checkbox" id="{{ $prefix }}-privacy" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required>
    <label for="{{ $prefix }}-privacy">Я принимаю условия <a href="/privacy" target="_blank" class="text-decoration-underline">обработки персональных данных</a></label>
</div>

<style>
.registration-kind{display:grid;grid-template-columns:1fr 1fr;margin-bottom:18px;border:1px solid #dce2e8;border-radius:9px;overflow:hidden}.registration-kind button{border:0;border-right:1px solid #dce2e8;background:#fff;color:#59697a;padding:12px 8px;font-weight:700}.registration-kind button:last-child{border-right:0}.registration-kind button.active{background:#132238;color:#fff}.registration-consent{align-items:flex-start;margin-top:4px}.registration-consent label{font-size:13px;line-height:1.45}.registration-consent a{color:#087cdb}
.phone-verification{margin:-4px 0 14px}.phone-verification-send{width:100%;padding:11px 16px;border:1px solid #087cdb;border-radius:8px;background:#fff;color:#087cdb;font-weight:700}.phone-verification-send:disabled{opacity:.65;cursor:not-allowed}.phone-verification-code{margin-top:12px}.phone-verification-code input{width:100%;letter-spacing:.35em;text-align:center;font-size:18px}.phone-verification-status{min-height:20px;margin:7px 0 0;font-size:13px;color:#59697a}.phone-verification-status.is-error{color:#c62828}.phone-verification-status.is-success{color:#168344}
</style>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-registration-kind]').forEach(function (switcher) {
        const form = switcher.closest('form');
        const input = switcher.querySelector('[data-account-type]');
        const legalFields = form.querySelector('[data-legal-fields]');
        const nameLabel = form.querySelector('[data-name-label]');
        function setKind(kind) {
            input.value = kind;
            switcher.querySelectorAll('[data-account-kind]').forEach(function (button) {
                button.classList.toggle('active', button.dataset.accountKind === kind);
            });
            legalFields.hidden = kind !== 'legal';
            legalFields.querySelectorAll('[data-legal-required]').forEach(function (field) {
                field.required = kind === 'legal';
            });
            nameLabel.textContent = kind === 'legal' ? 'Контактное лицо' : 'Имя';
        }
        switcher.querySelectorAll('[data-account-kind]').forEach(function (button) {
            button.addEventListener('click', function () { setKind(this.dataset.accountKind); });
        });
        setKind(input.value === 'retail' ? 'retail' : 'legal');
    });

    document.querySelectorAll('[data-phone-verification]').forEach(function (wrapper) {
        const form = wrapper.closest('form');
        const phone = form.querySelector('input[name="phone"]');
        const csrf = form.querySelector('input[name="_token"]');
        const button = wrapper.querySelector('[data-phone-verification-send]');
        const codeWrap = wrapper.querySelector('[data-phone-verification-code]');
        const code = codeWrap.querySelector('input[name="phone_code"]');
        const token = wrapper.querySelector('[data-phone-verification-token]');
        const status = wrapper.querySelector('[data-phone-verification-status]');
        let timer = null;

        function showStatus(message, type) {
            status.textContent = message;
            status.classList.toggle('is-error', type === 'error');
            status.classList.toggle('is-success', type === 'success');
        }

        function startCooldown(seconds) {
            let remaining = seconds;
            button.disabled = true;
            button.textContent = 'Повторить через ' + remaining + ' сек.';
            clearInterval(timer);
            timer = setInterval(function () {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(timer);
                    button.disabled = false;
                    button.textContent = 'Отправить код повторно';
                    return;
                }
                button.textContent = 'Повторить через ' + remaining + ' сек.';
            }, 1000);
        }

        phone.addEventListener('input', function () {
            token.value = '';
            code.value = '';
            codeWrap.hidden = true;
            showStatus('', '');
        });

        button.addEventListener('click', async function () {
            if (!phone.value.trim()) {
                showStatus('Сначала введите номер телефона.', 'error');
                phone.focus();
                return;
            }

            button.disabled = true;
            showStatus('Отправляем код…', '');

            try {
                const response = await fetch(wrapper.dataset.sendUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf.value
                    },
                    body: JSON.stringify({phone: phone.value})
                });
                const payload = await response.json();

                if (!response.ok) {
                    const message = payload.errors && payload.errors.phone
                        ? payload.errors.phone[0]
                        : (payload.message || 'Не удалось отправить код.');
                    throw new Error(message);
                }

                token.value = payload.verification_token;
                codeWrap.hidden = false;
                code.required = true;
                code.focus();
                showStatus(payload.message, 'success');
                startCooldown(60);
            } catch (error) {
                button.disabled = false;
                showStatus(error.message || 'Не удалось отправить код.', 'error');
            }
        });
    });
});
</script>
@endpush
@endonce

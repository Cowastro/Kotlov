@php
    $prefix = $prefix ?? 'registration';
    $accountType = old('account_type', 'legal');
@endphp

<div class="registration-fields" data-registration-fields data-company-lookup-url="{{ route('registration.company-lookup') }}">
<div class="registration-kind" data-registration-kind>
    <input type="hidden" name="account_type" value="{{ $accountType }}" data-account-type>
    <button type="button" data-account-kind="legal" class="{{ $accountType === 'legal' ? 'active' : '' }}">Юридическое лицо</button>
    <button type="button" data-account-kind="retail" class="{{ $accountType === 'retail' ? 'active' : '' }}">Физическое лицо</button>
</div>

<div class="form-content">
    <div class="registration-legal-fields" data-legal-fields @if($accountType !== 'legal') hidden @endif>
        <fieldset class="tf-field">
            <label for="{{ $prefix }}-company" class="tf-lable fw-medium">Компания / ИП <span class="text-primary">*</span></label>
            <input type="text" id="{{ $prefix }}-company" name="company_name" value="{{ old('company_name') }}" placeholder="ООО «Теплосервис»" data-legal-required>
        </fieldset>
        <fieldset class="tf-field">
            <label for="{{ $prefix }}-unp" class="tf-lable fw-medium">УНП <span class="text-primary">*</span></label>
            <div class="registration-unp-row">
                <input type="text" id="{{ $prefix }}-unp" name="company_inn" value="{{ old('company_inn') }}" placeholder="9 цифр УНП" inputmode="numeric" maxlength="9" autocomplete="off" data-legal-required data-company-unp>
                <button type="button" data-company-lookup-button title="Заполнить название компании и юридический адрес по УНП" aria-label="Автозаполнить реквизиты компании по УНП">Автозаполнить</button>
            </div>
            <p class="registration-lookup-status" data-company-lookup-status aria-live="polite">Введите 9 цифр УНП — название и адрес заполнятся автоматически.</p>
        </fieldset>
        <fieldset class="tf-field">
            <label for="{{ $prefix }}-company-address" class="tf-lable fw-medium">Юридический адрес</label>
            <input type="text" id="{{ $prefix }}-company-address" name="company_address" value="{{ old('company_address') }}" placeholder="Заполнится автоматически по УНП" data-company-address>
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
</div>

<div class="registration-consent checkbox-wrap">
    <input class="tf-check flex-shrink-0" type="checkbox" id="{{ $prefix }}-privacy" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required>
    <label for="{{ $prefix }}-privacy">Я принимаю условия <a href="/privacy" target="_blank" class="text-decoration-underline">обработки персональных данных</a></label>
</div>

<style>
#register .modal-content{max-height:calc(100vh - 24px)}#register .modal-main{overflow-y:auto;padding-right:3px}.registration-fields{width:100%}.registration-kind{display:grid;grid-template-columns:1fr 1fr;gap:4px;margin-bottom:20px;padding:4px;border:1px solid #e0e0e0;border-radius:10px;background:#f6f6f6}.registration-kind button{display:flex;align-items:center;justify-content:center;width:100%;height:42px;min-height:42px;margin:0;padding:0 10px;border:0;border-radius:7px;background:transparent;color:#5f6368;font-family:inherit;font-size:14px;font-weight:500;line-height:1;text-align:center;white-space:nowrap}.registration-kind button.active{background:#111;color:#fff;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.08)}.registration-fields .form-content{gap:16px;margin-bottom:24px}.registration-legal-fields{display:grid;gap:16px}.registration-fields .tf-field{width:100%}.registration-fields .tf-field>input,.registration-fields .password-wrapper.w-100>input,.registration-unp-row input{width:100%;height:50px;min-height:50px;box-sizing:border-box}.registration-unp-row{display:grid;grid-template-columns:minmax(0,1fr) 132px;align-items:stretch;gap:8px;width:100%}.registration-unp-row button{display:flex;align-items:center;justify-content:center;width:100%;height:50px;min-height:50px;margin:0;padding:0 10px;border:1px solid #111;border-radius:8px;background:#111;color:#fff;font-family:inherit;font-size:13px;font-weight:600;line-height:1;text-align:center;white-space:nowrap}.registration-unp-row button:disabled{opacity:.6;cursor:wait}.registration-lookup-status{min-height:18px;margin:6px 0 0;color:#7a8794;font-size:12px;line-height:1.45}.registration-lookup-status.is-success{color:#168344}.registration-lookup-status.is-error{color:#c62828}.registration-consent{display:grid;grid-template-columns:16px minmax(0,1fr);align-items:start;gap:9px;margin:0 0 22px}.registration-consent .tf-check{margin-top:2px}.registration-consent label{min-width:0;font-size:13px;line-height:1.45}.registration-consent a{color:#087cdb}
.phone-verification{margin:-4px 0 14px}.phone-verification-send{width:100%;padding:11px 16px;border:1px solid #087cdb;border-radius:8px;background:#fff;color:#087cdb;font-weight:700}.phone-verification-send:disabled{opacity:.65;cursor:not-allowed}.phone-verification-code{margin-top:12px}.phone-verification-code input{width:100%;letter-spacing:.35em;text-align:center;font-size:18px}.phone-verification-status{min-height:20px;margin:7px 0 0;font-size:13px;color:#59697a}.phone-verification-status.is-error{color:#c62828}.phone-verification-status.is-success{color:#168344}
@media(max-width:420px){.registration-kind button{font-size:13px}.registration-unp-row{grid-template-columns:minmax(0,1fr) 120px}.registration-unp-row button{font-size:12px;padding:0 7px}}
</style>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-registration-fields]').forEach(function (wrapper) {
        const form = wrapper.closest('form');
        const unp = wrapper.querySelector('[data-company-unp]');
        const company = wrapper.querySelector('input[name="company_name"]');
        const address = wrapper.querySelector('[data-company-address]');
        const button = wrapper.querySelector('[data-company-lookup-button]');
        const status = wrapper.querySelector('[data-company-lookup-status]');
        const csrf = form.querySelector('input[name="_token"]');
        let timer = null;
        let lastLookup = '';

        function showStatus(message, type) {
            status.textContent = message;
            status.classList.toggle('is-success', type === 'success');
            status.classList.toggle('is-error', type === 'error');
        }

        async function lookupCompany() {
            const value = unp.value.replace(/\D/g, '').slice(0, 9);
            unp.value = value;
            if (value.length !== 9) {
                showStatus('Введите 9 цифр УНП.', value.length ? 'error' : '');
                return;
            }
            if (lastLookup === value && company.value.trim()) return;

            button.disabled = true;
            button.textContent = 'Ищем…';
            showStatus('Проверяем УНП в реестре…', '');
            try {
                const response = await fetch(wrapper.dataset.companyLookupUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf.value
                    },
                    body: JSON.stringify({unp: value})
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'Организация не найдена.');

                const data = payload.data || {};
                company.value = data.short_name || data.legal_name || company.value;
                address.value = data.address || address.value;
                lastLookup = value;
                showStatus('Организация найдена. Реквизиты заполнены автоматически.', 'success');
            } catch (error) {
                showStatus((error.message || 'Не удалось проверить УНП.') + ' Можно заполнить данные вручную.', 'error');
            } finally {
                button.disabled = false;
                button.textContent = 'Автозаполнить';
            }
        }

        unp.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 9);
            lastLookup = '';
            clearTimeout(timer);
            if (this.value.length === 9) timer = setTimeout(lookupCompany, 350);
            else showStatus('Введите 9 цифр УНП — название и адрес заполнятся автоматически.', '');
        });
        unp.addEventListener('blur', function () { if (this.value.length === 9) lookupCompany(); });
        button.addEventListener('click', lookupCompany);
    });

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

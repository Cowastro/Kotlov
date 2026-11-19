(() => {
    const form = document.getElementById('evropochta-estimate');
    if (!form || typeof window.mountEvropochtaBranchPicker !== 'function') return;

    const picker = window.mountEvropochtaBranchPicker(form.querySelector('[data-evropochta-picker]'));
    const box = form.querySelector('#evropochta-box');
    const dimensions = ['length', 'width', 'height'].map(field => form.elements.namedItem(field));
    const submit = form.querySelector('button[type="submit"]');
    const errorBox = form.querySelector('[data-calculator-errors]');
    const result = document.getElementById('evropochta-estimate-result');
    const amount = result.querySelector('[data-calculator-price]');
    const status = result.querySelector('[data-calculator-result-status]');
    const quote = result.querySelector('[data-calculator-result]');
    const formatter = new Intl.NumberFormat('ru-BY', { maximumFractionDigits: 0 });
    let pending = null;

    function selectMatchingBox() {
        if (!box) return;

        const sizes = dimensions.map(field => Number(field.value.replace(',', '.'))).join(',');
        box.value = Array.from(box.options).find(option => option.value === sizes)?.value || '';
    }

    box?.addEventListener('change', () => {
        if (!box.value) return;

        const sizes = box.value.split(',');
        dimensions.forEach((field, index) => { field.value = sizes[index]; });
    });
    selectMatchingBox();

    function showStatus(message) {
        quote.hidden = true;
        status.textContent = message;
        status.hidden = false;
    }

    function showErrors(messages) {
        const items = messages.map(message => {
            const item = document.createElement('li');
            item.textContent = message;
            return item;
        });
        errorBox.querySelector('ul').replaceChildren(...items);
        errorBox.hidden = !items.length;
    }

    function resetQuote(event) {
        if (dimensions.includes(event.target)) selectMatchingBox();
        if (!errorBox.hidden) showErrors([]);

        if (pending || !quote.hidden) {
            pending?.abort();
            pending = null;
            submit.disabled = false;
            showStatus('Данные изменились. Рассчитайте тариф для новых параметров.');
        }
    }

    form.addEventListener('input', resetQuote);
    form.addEventListener('change', resetQuote);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending) return;

        if (!picker.branch.value) {
            showErrors(['Выберите отделение из списка.']);
            picker.search.focus();
            return;
        }

        const request = new AbortController();
        pending = request;
        submit.disabled = true;
        showErrors([]);
        showStatus('Рассчитываем тариф…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(form),
                signal: request.signal,
            });
            const data = await response.json();
            if (pending !== request) return;

            if (response.status === 422 && data.errors) {
                showErrors(Object.values(data.errors).flat());
                showStatus('Проверьте данные расчёта.');
                return;
            }
            if (response.status === 429) {
                showErrors(['Слишком много расчётов. Повторите через минуту.']);
                showStatus('Расчёт временно недоступен.');
                return;
            }
            if (!response.ok || typeof data.price !== 'number') {
                throw new Error('Quote unavailable');
            }

            amount.textContent = formatter.format(data.price) + ' BYN';
            result.querySelector('[data-calculator-address]').textContent = data.address;
            result.querySelector('[data-calculator-type]').textContent = data.type;
            status.hidden = true;
            quote.hidden = false;
            result.focus({ preventScroll: true });
            if (window.matchMedia('(max-width: 840px)').matches) {
                result.scrollIntoView({ block: 'start' });
            }
        } catch (error) {
            if (pending !== request) return;

            showErrors(['Не удалось получить тариф. Повторите расчёт.']);
            showStatus('Расчёт временно недоступен.');
        } finally {
            if (pending === request) {
                pending = null;
                submit.disabled = false;
            }
        }
    });
})();

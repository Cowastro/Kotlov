(() => {
    const panel = document.querySelector('[data-product-delivery]');
    if (!panel) return;

    const holder = panel.querySelector('[data-product-picker]');
    const root = holder.querySelector('[data-evropochta-picker]');
    const rate = panel.querySelector('[data-product-rate]');
    const retry = panel.querySelector('[data-product-retry]');
    let picker;
    let revision = 0;

    async function update() {
        const current = ++revision;
        const url = new URL(panel.dataset.productDelivery, window.location.href);
        if (picker) {
            if (!picker.branch.value) return;
            url.searchParams.set('branch', picker.branch.value);
        }
        retry.hidden = true;
        rate.textContent = picker ? 'Рассчитываем доставку…' : 'Загружаем отделения…';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json();
            if (current !== revision) return;
            if (!response.ok) throw new Error(data.message || 'Расчёт временно недоступен.');
            if (picker) {
                rate.textContent = 'Доставка до отделения — ' + data.price + ' BYN. ' + data.address;
                return;
            }
            if (!Object.keys(data.branches).length) throw new Error('Список отделений временно недоступен.');
            root.dataset.branches = JSON.stringify(data.branches);
            picker = window.mountEvropochtaBranchPicker(root);
            picker.search.addEventListener('input', () => {
                revision++;
                rate.textContent = '';
                retry.hidden = true;
            });
            picker.branch.addEventListener('change', () => { if (picker.branch.value) update(); });
            holder.hidden = false;
            rate.textContent = '';
        } catch (error) {
            if (current !== revision) return;
            rate.textContent = error instanceof SyntaxError || error instanceof TypeError
                ? 'Расчёт временно недоступен.' : error.message;
            retry.hidden = false;
        }
    }
    panel.addEventListener('toggle', () => { if (panel.open && !picker) update(); });
    retry.addEventListener('click', update);
})();

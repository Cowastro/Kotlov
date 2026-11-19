window.mountEvropochtaBranchPicker = function (root) {
    const branch = root.querySelector('#evropochta-branch');
    const search = root.querySelector('#evropochta-search');
    const results = root.querySelector('#evropochta-results');
    const status = root.querySelector('#evropochta-search-status');
    const labels = JSON.parse(root.dataset.branches);

    const normalize = value => value.toLowerCase().replace(/ё/g, 'е').replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    const branches = Object.entries(labels).map(([id, label]) => ({ id, label, search: normalize(label) }));
    branch.value = Object.hasOwn(labels, branch.value) ? branch.value : '';
    search.value = branch.value ? labels[branch.value] : '';

    function render() {
        results.replaceChildren();
        results.hidden = true;
        status.textContent = '';
        search.setCustomValidity(search.disabled || branch.value ? '' : 'Выберите отделение из списка.');
        if (search.disabled || branch.value) return;

        const query = normalize(search.value);
        if (!query) {
            status.textContent = 'Введите город, улицу или номер отделения.';
            return;
        }

        const terms = query.split(' ');
        const matches = branches.filter(entry => terms.every(term => entry.search.includes(term)));
        const shown = matches.slice(0, 20);
        if (!shown.length) {
            status.textContent = 'Отделения не найдены. Проверьте написание или попробуйте указать город.';
            return;
        }

        status.textContent = shown.length < matches.length
            ? 'Показано ' + shown.length + ' из ' + matches.length + '. Уточните поиск или выберите отделение.'
            : 'Выберите отделение из списка.';
        for (const entry of shown) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = entry.label;
            button.dataset.branchId = entry.id;
            results.append(button);
        }
        results.hidden = false;
    }

    search.addEventListener('input', () => {
        branch.value = '';
        render();
        branch.dispatchEvent(new Event('change', { bubbles: true }));
    });

    search.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            const first = results.querySelector('button');
            if (first && results.childElementCount === 1) {
                first.click();
            } else {
                first?.focus();
            }
        } else if (event.key === 'ArrowDown' && !results.hidden) {
            event.preventDefault();
            results.querySelector('button')?.focus();
        }
    });

    results.addEventListener('click', event => {
        const button = event.target.closest('button[data-branch-id]');
        if (!button || search.disabled) return;

        branch.value = button.dataset.branchId;
        search.value = labels[branch.value];
        render();
        search.focus();
        branch.dispatchEvent(new Event('change', { bubbles: true }));
    });

    render();

    return {
        branch,
        search,
        toggle(enabled, city) {
            branch.disabled = !enabled;
            search.disabled = !enabled;
            if (enabled && !branch.value && !search.value) search.value = city || '';
            render();
        },
    };
};

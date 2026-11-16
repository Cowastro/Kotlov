(function () {
    'use strict';
    const editor = document.getElementById('selection-editor');
    if (!editor) return;
    const rows = Array.from(editor.querySelectorAll('[data-selection-row]'));
    const search = document.getElementById('selection-search');
    const counter = document.getElementById('selection-match-count');
    const total = editor.querySelector('[data-selection-total]');
    const money = new Intl.NumberFormat('ru-BY', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const names = rows.map(row => row.querySelector('.selection-product').textContent.toLocaleLowerCase());
    function recalculate() {
        let sum = 0;
        rows.forEach(row => {
            const quantity = Number(row.querySelector('input[type=number]').value);
            const line = Math.round(Number(row.dataset.price) * (Number.isFinite(quantity) ? quantity : 0) * 100) / 100;
            row.querySelector('[data-line-total]').textContent = money.format(line);
            row.classList.toggle('is-removed', quantity === 0);
            sum += line;
        });
        total.textContent = money.format(sum);
    }
    search.addEventListener('input', function () {
        const term = search.value.trim().toLocaleLowerCase();
        let visible = 0;
        rows.forEach((row, index) => { row.hidden = !names[index].includes(term); if (!row.hidden) visible++; });
        counter.textContent = 'Найдено ' + visible + ' из ' + rows.length;
    });
    editor.addEventListener('input', event => { if (event.target.matches('input[type=number]')) recalculate(); });
    recalculate();
}());

(function () {
    'use strict';
    var form = document.querySelector('[data-pc-form]'); if (!form) return;
    function toggle() {
        var load = form.querySelector('[data-pc-load]'), head = form.querySelector('[data-pc-head]');
        if (!load) return;
        form.querySelectorAll('[data-pc-load-fields]').forEach(function (section) {
            section.hidden = !section.dataset.pcLoadFields.split(' ').includes(load.value);
            section.querySelectorAll('input,select').forEach(function (input) { input.disabled = section.hidden; });
        });
        form.querySelectorAll('[data-pc-head-fields]').forEach(function (section) {
            section.hidden = (load.value === 'direct' ? 'known' : head.value) !== section.dataset.pcHeadFields;
            section.querySelectorAll('input,select').forEach(function (input) { input.disabled = section.hidden; });
        });
    }
    form.addEventListener('change', toggle);
    var system = form.querySelector('[data-pc-system]');
    if (system) {
        var initialSupply = form.querySelector('[data-pc-supply]'), initialReturn = form.querySelector('[data-pc-return]');
        if (Number(initialSupply.value) !== (system.value === 'floor' ? 40 : 70) || Number(initialReturn.value) !== (system.value === 'floor' ? 35 : 50)) {
            initialSupply.dataset.edited = '1'; initialReturn.dataset.edited = '1';
        }
    }
    if (system) system.addEventListener('change', function () {
        var supply = form.querySelector('[data-pc-supply]'), back = form.querySelector('[data-pc-return]');
        if (!supply.dataset.edited && !back.dataset.edited) {
            supply.value = system.value === 'floor' ? 40 : 70; back.value = system.value === 'floor' ? 35 : 50;
        }
    });
    form.querySelectorAll('[data-pc-supply],[data-pc-return]').forEach(function (input) { input.addEventListener('input', function () { input.dataset.edited = '1'; }); });
    document.querySelectorAll('[data-pc-save]').forEach(function (save) {
        var select = save.querySelector('[data-pc-selection]'), label = save.querySelector('[data-pc-new-name]');
        select.addEventListener('change', function () {
            label.hidden = !!select.value; label.querySelector('input').required = !select.value;
            save.querySelector('[data-pc-revision]').value = select.selectedOptions[0].dataset.revision || '';
        });
    });
    toggle();
    if (location.hash === '#pc-result') {
        Promise.resolve(document.fonts ? document.fonts.ready : null).then(function () {
            requestAnimationFrame(function () {
                var target = document.querySelector('.pc-errors') || document.getElementById('pc-result');
                target.setAttribute('tabindex', '-1');
                target.focus({preventScroll:true});
                target.scrollIntoView({block:'start'});
            });
        });
    }
})();

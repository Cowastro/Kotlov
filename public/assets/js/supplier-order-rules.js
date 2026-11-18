(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.KotlovOrderRules = factory();
}(typeof window === 'object' ? window : this, function () {
    'use strict';
    function label(rules) {
        var titles = {minimum:'Мин.', multiple:'Кратность', box:'В коробке'};
        return Object.keys(titles).filter(function (key) { return Number(rules[key]) > 0 && Number.isFinite(Number(rules[key])); }).map(function (key) {
            return titles[key] + ' ' + Number(rules[key]).toLocaleString('ru-RU', {maximumFractionDigits:3}) + (rules.unit ? ' ' + rules.unit : '');
        }).join(' · ');
    }
    function warning(rules, quantity) {
        if (!Number.isFinite(quantity) || quantity <= 0) return '';
        if (rules.minimum && quantity + 1e-8 < Number(rules.minimum)) return 'Количество меньше минимума поставщика: ' + label({minimum:rules.minimum, unit:rules.unit}).replace(/\.$/, '') + '.';
        var multiple = Number(rules.multiple || 0);
        if (multiple > 0 && Math.abs(quantity / multiple - Math.round(quantity / multiple)) > 1e-8) return 'Проверьте кратность заказа: ' + label({multiple:multiple, unit:rules.unit}).replace(/\.$/, '') + '.';
        return '';
    }
    function initialQuantity(rules) {
        var minimum = Math.min(999, Math.max(1, Math.ceil(Number(rules.minimum || 1))));
        for (var quantity = minimum; quantity <= 999; quantity++) if (!warning(rules, quantity)) return quantity;
        return minimum;
    }
    return {label:label, warning:warning, initialQuantity:initialQuantity};
}));

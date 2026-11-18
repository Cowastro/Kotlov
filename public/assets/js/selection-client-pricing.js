(function (root, factory) {
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.KotlovClientPricing = factory();
}(typeof window === 'object' ? window : this, function () {
    'use strict';
    function quote(item, mode, price, discount, quantity) {
        var base = Math.round(Number(item.client_pricing ? item.client_pricing.base_price : item.price) * 100);
        var cents = Math.round(Number(item.price) * 100), rate = Number(discount);
        if (mode === 'price') cents = Math.round(Number(price) * 100);
        if (mode === 'reset') cents = base;
        if (mode === 'discount') cents = base > 0 && String(discount).trim() !== '' && Number.isFinite(rate) && rate >= 0 && rate <= 99.99 ? Math.round(base * (10000 - Math.round(rate * 100)) / 10000) : NaN;
        var valid = Number.isFinite(cents) && cents >= 1 && cents <= 99999999999 && Number.isInteger(quantity) && quantity >= 1 && quantity <= 999;
        var purchase = item.workspace_purchase && item.workspace_purchase.purchase_price;
        var known = purchase !== null && purchase !== undefined && Number.isFinite(Number(purchase));
        var cost = known ? Math.round(Number(purchase) * 100) : null;
        return {valid:valid, base:base / 100, price:cents / 100, total:cents * quantity / 100,
            margin:valid && known ? (cents - cost) * quantity / 100 : null,
            marginPercent:valid && known ? Math.round((cents - cost) / cents * 1000) / 10 : null,
            belowCost:valid && known && cents < cost};
    }
    return {quote:quote};
}));

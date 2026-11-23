'use strict';

let catalogFilterDraft = null;

function mobileCatalogFiltersOpen() {
    return window.matchMedia('(max-width: 1199.98px)').matches
        && document.getElementById('catalog-filters')?.classList.contains('show');
}

function catalogFilterUrl() {
    return new URL(mobileCatalogFiltersOpen() && catalogFilterDraft ? catalogFilterDraft : window.location.href);
}

function commitCatalogFilterUrl(url) {
    url.searchParams.delete('page');
    url.hash = 'catalog-products';
    if (mobileCatalogFiltersOpen()) {
        catalogFilterDraft = url.toString();
    } else {
        window.location.href = url.toString();
    }
}

function applyFilter(key, value) {
    const url = catalogFilterUrl();
    if (value === '' || value === null) url.searchParams.delete(key);
    else url.searchParams.set(key, value);
    commitCatalogFilterUrl(url);
}

function applyPriceFilter() {
    const url = catalogFilterUrl();
    const range = document.getElementById('price-value-range');
    const min = Number.parseFloat(document.getElementById('price-min-value')?.value);
    const max = Number.parseFloat(document.getElementById('price-max-value')?.value);
    if (Number.isFinite(min) && min > Number(range?.dataset.min || 0)) url.searchParams.set('price_min', min);
    else url.searchParams.delete('price_min');
    if (Number.isFinite(max) && max < Number(range?.dataset.max || 0)) url.searchParams.set('price_max', max);
    else url.searchParams.delete('price_max');
    commitCatalogFilterUrl(url);
}

function applyAttrFilter(attrId, optionId, checked, kind) {
    const url = catalogFilterUrl();
    const group = `attr[${attrId}]`;
    const keys = [...new Set(url.searchParams.keys())].filter(key => key === group || key.startsWith(`${group}[`));
    let values = keys.flatMap(key => url.searchParams.getAll(key));
    if (checked && !values.includes(String(optionId))) values.push(String(optionId));
    if (!checked) values = values.filter(value => value !== String(optionId));
    keys.forEach(key => url.searchParams.delete(key));
    values.forEach(value => url.searchParams.append(`${group}[]`, value));
    if (kind === 'diameter') {
        url.searchParams.delete('mono_diameter');
        url.searchParams.delete('chimney_diameter');
    }
    if (kind === 'steel') url.searchParams.delete('chimney_steel');
    commitCatalogFilterUrl(url);
}

function applyMobileCatalogFilters() {
    if (catalogFilterDraft) window.location.href = catalogFilterDraft;
}

document.addEventListener('DOMContentLoaded', function () {
    const panel = document.getElementById('catalog-filters');
    panel?.addEventListener('show.bs.offcanvas', function () { catalogFilterDraft = null; });
    panel?.addEventListener('hidden.bs.offcanvas', function () {
        catalogFilterDraft = null;
        panel.querySelectorAll('input[type="checkbox"],input[type="radio"]').forEach(input => { input.checked = input.defaultChecked; });
        panel.querySelectorAll('.price-val').forEach(input => { input.value = input.defaultValue; });
    });
});

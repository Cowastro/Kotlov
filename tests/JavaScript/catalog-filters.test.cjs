const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function page(mobile, query = '') {
    const events = {};
    const panel = {classList: {contains: () => true}, addEventListener: (name, cb) => { events[name] = cb; }, querySelectorAll: () => []};
    const window = {location: {href: 'https://kotlov.by/shibery-dymohod?' + query}, matchMedia: () => ({matches: mobile})};
    const context = vm.createContext({URL, window, document: {getElementById: () => panel, addEventListener: (name, cb) => cb()}});
    vm.runInContext(readFileSync(path.join(__dirname, '../../public/assets/js/catalog-filters.js'), 'utf8'), context);
    return {context, window, events};
}

test('mobile applies diameter, steel, thickness and stock together with one navigation', () => {
    const {context: p, window} = page(true, 'sort=price_asc&page=3');
    const original = window.location.href;
    p.applyAttrFilter(435, 272, true, 'diameter');
    p.applyAttrFilter(441, 1378, true, 'steel');
    p.applyAttrFilter(439, 1168, true);
    p.applyFilter('in_stock', '1');
    assert.equal(window.location.href, original);
    p.applyMobileCatalogFilters();
    const url = new URL(window.location.href);
    assert.equal(url.searchParams.get('attr[435][]'), '272');
    assert.equal(url.searchParams.get('attr[441][]'), '1378');
    assert.equal(url.searchParams.get('attr[439][]'), '1168');
    assert.equal(url.searchParams.get('in_stock'), '1');
    assert.equal(url.searchParams.get('sort'), 'price_asc');
    assert.equal(url.searchParams.has('page'), false);
});

test('sidebar replaces equivalent quick restrictions but keeps unrelated filters', () => {
    const {context: p, window} = page(false, 'mono_diameter=150&chimney_steel=304&in_stock=1&attr[439][0]=1168');
    p.applyAttrFilter(435, 272, true, 'diameter');
    p.applyAttrFilter(441, 1378, true, 'steel');
    const url = new URL(window.location.href);
    assert.equal(url.searchParams.has('mono_diameter'), false);
    assert.equal(url.searchParams.has('chimney_steel'), false);
    assert.equal(url.searchParams.get('attr[439][0]'), '1168');
    assert.equal(url.searchParams.get('in_stock'), '1');
});

test('indexed multi-select values can be removed without losing another selection', () => {
    const {context: p, window} = page(false, 'attr[435][0]=272&attr[435][1]=273&in_stock=1');
    p.applyAttrFilter(435, 272, false, 'diameter');
    const url = new URL(window.location.href);
    assert.deepEqual(url.searchParams.getAll('attr[435][]'), ['273']);
    assert.equal(url.searchParams.get('in_stock'), '1');
});

test('closing mobile panel discards an unapplied draft', () => {
    const {context: p, window, events} = page(true, 'in_stock=1');
    const original = window.location.href;
    p.applyAttrFilter(435, 272, true, 'diameter');
    events['hidden.bs.offcanvas']();
    p.applyMobileCatalogFilters();
    assert.equal(window.location.href, original);
});

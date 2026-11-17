const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const source = readFileSync(require('node:path').join(__dirname, '../../public/assets/js/cart-workspace.js'), 'utf8');

function element(properties = {}) {
    return Object.assign({
        dataset: {}, listeners: {}, hidden: false, textContent: '',
        classList: {toggle() {}}, style: {setProperty() {}},
        addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); },
        querySelector() { return null; }, querySelectorAll() { return []; },
        appendChild() {}, scrollIntoView() {},
    }, properties);
}

function cart() {
    const requests = [], navigations = [], submissions = [];
    const input = element({value: '2'}), check = element({checked: true}), note = element();
    const rowFields = {'.cart-qty-input': input, '.cart-line-check': check, '.cart-line-note': note};
    for (const name of ['[data-line-quantity]', '[data-line-total]', '[data-unit-price]']) rowFields[name] = element();
    const row = element({dataset: {productId: '7', price: '10'}, querySelector: name => rowFields[name] || null});
    input.closest = () => row;
    const root = element({dataset: {initialSelection: JSON.stringify({positions: 1, count: 2, subtotal: 20, total: 20})},
        contains: () => false, querySelectorAll: name => name === '[data-cart-row]' ? [row] : []});
    const ids = {'cart-save-state': element(), 'cart-error': element({hidden: true}), 'cart-agree': element({checked: true})};
    const document = element({
        querySelector: name => name === '[data-cart-workspace]' ? root : {content: 'test-csrf'},
        getElementById: id => ids[id] || null, createElement: () => element(),
    });
    const window = element();
    const context = {document, window, URL, Map, Promise, Number, Array, JSON, setTimeout, clearTimeout,
        location: {href: 'https://kotlov.test/cart', origin: 'https://kotlov.test', pathname: '/cart', search: '', assign: url => navigations.push(url)},
        HTMLFormElement: {prototype: {submit() { submissions.push(this); }}},
        fetch(url, options) {
            return new Promise((resolve, reject) => requests.push({url, body: JSON.parse(options.body), resolve, reject}));
        },
    };
    vm.runInNewContext(source, context);
    function editQuantity() { input.value = '17'; input.listeners.input[0](); }
    function emit(name, target) {
        const event = {target, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }};
        for (const handler of document.listeners[name] || []) handler(event);
        return event;
    }
    const tick = () => new Promise(setImmediate);
    async function saved() {
        await tick();
        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/cart/update');
        assert.equal(requests[0].body.quantity, 17);
        requests[0].resolve({ok: true, json: async () => ({item_quantity: 17, item_price: 10,
            selection: {positions: 1, count: 17, subtotal: 170, total: 170}})});
        await tick();
    }
    return {editQuantity, emit, tick, saved, requests, navigations, submissions, ids};
}

function accountLink() {
    const link = {href: 'https://kotlov.test/account', target: '', hasAttribute: () => false, getAttribute: () => '/account'};
    return {closest: selector => selector === 'a[href]' ? link : null};
}

test('leaving cart immediately after typing waits for confirmed quantity save', async () => {
    const app = cart(); app.editQuantity();
    assert.equal(app.emit('click', accountLink()).defaultPrevented, true);
    assert.deepEqual(app.navigations, []);
    await app.saved();
    assert.deepEqual(app.navigations, ['https://kotlov.test/account']);
});

test('logout outside the cart waits for the last quantity before invalidating session', async () => {
    const app = cart(); app.editQuantity();
    const form = {action: 'https://kotlov.test/logout', dataset: {}};
    assert.equal(app.emit('submit', form).defaultPrevented, true);
    assert.equal(app.submissions.length, 0);
    await app.saved();
    assert.equal(app.submissions.length, 1);
    assert.equal(app.submissions[0], form);
});

test('a failed save keeps the cart open and prevents logout', async () => {
    const app = cart(); app.editQuantity();
    app.emit('submit', {action: 'https://kotlov.test/logout', dataset: {}});
    await app.tick(); app.requests[0].reject(new Error('Connection lost')); await app.tick();
    assert.equal(app.submissions.length, 0);
    assert.equal(app.ids['cart-error'].hidden, false);
    assert.equal(app.ids['cart-save-state'].textContent, 'Есть несохранённые изменения');
});

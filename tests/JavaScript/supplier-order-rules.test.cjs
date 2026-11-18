const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const context = {module:{exports:{}}};
vm.runInNewContext(readFileSync(require('node:path').join(__dirname, '../../public/assets/js/supplier-order-rules.js'), 'utf8'), context);
const rules = context.module.exports;

test('confirmed minimum is visible and box size does not force full boxes', () => {
    const offer = {minimum:8, multiple:1, box:100, unit:'шт.'};
    assert.equal(rules.label(offer), 'Мин. 8 шт. · Кратность 1 шт. · В коробке 100 шт.');
    assert.equal(rules.initialQuantity(offer), 8);
    assert.equal(rules.warning(offer, 9), '');
    assert.match(rules.warning(offer, 7), /меньше минимума/);
    assert.equal(rules.warning(offer, 0), '');
});

test('supplier switch can remove old rules and decimal multiples remain accurate', () => {
    assert.equal(rules.label({}), '');
    assert.equal(rules.label({minimum:0, box:0, unit:'уп.'}), '');
    assert.equal(rules.initialQuantity({}), 1);
    assert.equal(rules.initialQuantity({minimum:8,multiple:3}), 9);
    assert.equal(rules.initialQuantity({minimum:1.5,multiple:0.5}), 2);
    assert.equal(rules.warning({multiple:0.1}, 0.3), '');
    assert.match(rules.warning({multiple:3}, 8), /кратность/);
});

test('quick additions respect final line minimum, multiples and the workspace limit', () => {
    const pack = {minimum:8, multiple:4, box:100};
    assert.equal(rules.additionQuantity(pack, 0, 1, 1), 8);
    assert.equal(rules.additionQuantity(pack, 8, 1, 1), 4);
    assert.equal(rules.additionQuantity(pack, 3, 1, 1), 5);
    assert.equal(rules.additionQuantity(pack, 8, 3, -1), null);
    assert.equal(rules.additionQuantity(pack, 8, 5, 1), 8);
    assert.equal(rules.additionQuantity({minimum:1.5, multiple:1.5}, 0, 1, 1), 3);
    assert.equal(rules.additionQuantity({minimum:2000}, 0, 1, 1), null);
    assert.equal(rules.additionQuantity({}, 999, 1, 1), null);
});

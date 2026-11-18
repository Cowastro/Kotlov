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

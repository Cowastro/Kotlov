const {test} = require('node:test');
const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const vm = require('node:vm');
const context = {module:{exports:{}}};
vm.runInNewContext(readFileSync(require('node:path').join(__dirname, '../../public/assets/js/selection-client-pricing.js'), 'utf8'), context);
const {quote} = context.module.exports;
test('discount uses original cents, not an already discounted price', () => {
    const item = {price:90, client_pricing:{base_price:100}, workspace_purchase:{purchase_price:60}};
    const result = quote(item, 'discount', '', 20, 3);
    assert.equal(result.price,80); assert.equal(result.total,240); assert.equal(result.margin,60); assert.equal(result.marginPercent,25);
    assert.equal(item.price,90); assert.equal(item.workspace_purchase.purchase_price,60);
});
test('manual price and reset show the actual margin without changing cost', () => {
    const item = {price:80, client_pricing:{base_price:100}, workspace_purchase:{purchase_price:60}};
    assert.equal(quote(item,'price',55.55,0,2).margin,-8.9);
    assert.equal(quote(item,'price',55.55,0,2).belowCost,true);
    assert.equal(quote(item,'reset',0,0,2).price,100);
    assert.equal(quote({price:50},'keep',0,0,2).margin,null);
});
test('rounds half a kopeck up and rejects zero or unknown base for discounts', () => {
    assert.equal(quote({price:0.05},'discount',0,10,1).price,0.05);
    assert.equal(quote({price:0},'discount',0,10,1).valid,false);
    assert.equal(quote({price:0.01},'discount',0,99.99,1).valid,false);
    assert.equal(quote({price:100},'price',-1,0,1).valid,false);
});

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

function cart(count = 1) {
    const requests = [], navigations = [], submissions = [], printed = [];
    const rows = Array.from({length:count}, (_, index) => {
        const input=element({value:'2'}),check=element({checked:true}),note=element();
        const fields={'.cart-qty-input':input,'.cart-line-check':check,'.cart-line-note':note,
            '.cart-line__info':element({textContent:'Фитинг KOTLOV-'+String(index+1).padStart(6,'0')})};
        for(const name of ['[data-line-quantity]','[data-line-total]','[data-unit-price]'])fields[name]=element();
        const row=element({dataset:{productId:String(index+7),price:'10'},querySelector:name=>fields[name]||null});
        input.closest=()=>row;return row;
    });
    const printLink=element({href:'https://kotlov.test/cart/print'}), empty=element({hidden:true});
    const root=element({dataset:{initialSelection:JSON.stringify({positions:count,count:count*2,subtotal:count*20,total:count*20})},
        contains:()=>false,querySelector:name=>name==='.cart-search-empty'?empty:null,
        querySelectorAll:name=>name==='[data-cart-row]'?rows:name==='a[target="_blank"]'?[printLink]:[]});
    const ids={'cart-save-state':element(),'cart-error':element({hidden:true}),'cart-agree':element({checked:true}),
        'cart-select-all':element({checked:true}),'cart-find':element({value:'',focus(){}}),
        'cart-search-clear':element(),'cart-search-result':element(),'cart-select-label':element()};
    const document=element({querySelector:name=>name==='[data-cart-workspace]'?root:{content:'test-csrf'},
        getElementById:id=>ids[id]||null,createElement:()=>element()});
    const window=element({open(){return {opener:{},location:{replace:url=>printed.push(url)},close(){printed.push('closed');}};}});
    const context={document,window,URL,Map,Promise,Number,Array,JSON,setTimeout,clearTimeout,
        location:{href:'https://kotlov.test/cart',origin:'https://kotlov.test',pathname:'/cart',search:'',assign:url=>navigations.push(url)},
        HTMLFormElement:{prototype:{submit(){submissions.push(this);}}},
        fetch(url,options){return new Promise((resolve,reject)=>requests.push({url,body:JSON.parse(options.body),resolve,reject}));}};
    vm.runInNewContext(source,context);
    function editQuantity(index=0,quantity=17){const input=rows[index].querySelector('.cart-qty-input');input.value=String(quantity);input.listeners.input[0]();}
    function emit(name,target){const event={target,button:0,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};
        for(const handler of document.listeners[name]||[])handler(event);return event;}
    const tick=()=>new Promise(setImmediate);
    async function saved(){await tick();assert.equal(requests.length,1);assert.equal(requests[0].url,'/cart/update');assert.equal(requests[0].body.quantity,17);
        requests[0].resolve({ok:true,json:async()=>({item_quantity:17,item_price:10,selection:{positions:count,count:count*2+15,subtotal:count*20+150,total:count*20+150}})});await tick();}
    function search(value){ids['cart-find'].value=value;ids['cart-find'].listeners.input[0]();}
    function print(){const event={preventDefault(){}};printLink.listeners.click[0](event);}
    return {editQuantity,emit,tick,saved,requests,navigations,submissions,ids,rows,printed,search,print};
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

test('150 rows: article search ignores separators, resets without losing quantities',async()=>{
    const app=cart(150);app.editQuantity(149);app.search('kotlov 000150');
    assert.equal(app.rows.filter(row=>!row.hidden).length,1);
    assert.equal(app.rows[149].querySelector('.cart-qty-input').value,'17');
    app.ids['cart-search-clear'].listeners.click[0]();
    assert.equal(app.rows.filter(row=>!row.hidden).length,150);
    assert.equal(app.rows[149].querySelector('.cart-qty-input').value,'17');
    app.emit('click',accountLink());await app.saved();
});

test('select shown rows leaves 149 hidden positions untouched',async()=>{
    const app=cart(150);app.search('KOTLOV-000150');
    app.ids['cart-select-all'].checked=false;app.ids['cart-select-all'].listeners.change[0]();await app.tick();
    assert.equal(app.requests[0].url,'/cart/selection');
    assert.deepEqual(Array.from(app.requests[0].body.ids),[156]);
    assert.equal(app.rows.filter(row=>row.querySelector('.cart-line-check').checked).length,149);
    app.requests[0].resolve({ok:true,json:async()=>({})});await app.tick();
});

test('printing immediately after an edit waits for the saved last row of 150',async()=>{
    const app=cart(150);app.editQuantity(149);app.print();
    assert.deepEqual(app.printed,[]);await app.saved();
    assert.equal(app.requests[0].body.product_id,'156');
    assert.deepEqual(app.printed,['https://kotlov.test/cart/print']);
});

test('failed last-row save closes print tab instead of printing an outdated selection',async()=>{
    const app=cart(150);app.editQuantity(149);app.print();await app.tick();
    app.requests[0].reject(new Error('Connection lost'));await app.tick();
    assert.deepEqual(app.printed,['closed']);assert.equal(app.ids['cart-error'].hidden,false);
});

test('three different edits in a 150-line cart all finish before printing',async()=>{
    const app=cart(150);app.editQuantity(0,3);app.editQuantity(74,17);app.editQuantity(149,9);app.print();
    for(const [index,quantity] of [3,17,9].entries()){
        await app.tick();assert.equal(app.requests.length,index+1);assert.equal(app.requests[index].body.quantity,quantity);
        assert.deepEqual(app.printed,[]);
        app.requests[index].resolve({ok:true,json:async()=>({item_quantity:quantity,item_price:10})});
    }
    await app.tick();assert.deepEqual(app.printed,['https://kotlov.test/cart/print']);
    assert.equal(app.rows.length,150);
});

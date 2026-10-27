<style>
:root { --b2b-ink:#17202a; --b2b-muted:#6f7b87; --b2b-line:#dfe4e8; --b2b-bg:#f4f6f8; --b2b-accent:#df3f42; --b2b-green:#15803d; }
body.b2b-body { margin:0; color:var(--b2b-ink); background:var(--b2b-bg); font-family:Inter,Arial,sans-serif; }
.b2b-body a { text-decoration:none; }
.b2b-portal { min-height:100vh; }
.b2b-topbar { position:sticky; z-index:50; top:0; height:64px; border-bottom:1px solid #e2e6e9; background:rgba(255,255,255,.96); backdrop-filter:blur(12px); }
.b2b-topbar__inner { display:flex; align-items:center; width:min(1720px,calc(100% - 40px)); height:100%; margin:auto; }
.b2b-brand { display:flex; align-items:center; gap:12px; padding-right:22px; border-right:1px solid var(--b2b-line); }
.b2b-brand img { display:block; width:126px; height:auto; }
.b2b-brand span { padding:4px 7px; border-radius:5px; color:#fff; background:#111; font-size:11px; font-weight:800; letter-spacing:.08em; }
.b2b-topbar__company { display:flex; min-width:0; flex-direction:column; margin-left:20px; line-height:1.2; }
.b2b-topbar__company small { color:var(--b2b-muted); font-size:10px; text-transform:uppercase; letter-spacing:.06em; }
.b2b-topbar__company strong { max-width:320px; overflow:hidden; font-size:13px; text-overflow:ellipsis; white-space:nowrap; }
.b2b-topbar__actions { display:flex; align-items:center; gap:6px; margin-left:auto; }
.b2b-topbar__link,.b2b-topbar__logout { display:flex; align-items:center; gap:7px; min-height:38px; padding:0 12px; border:0; border-radius:8px; color:#35404a; background:transparent; font-size:13px; font-weight:650; cursor:pointer; }
.b2b-topbar__link:hover,.b2b-topbar__logout:hover { color:#111; background:#f0f2f4; }
.b2b-topbar__cart { color:#fff; background:var(--b2b-ink); }
.b2b-topbar__cart:hover { color:#fff; background:#080c10; }
.b2b-topbar__cart b { display:grid; place-items:center; min-width:20px; height:20px; padding:0 5px; border-radius:10px; color:#111; background:#fff; font-size:11px; }
.b2b-workspace { width:min(1720px,calc(100% - 40px)); margin:0 auto; padding:26px 0 40px; }
.b2b-page-heading { display:flex; align-items:flex-end; justify-content:space-between; margin-bottom:18px; }
.b2b-page-heading p { margin:0 0 3px; color:var(--b2b-accent); font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
.b2b-page-heading h1 { margin:0; font-size:26px; line-height:1.15; }
.b2b-mobile-catalog-button { display:none; }
.b2b-command-bar { position:relative; z-index:15; margin-bottom:18px; padding:14px; border:1px solid var(--b2b-line); border-radius:12px; background:#fff; box-shadow:0 3px 15px rgba(20,31,42,.04); }
.b2b-search { position:relative; display:flex; align-items:center; height:50px; border:2px solid var(--b2b-ink); border-radius:9px; background:#fff; }
.b2b-search > i { margin-left:16px; color:#78838d; font-size:19px; }
.b2b-search > input { width:0; min-width:0; height:46px; flex:1 1 0; padding:0 12px; border:0!important; border-radius:0!important; outline:0!important; background:transparent!important; box-shadow:none!important; font-size:15px; -webkit-appearance:none; appearance:none; }
.b2b-search > input::-webkit-search-cancel-button { display:none; }
.b2b-search > kbd { margin-right:10px; padding:2px 7px; border:1px solid #d8dde1; border-radius:5px; color:#7a848d; background:#f5f6f7; font:12px Inter,sans-serif; }
.b2b-search__clear { display:grid; place-items:center; width:32px; height:32px; margin-right:6px; border-radius:6px; color:#7b858e; font-size:22px; }
.b2b-search__clear:hover { color:#111; background:#f0f2f4; }
.b2b-search > button { display:grid; place-items:center; align-self:stretch; min-width:112px; margin:-2px; padding:0; border:0; border-radius:0 9px 9px 0; color:#fff; background:var(--b2b-ink); font-size:13px; font-weight:750; line-height:1; white-space:nowrap; cursor:pointer; }
.b2b-search > button:hover { background:#080c10; }
.b2b-search-suggest { position:absolute; z-index:40; top:calc(100% + 7px); right:0; left:0; max-height:440px; border:1px solid var(--b2b-line); border-radius:10px; background:#fff; box-shadow:0 16px 38px rgba(13,24,35,.16); overflow:auto; }
.b2b-suggest-item { display:grid; grid-template-columns:42px minmax(0,1fr) auto; align-items:center; gap:11px; padding:9px 12px; color:var(--b2b-ink); }
.b2b-suggest-item + .b2b-suggest-item { border-top:1px solid #eef0f2; }
.b2b-suggest-item:hover,.b2b-suggest-item.is-active { background:#f5f7f8; }
.b2b-suggest-item img { width:42px; height:42px; border-radius:6px; object-fit:contain; background:#f5f5f5; }
.b2b-suggest-item strong,.b2b-suggest-item small { display:block; }
.b2b-suggest-item strong { overflow:hidden; font-size:13px; text-overflow:ellipsis; white-space:nowrap; }
.b2b-suggest-item small { margin-top:3px; color:var(--b2b-muted); font-size:11px; }
.b2b-suggest-item__price { text-align:right; }
.b2b-suggest-item__price b { display:block; font-size:13px; white-space:nowrap; }
.b2b-suggest-item__price small { color:var(--b2b-green); }
.b2b-suggest-empty { padding:18px; color:var(--b2b-muted); font-size:13px; text-align:center; }
.b2b-command-bar__filters { display:flex; align-items:flex-end; gap:16px; margin-top:11px; }
.b2b-mode-switch { display:flex; gap:3px; padding:3px; border-radius:8px; background:#eef1f3; }
.b2b-mode-switch a { padding:8px 12px; border-radius:6px; color:#59636c; font-size:12px; font-weight:700; white-space:nowrap; }
.b2b-mode-switch a.active { color:#fff; background:var(--b2b-ink); }
.b2b-filter-selects { display:flex; align-items:flex-end; justify-content:flex-end; gap:8px; min-width:0; flex:1; }
.b2b-filter-selects label { min-width:150px; margin:0; }
.b2b-filter-selects label span { display:block; margin:0 0 3px 2px; color:var(--b2b-muted); font-size:10px; }
.b2b-filter-selects select { width:100%; height:34px; padding:0 29px 0 9px; border:1px solid #d9dee2; border-radius:7px; color:#27313a; background:#fff; font-size:12px; }
.b2b-reset-filters { height:34px; padding:8px 9px; color:var(--b2b-accent); font-size:12px; font-weight:650; white-space:nowrap; }
.b2b-mobile-category-showcase { display:none; }
.b2b-catalog-layout { display:grid; grid-template-columns:270px minmax(0,1fr); align-items:start; gap:16px; }
.b2b-catalog-overlay { display:none; }
.b2b-category-panel { position:sticky; top:82px; max-height:calc(100vh - 100px); padding:14px; border:1px solid var(--b2b-line); border-radius:12px; background:#fff; overflow:auto; }
.b2b-category-panel__heading { display:flex; align-items:center; justify-content:space-between; margin-bottom:11px; }
.b2b-category-panel__heading div { display:flex; flex-direction:column; }
.b2b-category-panel__heading small { color:var(--b2b-muted); font-size:9px; text-transform:uppercase; letter-spacing:.08em; }
.b2b-category-panel__heading strong { font-size:16px; }
.b2b-category-panel__close { display:none; border:0; background:transparent; font-size:24px; }
.b2b-category-search { display:flex; align-items:center; height:34px; margin-bottom:8px; border:1px solid #dce1e5; border-radius:7px; background:#f8f9fa; }
.b2b-category-search i { margin-left:9px; color:#89929a; font-size:13px; }
.b2b-category-search input { min-width:0; flex:1; padding:0 8px; border:0; outline:0; background:transparent; font-size:12px; }
.b2b-category-all,.b2b-category-leaf,.b2b-category-branch summary { display:flex; align-items:center; justify-content:space-between; gap:7px; min-height:32px; padding:5px 7px; border-radius:6px; color:#303b45; cursor:pointer; font-size:12px; }
.b2b-category-all:hover,.b2b-category-leaf:hover,.b2b-category-branch summary:hover { background:#f0f2f4; }
.b2b-category-all.active,.b2b-category-leaf.active,.b2b-category-branch.selected > summary { color:#fff; background:var(--b2b-ink); }
.b2b-category-tree { margin-top:4px; }
.b2b-category-tree__nested { margin-left:9px; padding-left:7px; border-left:1px solid #dfe3e6; }
.b2b-category-branch summary { list-style:none; font-weight:650; }
.b2b-category-branch summary::-webkit-details-marker { display:none; }
.b2b-category-branch summary::before { content:'›'; flex:0 0 auto; color:#8a949d; transition:transform .15s ease; }
.b2b-category-branch[open] > summary::before { transform:rotate(90deg); }
.b2b-category-branch summary span:first-of-type { flex:1; }
.b2b-category-count { flex:0 0 auto; min-width:20px; color:#8a949d; font-size:10px; text-align:right; }
.b2b-category-branch.selected > summary .b2b-category-count,.b2b-category-leaf.active .b2b-category-count,.b2b-category-all.active .b2b-category-count { color:#fff; }
.b2b-category-section-link { display:block; margin:2px 0 2px 15px; padding:4px 6px; color:var(--b2b-accent); font-size:10px; }
.b2b-category-empty { margin:14px 0 0; color:var(--b2b-muted); font-size:12px; text-align:center; }
.b2b-catalog-results { min-width:0; }
.b2b-results-toolbar { display:flex; align-items:center; justify-content:space-between; min-height:38px; padding:0 2px 8px; }
.b2b-results-toolbar div { display:flex; align-items:baseline; gap:5px; }
.b2b-results-toolbar strong { font-size:18px; }
.b2b-results-toolbar span,.b2b-results-toolbar em,.b2b-results-toolbar p { color:var(--b2b-muted); font-size:11px; font-style:normal; }
.b2b-results-toolbar p { margin:0; }
.b2b-live-dot { display:inline-block; width:7px; height:7px; margin-right:5px; border-radius:50%; background:#22a052; box-shadow:0 0 0 3px rgba(34,160,82,.12); }
.b2b-product-table { border:1px solid var(--b2b-line); border-radius:11px; background:#fff; overflow:hidden; }
.b2b-product-table__head,.b2b-product-row { display:grid; grid-template-columns:minmax(340px,1fr) 145px 190px 190px; align-items:center; column-gap:14px; }
.b2b-product-table.is-reference .b2b-product-table__head,.b2b-product-table.is-reference .b2b-product-row { grid-template-columns:minmax(380px,1fr) 160px minmax(270px,420px); }
.b2b-product-table__head { min-height:32px; padding:0 12px; border-bottom:1px solid var(--b2b-line); color:#7a848d; background:#f7f8f9; font-size:9px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
.b2b-product-row { min-height:72px; padding:8px 12px; }
.b2b-product-row + .b2b-product-row { border-top:1px solid #e9ecef; }
.b2b-product-row:hover { background:#fbfcfc; }
.b2b-product-main { display:grid; grid-template-columns:50px minmax(0,1fr); align-items:center; gap:10px; min-width:0; }
.b2b-product-image { display:block; width:50px; height:50px; border:1px solid #edf0f2; border-radius:7px; background:#f7f8f9; overflow:hidden; }
.b2b-product-image img { width:100%; height:100%; object-fit:contain; }
.b2b-product-copy { min-width:0; }
.b2b-product-meta { display:flex; gap:6px; margin-bottom:3px; color:#87919a; font-size:10px; white-space:nowrap; overflow:hidden; }
.b2b-product-meta span { overflow:hidden; text-overflow:ellipsis; }
.b2b-product-meta span + span::before { content:'·'; margin-right:6px; }
.b2b-product-meta .b2b-sku { color:#3d4b57; font-weight:700; }
.b2b-product-name { display:-webkit-box; color:#18212a; font-size:13px; font-weight:650; line-height:1.3; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }
.b2b-product-name:hover { color:var(--b2b-accent); }
.b2b-price-cell > strong,.b2b-price-cell > span,.b2b-stock-cell > strong,.b2b-stock-cell > span { display:block; }
.b2b-price-cell strong { font-size:15px; line-height:1.2; }
.b2b-price-cell strong small { font-size:9px; }
.b2b-price-cell span,.b2b-stock-cell span { margin-top:3px; color:#87919a; font-size:10px; }
.b2b-stock-cell strong { color:var(--b2b-green); font-size:12px; }
.b2b-stock-cell .b2b-live-dot { display:inline-block; vertical-align:1px; }
.b2b-buy-cell,.b2b-price-request-form { display:flex; align-items:center; gap:6px; }
.b2b-buy-cell input,.b2b-price-request-form input { width:52px; height:36px; padding:0 5px; border:1px solid #d8dde1; border-radius:7px; text-align:center; font-size:12px; }
.b2b-add-button,.b2b-price-request-form button { display:flex; align-items:center; justify-content:center; gap:6px; height:36px; flex:1; padding:0 12px; border:0; border-radius:7px; color:#fff; background:var(--b2b-accent); font-size:11px; font-weight:750; cursor:pointer; }
.b2b-add-button:hover,.b2b-price-request-form button:hover { background:#c93235; }
.b2b-add-button.is-added { background:var(--b2b-green); }
.b2b-price-request-state { padding:7px 9px; border-radius:7px; background:#fff5d8; }
.b2b-price-request-state strong,.b2b-price-request-state span { display:block; font-size:10px; }
.b2b-price-request-state p { margin:3px 0 0; font-size:10px; line-height:1.35; }
.b2b-empty-state { padding:70px 20px; border:1px dashed #ced5da; border-radius:11px; background:#fff; text-align:center; }
.b2b-empty-state i { font-size:34px; color:#a1a9b0; }
.b2b-empty-state h2 { margin:10px 0 5px; font-size:18px; }
.b2b-empty-state p { margin:0 0 14px; color:var(--b2b-muted); font-size:12px; }
.b2b-empty-state a { display:inline-flex; padding:9px 13px; border-radius:7px; color:#fff; background:var(--b2b-ink); font-size:12px; font-weight:700; }
.b2b-pagination { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-top:16px; }
.b2b-pagination__summary { color:#74787d; font-size:11px; }
.b2b-pagination__pages { display:flex; align-items:center; gap:4px; }
.b2b-pagination__link,.b2b-pagination__current,.b2b-pagination__disabled { display:grid; place-items:center; min-width:34px; height:34px; padding:0 8px; border:1px solid #d9dfe3; border-radius:7px; background:#fff; color:#27313a; font-size:11px; }
.b2b-pagination__link:hover { border-color:var(--b2b-ink); }
.b2b-pagination__current { border-color:var(--b2b-ink); color:#fff; background:var(--b2b-ink); }
.b2b-pagination__disabled { color:#b6bdc3; background:#f3f5f6; }
.b2b-pagination__ellipsis { padding:0 3px; color:#8b939a; }
.b2b-toast { position:fixed; z-index:100; right:22px; bottom:22px; max-width:340px; padding:11px 15px; border-radius:8px; color:#fff; background:#16202a; box-shadow:0 12px 34px rgba(0,0,0,.2); font-size:12px; font-weight:650; }
@media(max-width:1100px){.b2b-product-table__head,.b2b-product-row{grid-template-columns:minmax(290px,1fr) 125px 155px 165px}.b2b-catalog-layout{grid-template-columns:235px minmax(0,1fr)}.b2b-command-bar__filters{align-items:stretch;flex-direction:column}.b2b-filter-selects{justify-content:flex-start}}
@media(max-width:900px){.b2b-workspace,.b2b-topbar__inner{width:min(100% - 24px,1720px)}.b2b-topbar__company{display:none}.b2b-topbar__actions span{display:none}.b2b-page-heading{align-items:center}.b2b-mobile-catalog-button{display:flex;align-items:center;gap:6px;height:36px;padding:0 11px;border:1px solid #d8dde1;border-radius:7px;background:#fff;font-size:12px;font-weight:700}.b2b-catalog-layout{grid-template-columns:1fr}.b2b-category-panel{position:fixed;z-index:80;top:0;bottom:0;left:0;width:min(330px,88vw);max-height:none;border-radius:0;transform:translateX(-105%);transition:transform .2s ease}.b2b-category-panel.is-open{transform:translateX(0)}.b2b-category-panel__close{display:block}.b2b-catalog-overlay{position:fixed;z-index:70;inset:0;display:block;background:rgba(10,17,24,.42);opacity:0;pointer-events:none;transition:opacity .2s ease}.b2b-catalog-overlay.is-open{opacity:1;pointer-events:auto}.b2b-product-table__head{display:none}.b2b-product-row{grid-template-columns:minmax(260px,1fr) 120px 150px;row-gap:7px}.b2b-buy-cell{grid-column:2/4}.b2b-product-table.is-reference .b2b-product-row{grid-template-columns:minmax(260px,1fr) 140px}.b2b-product-table.is-reference .b2b-request-cell{grid-column:1/3}}
@media(max-width:640px){
    .b2b-topbar{height:58px}
    .b2b-topbar__inner{width:calc(100% - 20px)}
    .b2b-brand{gap:8px;padding-right:6px;border:0}
    .b2b-brand img{width:105px}
    .b2b-brand span{padding:5px 7px}
    .b2b-topbar__actions{gap:2px}
    .b2b-topbar__link,.b2b-topbar__logout{min-height:34px;padding:0 8px;font-size:11px}
    .b2b-topbar__logout{display:none}
    .b2b-workspace{width:calc(100% - 20px);padding-top:14px}
    .b2b-page-heading{margin-bottom:10px}
    .b2b-page-heading p{display:none}
    .b2b-page-heading h1{font-size:18px}
    .b2b-mobile-catalog-button{display:none}
    .b2b-command-bar{margin-bottom:16px;padding:0;border:0;background:transparent;box-shadow:none}
    .b2b-search{display:grid;grid-template-columns:40px minmax(0,1fr) 82px;height:48px;overflow:visible}
    .b2b-search>i{display:grid;place-items:center;height:44px;margin:0;text-align:center}
    .b2b-search>input{width:100%;height:44px;padding:0 7px;font-size:13px;line-height:44px;box-sizing:border-box}
    .b2b-search>button{width:auto;min-width:0;height:44px;margin:0;padding:0 10px;border-radius:0 6px 6px 0;font-size:12px}
    .b2b-search>kbd{display:none}
    .b2b-search__clear{position:absolute;z-index:2;right:84px;width:28px;height:28px;margin:0;background:#fff}
    .b2b-search-suggest{top:calc(100% + 7px)}
    .b2b-command-bar__filters{gap:8px;margin-top:8px}
    .b2b-mode-switch{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);width:100%;padding:2px;box-sizing:border-box}
    .b2b-mode-switch a{display:grid;place-items:center;min-height:38px;padding:6px 8px;text-align:center;white-space:normal;line-height:1.15}
    .b2b-filter-selects{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);width:100%}
    .b2b-filter-selects label{min-width:0}
    .b2b-filter-selects label span{margin-bottom:2px;font-size:9px}
    .b2b-filter-selects select{height:36px;min-width:0;background-color:#fff}
    .b2b-reset-filters{height:auto;padding:9px 3px}
    .b2b-mobile-category-showcase{display:block;margin:0 0 18px;padding:0}
    .b2b-mobile-category-showcase__heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-top:2px}
    .b2b-mobile-category-showcase__heading strong{font-size:13px}
    .b2b-mobile-category-showcase__heading button{display:flex;align-items:center;gap:4px;padding:4px 0;border:0;color:var(--b2b-accent);background:transparent;font-size:11px;font-weight:700}
    .b2b-mobile-category-showcase__heading button b{font-size:13px;font-weight:500}
    .b2b-mobile-category-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px 7px}
    .b2b-mobile-category-tile{display:flex;min-width:0;flex-direction:column;align-items:center;gap:5px;padding:0;border:0;color:#2e3740;background:transparent;text-align:center;font:inherit}
    .b2b-mobile-category-tile__image{position:relative;display:grid;place-items:center;width:100%;aspect-ratio:1;border:1px solid #e8ecef;border-radius:13px;background:#fff;overflow:hidden}
    .b2b-mobile-category-tile__image img{width:100%;height:100%;object-fit:contain;padding:8px;box-sizing:border-box}
    .b2b-mobile-category-tile__image b{display:none}
    .b2b-mobile-category-tile>span:last-child{display:-webkit-box;min-height:28px;font-size:10px;font-weight:600;line-height:1.2;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden}
    .b2b-mobile-category-tile.active .b2b-mobile-category-tile__image{border-color:var(--b2b-accent);box-shadow:0 0 0 2px rgba(223,63,66,.12)}
    .b2b-mobile-category-tile--all{cursor:pointer}
    .b2b-mobile-category-tile--all .b2b-mobile-category-tile__image{color:#fff;background:var(--b2b-ink)}
    .b2b-mobile-category-tile--all i{font-size:27px}
    .b2b-results-toolbar{align-items:flex-start;flex-direction:column;padding:11px 3px 10px;border-top:1px solid #dfe4e8}
    .b2b-results-toolbar p{margin-top:5px}
    .b2b-product-table{display:grid;gap:9px;border:0;border-radius:0;background:transparent;overflow:visible}
    .b2b-product-row,.b2b-product-table.is-reference .b2b-product-row{display:grid;grid-template-columns:88px minmax(0,1fr);gap:7px 11px;padding:11px 10px}
    .b2b-product-table .b2b-product-row,.b2b-product-table .b2b-product-row+.b2b-product-row{border:1px solid #dfe4e8;border-radius:11px;background:#fff;box-shadow:0 2px 8px rgba(20,31,42,.035)}
    .b2b-product-main{display:contents}
    .b2b-product-image{grid-column:1;grid-row:1/3;width:88px;height:76px;border:0;background:#f8f9fa}
    .b2b-product-copy{grid-column:2;grid-row:1;align-self:start}
    .b2b-product-meta{gap:5px;margin-bottom:4px;font-size:9px}
    .b2b-product-name{font-size:12px;line-height:1.25;-webkit-line-clamp:3}
    .b2b-price-cell{grid-column:2;grid-row:2;align-self:end;min-width:0;padding-top:2px}
    .b2b-price-cell strong{font-size:14px}
    .b2b-stock-cell{grid-column:1/3;display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0;padding-top:3px;text-align:left}
    .b2b-stock-cell>strong{flex:0 0 auto;white-space:nowrap}
    .b2b-stock-cell>span{min-width:0;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:right}
    .b2b-buy-cell,.b2b-product-table.is-reference .b2b-request-cell{grid-column:1/3}
    .b2b-product-table.is-reference .b2b-price-cell{grid-column:2;grid-row:2}
    .b2b-buy-cell input,.b2b-price-request-form input{width:58px;height:40px}
    .b2b-add-button,.b2b-price-request-form button{height:40px}
    .b2b-pagination{align-items:flex-start;flex-direction:column}
    .b2b-pagination__pages{max-width:100%;overflow:auto}
    .b2b-suggest-item{grid-template-columns:36px minmax(0,1fr)}
    .b2b-suggest-item img{width:36px;height:36px}
    .b2b-suggest-item__price{display:none}
}
@media(max-width:380px){
    .b2b-brand img{width:94px}
    .b2b-topbar__link{padding:0 7px}
    .b2b-page-heading h1{font-size:18px}
    .b2b-mobile-catalog-button{font-size:11px}
    .b2b-search{grid-template-columns:36px minmax(0,1fr) 72px}
    .b2b-search>input{font-size:12px}
    .b2b-search>button{font-size:11px}
    .b2b-search__clear{right:74px}
}
</style>

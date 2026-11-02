<style>
    .installer-cabinet { --ic-accent:#f28c00;--ic-ink:#171717;--ic-muted:#737373;--ic-line:#e8e6e2;--ic-soft:#f7f6f3; }
    .installer-cabinet > .flat-spacing { background:linear-gradient(180deg,#faf9f7 0,#fff 360px); }
    .installer-cabinet__hero { position:relative;isolation:isolate;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:28px;padding:30px 32px;border-radius:24px;background:linear-gradient(120deg,#111 0%,#201a16 58%,#3e2918 100%);color:#fff;box-shadow:0 18px 45px rgba(24,18,12,.14); }
    .installer-cabinet__hero::after { content:"";position:absolute;z-index:-1;right:-80px;top:-140px;width:360px;height:360px;border-radius:50%;background:radial-gradient(circle,rgba(242,140,0,.28),rgba(242,140,0,0) 70%); }
    .installer-cabinet__hero h2 { letter-spacing:-.03em; }
    .installer-cabinet__hero p { color:rgba(255,255,255,.68); }
    .installer-cabinet__status { display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border-radius:999px;background:rgba(255,255,255,.1);font-size:11px;font-weight:700; }
    .installer-cabinet__grid { display:grid;grid-template-columns:minmax(0,1.55fr) minmax(340px,.65fr);gap:26px;align-items:start; }
    .installer-cabinet__card { padding:28px;border:1px solid var(--ic-line);border-radius:22px;background:#fff;box-shadow:0 12px 34px rgba(24,18,12,.045); }
    .installer-cabinet__section-head { display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:20px; }
    .installer-cabinet__section-head p { color:#777;font-size:13px; }
    .installer-cabinet__preview-card { position:sticky;top:22px;padding:0;overflow:hidden; }
    .installer-cabinet__photo { position:relative;width:100%;aspect-ratio:4/3;overflow:hidden;background:linear-gradient(135deg,#efe7dc,#d7c3a9); }
    .installer-cabinet__photo::after { content:"";position:absolute;inset:auto 0 0;height:45%;background:linear-gradient(transparent,rgba(0,0,0,.52));pointer-events:none; }
    .installer-cabinet__photo img { width:100%;height:100%;object-fit:cover; }
    .installer-cabinet__photo-fallback { display:grid;place-items:center;width:100%;height:100%;font-size:76px;font-weight:700;letter-spacing:-.06em;color:#6d5239; }
    .installer-cabinet__photo-label { position:absolute;z-index:1;left:18px;bottom:16px;display:flex;align-items:center;gap:7px;color:#fff;font-size:12px;font-weight:700; }
    .installer-cabinet__preview-body { padding:22px; }
    .installer-cabinet__preview-kicker { color:#a86200;font-size:11px;font-weight:800;letter-spacing:.08em;text-transform:uppercase; }
    .installer-cabinet__metrics { display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin:18px 0; }
    .installer-cabinet__metric { padding:13px;border-radius:13px;background:var(--ic-soft); }
    .installer-cabinet__metric strong { display:block;font-size:20px;line-height:1;color:var(--ic-ink); }
    .installer-cabinet__metric span { display:block;margin-top:5px;color:var(--ic-muted);font-size:11px; }
    .installer-cabinet__check-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px; }
    .installer-cabinet__check { display:flex;align-items:center;gap:9px;padding:12px 14px;border:1px solid var(--ic-line);border-radius:12px;background:#fff;cursor:pointer;transition:.2s ease; }
    .installer-cabinet__check:has(input:checked) { border-color:#f2c174;background:#fff9ef; }
    .installer-cabinet__check input { width:17px;height:17px; }
    .installer-cabinet__work { padding:18px;border:1px solid var(--ic-line);border-radius:16px;background:#fff;transition:.2s ease; }
    .installer-cabinet__work:hover { border-color:#d8d2c9;box-shadow:0 10px 24px rgba(24,18,12,.05); }
    .installer-cabinet__work + .installer-cabinet__work { margin-top:14px; }
    .installer-cabinet__work-head { display:grid;grid-template-columns:110px 1fr auto;gap:16px;align-items:center; }
    .installer-cabinet__work-thumb { width:110px;aspect-ratio:16/10;border-radius:10px;overflow:hidden;background:#eee; }
    .installer-cabinet__work-thumb img { width:100%;height:100%;object-fit:cover; }
    .installer-cabinet__work summary { cursor:pointer;font-weight:700;color:#a85d00;list-style:none; }
    .installer-cabinet__work summary::-webkit-details-marker { display:none; }
    .installer-cabinet__work details[open] summary { margin-bottom:18px; }
    .installer-cabinet__hint { padding:14px 16px;border:1px solid #f4dfbd;border-radius:13px;background:#fff8ed;color:#714600;font-size:12px;line-height:1.55; }
    .installer-cabinet .tf-field > label { display:block;margin-bottom:7px;color:#34302b;font-size:12px;font-weight:650; }
    .installer-cabinet .tf-field > input:not([type="checkbox"]):not([type="file"]),
    .installer-cabinet .tf-field > select,
    .installer-cabinet .tf-field > textarea { width:100%;min-height:48px;padding:12px 14px;border:1px solid #ddd9d2;border-radius:12px;background:#fff;color:#222;outline:none;transition:border-color .2s,box-shadow .2s; }
    .installer-cabinet .tf-field > textarea { min-height:128px;resize:vertical; }
    .installer-cabinet .tf-field > input:focus,.installer-cabinet .tf-field > select:focus,.installer-cabinet .tf-field > textarea:focus { border-color:#d78a20;box-shadow:0 0 0 4px rgba(242,140,0,.09); }
    .installer-cabinet fieldset[disabled] input,.installer-cabinet fieldset[disabled] select,.installer-cabinet fieldset[disabled] textarea { opacity:1;color:#35322e;background:#f7f6f3;border-color:#e5e2dc; }
    .installer-cabinet textarea { min-height:120px; }
    .installer-cabinet input[type="file"] { width:100%;padding:12px;border:1px dashed #d8d3cb;border-radius:12px;background:#faf9f7; }
    @media(max-width:991px){
        .installer-cabinet__grid { grid-template-columns:1fr; }
        .installer-cabinet__hero { align-items:flex-start;flex-direction:column; }
        .installer-cabinet__preview-card { position:static; }
    }
    @media(max-width:575px){
        .installer-cabinet__hero,.installer-cabinet__card { padding:19px; }
        .installer-cabinet__preview-card { padding:0; }
        .installer-cabinet__check-grid { grid-template-columns:1fr; }
        .installer-cabinet__work-head { grid-template-columns:82px 1fr; }
        .installer-cabinet__work-thumb { width:82px; }
        .installer-cabinet__work-head > :last-child { grid-column:1/-1; }
    }
</style>

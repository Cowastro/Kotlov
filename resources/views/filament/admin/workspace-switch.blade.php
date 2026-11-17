@if (\App\Services\StaffCatalogWorkspace::allowed(auth()->user()))
    <nav aria-label="Рабочий режим" style="display:flex;align-items:center;gap:3px;padding:3px;border:1px solid #94a3b855;border-radius:10px;font-size:12px;white-space:nowrap">
        <span aria-current="page" style="padding:7px 10px;border-radius:7px;background:#f59e0b22;font-weight:700">CRM</span>
        <a href="{{ route('b2b.workspace') }}" style="padding:7px 10px;border-radius:7px;font-weight:600" title="Каталог для больших заявок с сохранением и печатью">B2B-подбор ↗</a>
    </nav>
@endif

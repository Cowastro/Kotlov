<div style="display:grid;gap:.75rem">
    @foreach($contracts as $contract)
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;border:1px solid rgba(148,163,184,.25);border-radius:.75rem">
            <div>
                <div style="font-weight:700">{{ $contract->number }}</div>
                <div style="font-size:.8rem;color:#94a3b8">
                    {{ $contract->contract_date?->format('d.m.Y') }} · {{ $contract->template_mode === 'client' ? 'форма клиента' : 'наша форма' }} · {{ \App\Models\CommercialContract::STATUSES[$contract->status] ?? $contract->status }}
                    @if($contract->creator) · {{ $contract->creator->name }} @endif
                    @if($contract->generated_at) · создан {{ $contract->generated_at->format('d.m.Y H:i') }} @endif
                </div>
                @if($contract->template_mode === 'client' && count((array) data_get($contract->terms_snapshot, 'replacement_report.unresolved_tokens', [])))
                    <div style="margin-top:.25rem;font-size:.78rem;color:#f59e0b">Проверьте незаполненные метки: {{ implode(', ', (array) data_get($contract->terms_snapshot, 'replacement_report.unresolved_tokens', [])) }}</div>
                @endif
            </div>
            <div style="display:flex;gap:.5rem">
                @if($contract->generated_pdf_path)
                    <a href="{{ route('admin.commercial-contracts.pdf', $contract) }}" target="_blank" rel="noopener" style="padding:.45rem .75rem;border:1px solid rgba(148,163,184,.35);border-radius:.5rem;font-weight:600">Открыть PDF</a>
                @endif
                @if($contract->generated_docx_path)
                    <a href="{{ route('admin.commercial-contracts.docx', $contract) }}" style="padding:.45rem .75rem;border:1px solid rgba(148,163,184,.35);border-radius:.5rem;font-weight:600">Скачать DOCX</a>
                @endif
                @if($contract->source_template_path)
                    <a href="{{ route('admin.commercial-contracts.source', $contract) }}" style="padding:.45rem .75rem;border:1px solid rgba(148,163,184,.35);border-radius:.5rem;font-weight:600">Исходник клиента</a>
                @endif
            </div>
        </div>
    @endforeach
</div>

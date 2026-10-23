<div style="display:grid;gap:.75rem">
    @foreach($contracts as $contract)
        <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;border:1px solid rgba(148,163,184,.25);border-radius:.75rem">
            <div>
                <div style="font-weight:700">{{ $contract->number }}</div>
                <div style="font-size:.8rem;color:#94a3b8">
                    {{ $contract->contract_date?->format('d.m.Y') }} · {{ \App\Models\CommercialContract::STATUSES[$contract->status] ?? $contract->status }}
                    @if($contract->creator) · {{ $contract->creator->name }} @endif
                    @if($contract->generated_at) · создан {{ $contract->generated_at->format('d.m.Y H:i') }} @endif
                </div>
            </div>
            <div style="display:flex;gap:.5rem">
                <a href="{{ route('admin.commercial-contracts.pdf', $contract) }}" target="_blank" rel="noopener" style="padding:.45rem .75rem;border:1px solid rgba(148,163,184,.35);border-radius:.5rem;font-weight:600">Открыть PDF</a>
                <a href="{{ route('admin.commercial-contracts.docx', $contract) }}" style="padding:.45rem .75rem;border:1px solid rgba(148,163,184,.35);border-radius:.5rem;font-weight:600">Скачать DOCX</a>
            </div>
        </div>
    @endforeach
</div>

<div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:12px">
    <div style="color:#64748b;font-size:12px">Решение: <strong style="color:inherit">{{ $decision['label'] }}</strong> · Пакет {{ $decision['batch_code'] }}</div>
    <a href="{{ route('admin.market-research-reports.pdf', $report) }}" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;border-radius:8px;background:#e85520;color:#fff;padding:8px 12px;font-size:12px;font-weight:700;text-decoration:none">Открыть PDF</a>
</div>

@include('filament.market.live-price-research', ['research' => $research, 'pricing' => $pricing])

<div class="market-report" style="margin-top:16px">
    <section class="mr-panel">
        <h3 class="mr-section-title">Зафиксированное решение</h3>
        <dl class="mr-facts" style="grid-template-columns:minmax(160px,.45fr) minmax(0,1.55fr)">
            <dt>Причина проверки</dt><dd style="text-align:left">{{ $decision['review_reason'] ?: '—' }}</dd>
            <dt>Почему принято решение</dt><dd style="text-align:left">{{ $decision['reason'] ?: '—' }}</dd>
            <dt>Подтверждение менеджера</dt><dd style="text-align:left">{{ $decision['requires_confirmation'] ? 'Требуется' : 'Не требуется' }}</dd>
        </dl>
    </section>
</div>

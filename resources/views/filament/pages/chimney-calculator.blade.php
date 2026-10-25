<x-filament-panels::page>
    <style>
        .chimney-request-bar{display:grid;grid-template-columns:minmax(190px,1fr) minmax(220px,1.3fr) repeat(2,minmax(130px,.65fr));gap:1px;margin-bottom:16px;overflow:hidden;border:1px solid rgba(148,163,184,.25);border-radius:14px;background:rgba(148,163,184,.25)}
        .chimney-request-bar>div{padding:14px 16px;background:var(--gray-900,#18181b)}.chimney-request-bar span{display:block;margin-bottom:4px;color:#9ca3af;font-size:12px}.chimney-request-bar strong{color:#fff}.chimney-request-bar a{color:#f59e0b}.chimney-requests-intro{margin-bottom:16px;color:#9ca3af}
        .chimney-calculator-frame{display:block;width:100%;height:calc(100vh - 10.5rem);min-height:760px;border:1px solid rgba(148,163,184,.3);border-radius:16px;background:#fafaf8}
        @media(max-width:1024px){.chimney-request-bar{grid-template-columns:1fr 1fr}.chimney-calculator-frame{height:calc(100vh - 8rem);min-height:680px;border-radius:10px}}
    </style>
    @if($showRequests)
        <p class="chimney-requests-intro">Все клиентские заявки и расчёты менеджеров. Нажмите на строку, чтобы открыть состав прямо в калькуляторе, скорректировать его и создать заказ.</p>
        {{ $this->table }}
    @else
        @if($calculation = $this->currentCalculation())
            <div class="chimney-request-bar">
                <div><span>Заявка</span><strong>{{ $calculation->number }}</strong></div>
                <div><span>Клиент</span><strong>{{ $calculation->customer_name }}</strong><br><a href="tel:{{ preg_replace('/[^0-9+]/', '', $calculation->customer_phone) }}">{{ $calculation->customer_phone }}</a></div>
                <div><span>Статус</span><strong>{{ \App\Models\ChimneyCalculationRequest::STATUSES[$calculation->status] ?? $calculation->status }}</strong></div>
                <div><span>Итого</span><strong>{{ number_format((float) $calculation->total, 2, ',', ' ') }} BYN</strong></div>
            </div>
        @endif
        <iframe
            class="chimney-calculator-frame"
            src="{{ $this->calculatorUrl() }}"
            title="Калькулятор дымохода"
        ></iframe>
    @endif
</x-filament-panels::page>

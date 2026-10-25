<x-filament-panels::page>
    <style>
        .chimney-calculator-frame{display:block;width:100%;height:calc(100vh - 10.5rem);min-height:760px;border:1px solid rgba(148,163,184,.3);border-radius:16px;background:#fafaf8}
        @media(max-width:1024px){.chimney-calculator-frame{height:calc(100vh - 8rem);min-height:680px;border-radius:10px}}
    </style>
    <iframe
        class="chimney-calculator-frame"
        src="{{ $this->calculatorUrl() }}"
        title="Калькулятор дымохода"
    ></iframe>
</x-filament-panels::page>

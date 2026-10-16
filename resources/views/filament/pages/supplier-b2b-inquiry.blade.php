<x-filament-panels::page>
    <style>
        .b2b-inquiry-wrap{display:grid;gap:20px;max-width:1250px}.b2b-inquiry-card{padding:22px;border:1px solid #d1d5db;border-radius:12px;background:#fff}.dark .b2b-inquiry-card{background:#18181b;border-color:#3f3f46}.b2b-inquiry-card h2{font-size:18px;font-weight:700;margin-bottom:8px}.b2b-inquiry-muted{color:#64748b;font-size:13px}.dark .b2b-inquiry-muted{color:#a1a1aa}.b2b-inquiry-list{display:grid;margin-top:14px}.b2b-inquiry-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;padding:13px 0;border-bottom:1px solid #71717a44}.b2b-inquiry-row:last-child{border-bottom:0}.b2b-inquiry-main{min-width:0}.b2b-inquiry-main strong,.b2b-inquiry-main span{display:block}.b2b-inquiry-main strong{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.b2b-inquiry-actions{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:16px}@media(max-width:700px){.b2b-inquiry-card{padding:16px}.b2b-inquiry-row{grid-template-columns:1fr}.b2b-inquiry-row .fi-btn{justify-self:start}}
    </style>

    <div class="b2b-inquiry-wrap">
        <section class="b2b-inquiry-card">
            <h2>Запрос партнёрской цены и наличия</h2>
            <p class="b2b-inquiry-muted">Отдельная рабочая форма для запроса актуальной B2B-цены и наличия у любого поставщика. Поставщика можно выбрать из базы или указать произвольно.</p>
            <div class="b2b-inquiry-actions">
                <x-filament::button wire:click="mountAction('create_b2b_inquiry')" icon="heroicon-o-plus">Создать B2B-заявку</x-filament::button>
                <span class="b2b-inquiry-muted">Письмо можно сразу отправить или сохранить как черновик в разделе «Почта».</span>
            </div>
        </section>

        <section class="b2b-inquiry-card">
            <h2>Последние B2B-заявки</h2>
            <p class="b2b-inquiry-muted">Здесь отображаются заявки, созданные в этом разделе и из анализа рынка.</p>
            <div class="b2b-inquiry-list">
                @forelse($this->recentInquiries as $message)
                    <div class="b2b-inquiry-row" wire:key="supplier-b2b-inquiry-{{ $message->id }}">
                        <div class="b2b-inquiry-main">
                            <strong>{{ $message->subject ?: $message->thread?->subject ?: 'Без темы' }}</strong>
                            <span class="b2b-inquiry-muted">
                                {{ ($message->sent_at ?: $message->received_at ?: $message->created_at)?->format('d.m.Y H:i') }}
                                · {{ $message->thread?->participant_name ?: $message->thread?->participant_email ?: 'Поставщик не указан' }}
                                · {{ $message->direction === 'draft' ? 'Черновик' : 'Отправлено' }}
                            </span>
                        </div>
                        @if($message->thread)
                            <x-filament::button tag="a" :href="$this->threadUrl($message->thread_id)" size="sm" color="gray">Открыть</x-filament::button>
                        @endif
                    </div>
                @empty
                    <p class="b2b-inquiry-muted">Заявок пока нет.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>

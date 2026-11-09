<x-filament-panels::page>
    <style>
        .rs-wrap{display:grid;gap:20px;max-width:1300px}.rs-card{padding:22px;border:1px solid #d1d5db;border-radius:12px;background:#fff}.dark .rs-card{background:#18181b;border-color:#3f3f46}
        .rs-card h2{font-size:18px;font-weight:700;margin-bottom:8px}.rs-muted{color:#64748b;font-size:13px}.dark .rs-muted{color:#a1a1aa}
        .rs-inputs{display:grid;grid-template-columns:1fr 1.3fr;gap:16px;margin-top:12px}.rs-drop{display:flex;flex-direction:column;justify-content:center;gap:6px;min-height:150px;padding:16px;border:2px dashed #9ca3af;border-radius:10px;text-align:center}
        .rs-text{width:100%;min-height:150px;padding:12px;border:1px solid #9ca3af;border-radius:10px;background:transparent;color:inherit;font-size:14px}
        .rs-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:16px}.rs-warning{color:#b45309}.dark .rs-warning{color:#fbbf24}
        .rs-table{width:100%;border-collapse:collapse;font-size:13px}.rs-table th,.rs-table td{padding:10px 8px;border-bottom:1px solid #71717a44;text-align:left;vertical-align:top}.rs-table th{font-weight:600;white-space:nowrap}
        .rs-req{font-weight:600}.rs-qty{width:84px;padding:6px 8px;border:1px solid #9ca3af;border-radius:8px;background:transparent;color:inherit}
        .rs-select{width:100%;min-width:320px;padding:7px 8px;border:1px solid #9ca3af;border-radius:8px;background:transparent;color:inherit}.dark .rs-select option{background:#18181b}
        .rs-stock{display:inline-block;margin-top:4px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600}.rs-stock--in{background:#dcfce7;color:#166534}.rs-stock--order{background:#fef3c7;color:#92400e}
        .rs-total{display:flex;justify-content:flex-end;gap:16px;align-items:center;margin-top:14px;font-size:15px}.rs-scroll{overflow:auto}
        @media(max-width:900px){.rs-inputs{grid-template-columns:1fr}}
    </style>
    <div class="rs-wrap">
        <form wire:submit="select" class="rs-card">
            <h2>Загрузите заявку — подберём товары и соберём КП</h2>
            <p class="rs-muted">Скан, PDF или фото заявки либо её текст. ИИ распознаёт позиции и подбирает по каждой товар из каталога: только активные карточки с ценой, сначала в наличии, затем дешевле. В КП — розничные цены. Каталог не изменяется.</p>
            <div class="rs-inputs">
                <label class="rs-drop">
                    <strong>Файл заявки</strong>
                    <input type="file" wire:model="requestFile" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <span class="rs-muted">PDF, JPG, PNG, WEBP — до 15 МБ</span>
                    <span wire:loading wire:target="requestFile" class="rs-muted">Загружаем файл…</span>
                    @error('requestFile') <span role="alert" class="rs-warning">{{ $message }}</span> @enderror
                </label>
                <div>
                    <label for="rs-text" class="rs-muted">…или вставьте текст заявки</label>
                    <textarea id="rs-text" wire:model="text" class="rs-text" maxlength="12000" placeholder="Радиатор биметаллический 10 секций — 12 шт&#10;Труба ПП ДУ32 — 36 м&#10;Отвод ПП 90° ДУ32 — 24 шт"></textarea>
                </div>
            </div>
            <div class="rs-actions">
                <x-filament::button type="submit" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="select,requestFile">Распознать и подобрать</x-filament::button>
                @if ($lines)<x-filament::button type="button" color="gray" wire:click="startNew">Новая заявка</x-filament::button>@endif
                <span wire:loading wire:target="select" class="rs-muted" role="status">Распознаём заявку и подбираем товары — до минуты…</span>
            </div>
        </form>

        @if ($lines)
            <section class="rs-card">
                <h2>Подбор по позициям</h2>
                <p class="rs-muted">Распознано позиций: {{ count($lines) }}{{ $method ? ' · '.$method : '' }}. Проверьте выбор, особенно строки с пометкой, поменяйте товар или количество — и создайте КП. «Не включать» — строка не попадёт в КП.</p>
                <div class="rs-scroll" style="margin-top:12px">
                    <table class="rs-table">
                        <thead><tr><th>№</th><th>В заявке</th><th>Кол-во</th><th>Товар из каталога</th><th>Цена</th><th>Сумма</th></tr></thead>
                        <tbody>
                            @foreach ($lines as $index => $line)
                                @php
                                    $chosen = $line['candidates'][(int) ($choice[$index] ?? 0)] ?? null;
                                    $options = collect($line['candidates']);
                                    $ordered = collect([$line['product_id'], ...$line['alternatives']])->filter()->unique()
                                        ->map(fn ($id) => $options->get($id))->filter()
                                        ->concat($options->except([$line['product_id'], ...$line['alternatives']])->values());
                                @endphp
                                <tr wire:key="rs-line-{{ $index }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="rs-req">{{ $line['name'] }}</span>@if ($line['unit'])<br><span class="rs-muted">{{ $line['unit'] }}</span>@endif</td>
                                    <td><input type="number" step="any" min="0.001" class="rs-qty" wire:model.live.debounce.400ms="quantity.{{ $index }}" aria-label="Количество, строка {{ $index + 1 }}"></td>
                                    <td>
                                        @if ($options->isEmpty())
                                            <span class="rs-warning">Не найдено в каталоге</span>
                                        @else
                                            <select class="rs-select" wire:model.live="choice.{{ $index }}" aria-label="Товар, строка {{ $index + 1 }}">
                                                <option value="0">— Не включать в КП —</option>
                                                @foreach ($ordered as $candidate)
                                                    <option value="{{ $candidate['id'] }}">{{ $candidate['id'] === $line['product_id'] ? '★ ' : '' }}{{ $candidate['name'] }} · {{ number_format($candidate['price'], 2, ',', ' ') }} BYN · {{ $candidate['in_stock'] ? 'в наличии' : 'под заказ' }}</option>
                                                @endforeach
                                            </select>
                                            @if ($chosen)
                                                <span class="rs-stock {{ $chosen['in_stock'] ? 'rs-stock--in' : 'rs-stock--order' }}">{{ $chosen['availability'] }}</span>
                                                <a class="rs-muted" href="{{ url('/admin/products/'.$chosen['id']) }}" target="_blank" rel="noopener">карточка ↗</a>
                                            @endif
                                        @endif
                                        @if ($line['note'])<div class="rs-warning" style="margin-top:4px">⚠ {{ $line['note'] }}</div>@endif
                                    </td>
                                    <td style="white-space:nowrap">{{ $chosen ? number_format($chosen['price'], 2, ',', ' ').' BYN' : '—' }}</td>
                                    <td style="white-space:nowrap">{{ $chosen ? number_format($chosen['price'] * (float) ($quantity[$index] ?? 0), 2, ',', ' ').' BYN' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="rs-total">
                    <span>Итого по рознице: <strong>{{ number_format($this->total(), 2, ',', ' ') }} BYN</strong></span>
                    <x-filament::button icon="heroicon-o-document-text" wire:click="createOffer" wire:loading.attr="disabled">Создать КП</x-filament::button>
                </div>
                <p class="rs-muted" style="margin-top:10px">★ — вариант ИИ. Это предварительный подбор: параметры (диаметры, резьбы, число секций) проверьте по карточке перед отправкой клиенту.</p>
            </section>
        @endif
    </div>
</x-filament-panels::page>

<x-filament-panels::page>
    <form wire:submit="applyPeriod" class="flex flex-wrap items-end gap-4">
        <label>С <x-filament::input.wrapper><x-filament::input type="date" wire:model="from" required /></x-filament::input.wrapper></label>
        <label>По <x-filament::input.wrapper><x-filament::input type="date" wire:model="to" required /></x-filament::input.wrapper></label>
        <x-filament::button type="submit">Показать</x-filament::button>
    </form>
    @error('from') <p>{{ $message }}</p> @enderror
    @error('to') <p>{{ $message }}</p> @enderror
    <p>Заявки отбираются по дате создания. Первый платёж учитывается до конца выбранного периода. Одобрение определяется по текущему статусу компании.</p>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead><tr>
                <th>Тип партнёра</th><th>Источник</th><th>Заявки</th><th>Одобрены</th><th>Первый оплаченный заказ</th>
                <th>Заявка → одобрение</th><th>Одобрение → оплата</th>
            </tr></thead>
            <tbody>
                @forelse($this->rows() as $row)
                    <tr>
                        <td>{{ \App\Models\B2bClient::PARTNER_TYPES[$row->partner_type] ?? $row->partner_type }}</td>
                        <td>{{ $row->source ?: 'прямой заход' }}</td>
                        <td>{{ $row->applications }}</td><td>{{ $row->approved }}</td><td>{{ $row->paid }}</td>
                        <td>{{ number_format(100 * $row->approved / $row->applications, 1, ',') }}%</td>
                        <td>{{ $row->approved ? number_format(100 * $row->paid / $row->approved, 1, ',').'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">За выбранный период заявок нет.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-filament-panels::page>

<?php

namespace App\Services;

use App\Models\SupplierSyncChange;
use App\Models\SupplierSyncRun;
use Illuminate\Support\Collection;

class SupplierSyncRunTextExporter
{
    public function filename(SupplierSyncRun $run): string
    {
        $date = ($run->started_at ?? now())->format('Y-m-d_H-i-s');
        $command = preg_replace('/[^a-z0-9_-]+/i', '-', (string) $run->command);

        return trim((string) $command, '-').'-'.$date.'.txt';
    }

    public function render(SupplierSyncRun $run): string
    {
        $changes = $this->changes($run);
        $lines = [
            'ЖУРНАЛ ИЗМЕНЕНИЙ СИНХРОНИЗАЦИИ',
            str_repeat('=', 42),
            'Дата запуска: '.($run->started_at?->timezone('Europe/Minsk')->format('d.m.Y H:i:s') ?? '—'),
            'Команда: '.($run->command ?: '—'),
            'Поставщик: '.($run->supplier_names ?: '—'),
            'Статус: '.$this->status($run->status),
            'Всего изменений: '.$run->changes_count,
            'Изменений цен: '.$run->price_changes_count,
            'Изменений остатков: '.$run->stock_changes_count,
            '',
        ];

        if ($changes->isEmpty()) {
            $lines[] = 'Цены и остатки не изменились.';

            return "\xEF\xBB\xBF".implode(PHP_EOL, $lines).PHP_EOL;
        }

        foreach ($changes as $index => $change) {
            $flags = $change->change_flags ?? [];
            $lines[] = ($index + 1).'. '.($change->product_name ?: 'Несвязанный товар');
            $lines[] = '   Поставщик: '.($change->supplier_name ?: '—');
            $lines[] = '   SKU: '.($change->product_sku ?: '—')
                .'; артикул поставщика: '.($change->supplier_article ?: '—');
            $lines[] = '   Что изменилось: '.implode(', ', array_map($this->flagLabel(...), $flags));

            $this->appendChange($lines, $flags, 'supplier_price', 'Закупочная цена',
                $this->money($change->supplier_price_before), $this->money($change->supplier_price_after));
            $this->appendChange($lines, $flags, 'retail_price', 'Розничная цена',
                $this->money($change->retail_price_before), $this->money($change->retail_price_after));
            $this->appendChange($lines, $flags, 'in_stock', 'Наличие',
                $this->stock($change->in_stock_before), $this->stock($change->in_stock_after));
            $this->appendChange($lines, $flags, 'stock_quantity', 'Количество',
                $this->value($change->stock_quantity_before), $this->value($change->stock_quantity_after));
            $this->appendChange($lines, $flags, 'stock_status', 'Статус склада',
                $this->value($change->stock_status_before), $this->value($change->stock_status_after));
            $this->appendChange($lines, $flags, 'availability_status', 'Статус на сайте',
                $this->value($change->availability_before), $this->value($change->availability_after));
            $lines[] = '';
        }

        return "\xEF\xBB\xBF".implode(PHP_EOL, $lines).PHP_EOL;
    }

    /** @return Collection<int, SupplierSyncChange> */
    private function changes(SupplierSyncRun $run): Collection
    {
        if ($run->relationLoaded('changes')) {
            return $run->changes->sortBy('id')->values();
        }

        return $run->changes()->orderBy('id')->get();
    }

    /** @param array<int, string> $lines @param array<int, string> $flags */
    private function appendChange(array &$lines, array $flags, string $flag, string $label, string $before, string $after): void
    {
        if (in_array($flag, $flags, true)) {
            $lines[] = "   {$label}: {$before} → {$after}";
        }
    }

    private function flagLabel(string $flag): string
    {
        return match ($flag) {
            'supplier_price' => 'закупочная цена',
            'retail_price' => 'розничная цена',
            'in_stock' => 'наличие',
            'stock_quantity' => 'количество',
            'stock_status' => 'статус склада',
            'availability_status' => 'статус на сайте',
            'product_link' => 'привязка товара',
            'link_created' => 'создана связка',
            'link_removed' => 'удалена связка',
            default => $flag,
        };
    }

    private function status(?string $status): string
    {
        return match ($status) {
            'success' => 'Успешно',
            'failed' => 'Ошибка команды',
            'journal_error' => 'Ошибка журнала',
            'running' => 'Выполняется',
            default => $status ?: '—',
        };
    }

    private function money(mixed $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 2, ',', ' ').' BYN';
    }

    private function stock(mixed $value): string
    {
        return $value === null ? '—' : ((bool) $value ? 'есть' : 'нет');
    }

    private function value(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : (string) $value;
    }
}

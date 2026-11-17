<?php

namespace App\Services;

use App\Models\CustomerSelection;
use Carbon\CarbonImmutable;

class SelectionExport
{
    public const SCOPES = ['all' => 'Вся потребность', 'ready' => 'Доступное количество', 'waiting' => 'Недостающее количество'];

    /** Project a saved check without changing demand, prices, selection or reservations. */
    public function prepare(CustomerSelection $selection, string $scope): array
    {
        $items = array_diff_key($selection->items, array_flip($selection->meta['desk_excluded_ids'] ?? []));
        $unchecked = 0;
        if ($scope !== 'all') {
            $items = app(SelectionAvailability::class)->annotate($items, $selection->meta['availability'] ?? []);
            foreach ($items as $id => &$item) {
                $allocation = $item['allocation'];
                if (! $allocation) {
                    $unchecked++;
                }
                $quantity = (int) ($allocation[$scope] ?? 0);
                if ($quantity < 1) {
                    unset($items[$id]);

                    continue;
                }
                $item['quantity'] = $quantity;
                $checked = CarbonImmutable::parse($allocation['checked_at'])->format('d.m.Y H:i');
                $context = self::SCOPES[$scope].' по сохранённой проверке '.$checked.'. Не является резервом.';
                if ($allocation['unknown']) {
                    $context .= ' Остаток требует подтверждения.';
                }
                $item['selection_note'] = trim(($item['selection_note'] ?? '')."\n".$context);
                unset($item['allocation']);
            }
            unset($item);
        }
        $name = $selection->name.($scope === 'all' ? '' : ' · '.self::SCOPES[$scope]);
        $note = $selection->note;
        if ($scope !== 'all') {
            $note = trim(($note ?? '')."\n".self::SCOPES[$scope].' по сохранённой проверке; цены из сохранённой заявки, актуальные цены и наличие проверяются при оформлении. Поиск на экране не меняет состав выгрузки.');
            if ($unchecked > 0) {
                $note .= ' Не включено непроверенных позиций: '.$unchecked.'.';
            }
        }

        return compact('items', 'name', 'note', 'unchecked');
    }
}

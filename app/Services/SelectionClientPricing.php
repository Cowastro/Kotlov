<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SelectionClientPricing
{
    public static function apply(array $item, array $data, int $userId): array
    {
        $mode = $data['pricing_mode'] ?? null;
        if ($mode === null) {
            return $item;
        }
        $previous = $item['client_pricing'] ?? [];
        $base = (int) round((float) ($previous['base_price'] ?? $item['price']) * 100);
        if ($mode === 'reset') {
            $item['price'] = $base / 100;
            $item['selection_note'] = array_key_exists('base_selection_note', $previous) ? $previous['base_selection_note'] : ($item['selection_note'] ?? null);
            unset($item['client_pricing']);

            return $item;
        }
        if ($mode === 'discount' && $base <= 0) {
            throw ValidationException::withMessages(['discount_percent' => 'Исходная цена не указана. Введите цену для клиента вручную.']);
        }
        $discount = $mode === 'discount' ? (int) round((float) $data['discount_percent'] * 100) : null;
        $cents = $mode === 'discount'
            ? (int) round($base * (10000 - $discount) / 10000, 0, PHP_ROUND_HALF_UP)
            : (int) round((float) $data['client_price'] * 100);
        if ($cents < 1 || $cents > 99999999999) {
            throw ValidationException::withMessages(['client_price' => 'Цена для клиента должна быть от 0,01 до 999 999 999,99 BYN.']);
        }
        $item['client_pricing'] = [
            'base_price' => $base / 100,
            'base_selection_note' => array_key_exists('base_selection_note', $previous) ? $previous['base_selection_note'] : ($item['selection_note'] ?? null),
            'mode' => $mode,
            'discount_percent' => $mode === 'discount' ? $discount / 100 : ($base > 0 ? round((1 - $cents / $base) * 100, 2) : null),
            'updated_by' => $userId, 'updated_at' => now()->toIso8601String(),
        ];
        $item['price'] = $cents / 100;
        $item['selection_note'] = 'Цена для клиента согласована в заявке. Условия поставки уточняются.';

        return $item;
    }
}

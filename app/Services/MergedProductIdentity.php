<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Translate retired IDs, never recalculate prices or switch a chosen supplier. */
class MergedProductIdentity
{
    public function map(): array
    {
        return DB::table('products')->whereNotNull('merged_into_product_id')->pluck('merged_into_product_id', 'id')->map(fn ($id) => (int) $id)->all();
    }

    public function items(array $items, array $map): array
    {
        $result = [];
        foreach ($items as $key => $item) {
            $old = (int) ($item['id'] ?? $item['product_id'] ?? $key);
            $id = $map[$old] ?? $old;
            if ($id === $old) {
                $result[$key] = $item;

                continue;
            }
            if (array_key_exists('id', $item)) {
                $item['id'] = $id;
            }
            if (array_key_exists('product_id', $item)) {
                $item['product_id'] = $id;
            }
            $result[$key] = $item;
        }
        // Second pass handles either ordering of old/current lines.
        $normalized = [];
        foreach ($result as $key => $item) {
            $id = $map[(int) $key] ?? $key;
            if (isset($normalized[$id])) {
                $a = $normalized[$id];
                $b = $item;
                unset($a['quantity'], $b['quantity']);
                if ($a != $b) {
                    throw new ConflictHttpException('В подборе две версии одного товара с разными условиями. Данные сохранены; разделите их на отдельные заявки перед объединением.');
                }
                $item['quantity'] += $normalized[$id]['quantity'];
            }
            $normalized[$id] = $item;
        }

        return $normalized;
    }

    public function meta(array $meta, array $map): array
    {
        foreach (['cart_excluded_ids', 'desk_excluded_ids'] as $field) {
            if (isset($meta[$field])) {
                $meta[$field] = array_values(array_unique(array_map(fn ($id) => $map[(int) $id] ?? $id, $meta[$field])));
            }
        }
        foreach (['availability', 'desk_undo'] as $field) {
            if (! isset($meta[$field])) {
                continue;
            }
            if ($field === 'desk_undo') {
                $meta[$field] = $this->items($meta[$field], $map);
            } else {
                foreach ($map as $old => $id) {
                    if (! isset($meta[$field][$old])) {
                        continue;
                    }
                    // A stale stock allocation must be checked again, not added twice.
                    unset($meta[$field][$old], $meta[$field][$id]);
                }
            }
        }

        return $meta;
    }
}

<?php

namespace App\Services\Integrations\OneCBridge;

use App\Models\OneCItem;

class OneCGroupTree
{
    /** Keep ancestors of search results; broken parents and cycles remain visible without infinite recursion. */
    public function nodes(string $search = '', ?string $selected = null): array
    {
        $folders = OneCItem::query()->where('is_folder', true)->where('deletion_mark', false)
            ->orderBy('description')->get(['ref_key', 'description', 'parent_key'])->keyBy('ref_key');
        $byParent = $folders->groupBy(fn (OneCItem $item): string => $folders->has($item->parent_key) ? $item->parent_key : 'root');
        $seen = [];
        $build = function (OneCItem $item) use (&$build, &$seen, $byParent, $search, $selected): ?array {
            if (isset($seen[$item->ref_key])) {
                return null;
            }
            $seen[$item->ref_key] = true;
            $children = [];
            foreach ($byParent->get($item->ref_key, []) as $child) {
                if ($node = $build($child)) {
                    $children[] = $node;
                }
            }
            if ($search !== '' && ! str_contains(mb_strtolower($item->description), mb_strtolower($search)) && ! $children) {
                return null;
            }

            return ['key' => $item->ref_key, 'name' => $item->description, 'children' => $children,
                'selected' => $item->ref_key === $selected, 'open' => $search !== '' || collect($children)->contains(fn (array $child): bool => $child['selected'] || $child['open'])];
        };
        $nodes = [];
        foreach ($byParent->get('root', []) as $folder) {
            if ($node = $build($folder)) {
                $nodes[] = $node;
            }
        }
        foreach ($folders as $folder) {
            if (! isset($seen[$folder->ref_key]) && ($node = $build($folder))) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }
}

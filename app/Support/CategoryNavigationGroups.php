<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Subcategories of a large section in the groups of config('navigation.groups'): the column
 * headings of the mega menu and the blocks on the section page share them. A subcategory
 * no group names lands in «Ещё», so a category added later is never lost from the menu.
 */
final class CategoryNavigationGroups
{
    public static function has(string $sectionSlug): bool
    {
        return (array) config('navigation.groups.'.$sectionSlug, []) !== [];
    }

    /**
     * @param  Collection<int, \App\Models\Category>  $children  the subcategories to show (already filtered to those with cards)
     * @return Collection<int, array{title: string, icon: string, description: string, items: Collection}>
     */
    public static function for(string $sectionSlug, Collection $children): Collection
    {
        $definitions = (array) config('navigation.groups.'.$sectionSlug, []);
        if ($definitions === []) {
            return collect();
        }
        $bySlug = $children->keyBy('slug');
        $placed = [];
        $groups = collect($definitions)->map(function (array $group) use ($bySlug, &$placed): array {
            $items = collect($group['slugs'] ?? [])->map(fn (string $slug) => $bySlug->get($slug))->filter()->values();
            array_push($placed, ...$items->pluck('slug')->all());

            return [
                'title' => (string) $group['title'],
                'icon' => (string) ($group['icon'] ?? 'icon-SquaresFour'),
                'description' => (string) ($group['description'] ?? ''),
                'items' => $items,
            ];
        });
        $rest = $children->reject(fn ($child) => in_array($child->slug, $placed, true))->values();
        if ($rest->isNotEmpty()) {
            $groups->push(['title' => 'Ещё', 'icon' => 'icon-SquaresFour', 'description' => '', 'items' => $rest]);
        }

        return $groups->filter(fn (array $group): bool => $group['items']->isNotEmpty())->values();
    }
}

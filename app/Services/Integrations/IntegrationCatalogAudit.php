<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;
use App\Models\IntegrationSource;

class IntegrationCatalogAudit
{
    /** @return array<string, mixed> */
    public function snapshot(IntegrationSource $source, int $sampleSize = 5): array
    {
        $products = IntegrationProduct::query()
            ->whereBelongsTo($source, 'source')
            ->inStock();

        $ungrouped = (clone $products)->whereNull('integration_category_id');
        $samples = (clone $ungrouped)
            ->orderBy('id')
            ->limit(max(0, $sampleSize))
            ->get(['id', 'external_id', 'name', 'payload'])
            ->map(fn (IntegrationProduct $product): array => [
                'id' => $product->id,
                'external_id' => $product->external_id,
                'name' => $product->name,
                'group_references' => $this->groupReferences($product->payload ?? []),
            ])
            ->all();

        return [
            'source' => $source->code,
            'source_name' => $source->name,
            'groups' => $source->categories()->count(),
            'groups_with_site_category' => $source->categories()->whereNotNull('category_id')->count(),
            'products_in_stock' => (clone $products)->count(),
            'products_grouped' => (clone $products)->whereNotNull('integration_category_id')->count(),
            'products_ungrouped' => (clone $ungrouped)->count(),
            'ungrouped_sample' => $samples,
        ];
    }

    /** @return array<int, string> */
    private function groupReferences(array $payload): array
    {
        $references = [];
        $this->collectGroupReferences($payload, $references, false);

        return array_values(array_unique(array_filter($references)));
    }

    /** @param array<int, string> $references */
    private function collectGroupReferences(mixed $value, array &$references, bool $insideGroups): void
    {
        if (! is_array($value)) {
            if ($insideGroups && is_scalar($value)) {
                $references[] = trim((string) $value);
            }

            return;
        }

        foreach ($value as $key => $child) {
            $isGroups = $insideGroups || mb_strtolower((string) $key) === 'группы';
            if ($isGroups && mb_strtolower((string) $key) === 'ид') {
                foreach ((array) $child as $id) {
                    if (is_scalar($id)) {
                        $references[] = trim((string) $id);
                    }
                }

                continue;
            }

            $this->collectGroupReferences($child, $references, $isGroups);
        }
    }
}

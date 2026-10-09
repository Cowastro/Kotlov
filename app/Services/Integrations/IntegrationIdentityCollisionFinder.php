<?php

namespace App\Services\Integrations;

use App\Models\IntegrationProduct;

class IntegrationIdentityCollisionFinder
{
    /**
     * @return array<int, array{
     *     source_id:int,
     *     identities:array<int, array{kind:string,value:string,normalized_value:string}>,
     *     products:array<int, array{id:int,external_id:string,name:?string}>
     * }>
     */
    public function find(?int $sourceId = null): array
    {
        $groups = [];

        IntegrationProduct::query()
            ->when($sourceId, fn ($query) => $query->where('integration_source_id', $sourceId))
            ->where(function ($query): void {
                $query->whereNotNull('external_sku')
                    ->orWhereNotNull('barcode');
            })
            ->select(['id', 'integration_source_id', 'external_id', 'external_sku', 'barcode', 'name'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$groups): void {
                foreach ($products as $product) {
                    $this->add($groups, $product, 'sku', $product->external_sku, 4);
                    $this->add($groups, $product, 'barcode', $product->barcode, 6);
                }
            });

        return collect($groups)
            ->filter(function (array $group): bool {
                return collect($group['products'])
                    ->pluck('external_id')
                    ->unique()
                    ->count() > 1;
            })
            ->map(function (array $group): array {
                $group['products'] = array_values($group['products']);

                return $group;
            })
            ->groupBy(function (array $group): string {
                $productIds = collect($group['products'])->pluck('id')->sort()->implode(',');

                return $group['source_id'].'|'.$productIds;
            })
            ->map(function ($matchingGroups): array {
                $first = $matchingGroups->first();

                return [
                    'source_id' => $first['source_id'],
                    'identities' => $matchingGroups
                        ->map(fn (array $group): array => [
                            'kind' => $group['kind'],
                            'value' => $group['value'],
                            'normalized_value' => $group['normalized_value'],
                        ])
                        ->values()
                        ->all(),
                    'products' => $first['products'],
                ];
            })
            ->values()
            ->all();
    }

    /** @param array<string, array<string, mixed>> $groups */
    private function add(array &$groups, IntegrationProduct $product, string $kind, mixed $value, int $minimumLength): void
    {
        $displayValue = trim((string) $value);
        $normalized = $this->normalize($displayValue);

        if (mb_strlen($normalized) < $minimumLength) {
            return;
        }

        $key = $product->integration_source_id.'|'.$kind.'|'.$normalized;
        $groups[$key] ??= [
            'source_id' => (int) $product->integration_source_id,
            'kind' => $kind,
            'value' => $displayValue,
            'normalized_value' => $normalized,
            'products' => [],
        ];
        $groups[$key]['products'][$product->id] = [
            'id' => (int) $product->id,
            'external_id' => (string) $product->external_id,
            'name' => $product->name,
        ];
    }

    private function normalize(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
    }
}

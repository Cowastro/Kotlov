<?php

namespace App\Services\Marketplaces;

use Illuminate\Support\Collection;

/** Exact identities only: no fuzzy model names or removal of model/size suffixes. */
class DealByPricePlan
{
    public function fingerprint(array $remote): string
    {
        return hash('sha256', json_encode([$remote['sku'] ?? '', $remote['name'] ?? '', $remote['external_id'] ?? ''], JSON_UNESCAPED_UNICODE));
    }

    public function build(Collection $products, Collection $remote, array $links = []): Collection
    {
        $byId = $products->keyBy('id');
        $keys = [];
        $names = [];
        foreach ($products as $product) {
            $articles = $product->relationLoaded('supplierProducts')
                ? $product->supplierProducts->pluck('supplier_article')->all() : [];
            if ($product->relationLoaded('integrationProducts')) {
                foreach ($product->integrationProducts as $linked) {
                    $articles[] = $linked->external_sku;
                    $articles[] = $linked->barcode;
                }
            }
            foreach (array_unique([$product->sku, $product->slug, ...$articles]) as $value) {
                if ($key = $this->normalize($value)) {
                    $keys[$key][(int) $product->id] = true;
                }
            }
            if ($name = $this->normalize($product->name)) {
                $names[$name][(int) $product->id] = true;
            }
        }

        $rows = $remote->map(function (array $item) use ($byId, $keys, $names, $links): array {
            $id = (int) ($item['id'] ?? 0);
            $row = ['deal_id' => $id, 'deal_name' => $item['name'] ?? '', 'deal_sku' => $item['sku'] ?? '',
                'local_id' => null, 'local_slug' => null, 'method' => '', 'old_price' => $item['price'] ?? null,
                'new_price' => null, 'reason' => '', 'api_currency' => $item['_api_currency'] ?? $item['currency'] ?? null,
                'fingerprint' => $this->fingerprint($item)];
            $candidates = [];
            if ($id <= 0) {
                return array_replace($row, ['reason' => 'invalid_remote_id']);
            }
            if (isset($links[$id])) {
                if (($links[$id]['fingerprint'] ?? '') !== $row['fingerprint']) {
                    return array_replace($row, ['reason' => 'changed_remote_identity']);
                }
                $candidates[(int) $links[$id]['local_id']] = true;
                $row['method'] = 'saved';
            }
            if (preg_match('/^kotlov-(\d+)$/i', (string) ($item['external_id'] ?? ''), $match)) {
                $candidates[(int) $match[1]] = true;
                $row['method'] = $row['method'] ?: 'external_id';
            }
            $sku = $this->normalize($item['sku'] ?? '');
            $name = $this->normalize($item['name'] ?? '');
            foreach (($keys[$sku] ?? []) as $localId => $_) {
                $candidates[$localId] = true;
                $row['method'] = $row['method'] ?: 'sku';
            }
            foreach (($names[$name] ?? []) as $localId => $_) {
                $candidates[$localId] = true;
                $row['method'] = $row['method'] ?: 'name';
            }
            if (count($candidates) !== 1) {
                return array_replace($row, ['reason' => $candidates ? 'conflicting_identity' : 'unmatched']);
            }
            $product = $byId->get(array_key_first($candidates));
            if (! $product) {
                return array_replace($row, ['reason' => 'missing_local_product']);
            }
            $row['local_id'] = (int) $product->id;
            $row['local_slug'] = $product->slug;
            $row['new_price'] = round((float) $product->price, 2);
            if (isset($links[$id]['local_slug']) && $links[$id]['local_slug'] !== $product->slug) {
                $row['reason'] = 'changed_local_identity';
            } elseif (! $product->is_active || $product->is_archived || $product->requires_price_confirmation || ! is_finite($row['new_price']) || $row['new_price'] <= 0) {
                $row['reason'] = 'ineligible_local_product';
            } elseif (strtoupper((string) ($item['currency'] ?? '')) !== 'BYN') {
                $row['reason'] = 'unsupported_currency';
            } elseif (! is_numeric($item['price'] ?? null) || ! is_finite((float) $item['price'])) {
                $row['reason'] = 'invalid_remote_price';
            } elseif (! empty($item['discount'])) {
                // Do not accidentally apply an existing sbg.by discount on top of the KOTLOV retail price.
                $row['reason'] = 'remote_discount';
            } else {
                $row['reason'] = abs((float) $item['price'] - $row['new_price']) < 0.005 ? 'unchanged' : 'update';
            }

            return $row;
        });

        $counts = $rows->whereNotNull('local_id')->countBy('local_id');
        $remoteCounts = $rows->countBy('deal_id');

        return $rows->map(function (array $row) use ($counts, $remoteCounts): array {
            if (($remoteCounts[$row['deal_id']] ?? 0) > 1 || ($row['local_id'] && ($counts[$row['local_id']] ?? 0) > 1)) {
                $row['reason'] = 'duplicate_mapping';
            }

            return $row;
        });
    }

    private function normalize(mixed $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $value) ?? ''));
    }
}

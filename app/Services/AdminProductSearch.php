<?php

namespace App\Services;

use App\Models\OneCItem;
use App\Services\Suppliers\TdKomplektFeed;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Staff-only identifiers must not be tokenized into unrelated supplier SKU suffixes. */
class AdminProductSearch
{
    public function apply(Builder $query, string $search): Builder
    {
        $search = mb_substr(trim($search), 0, 200);
        if ($search === '') {
            return $query;
        }
        if ($code = $this->oneCCode($search)) {
            return $query->whereIn('products.id', $this->oneCIds([$code, '00'.$code]));
        }
        $variants = array_values(array_unique([$search, mb_strtoupper($search), mb_strtolower($search)]));
        $cards = DB::table('products')->whereIn('sku', $variants)->get(['id', 'merged_into_product_id']);
        $ids = $cards->map(fn ($card): int => (int) ($card->merged_into_product_id ?: $card->id))->all();
        if ($ids !== []) {
            return $query->whereIn('products.id', array_values(array_unique($ids)));
        }
        // Ordinary words keep the established word, layout and typo search. Exact identifiers
        // use existing B-tree indexes and resolve once, before any expensive text matching.
        if (preg_match('/\d/u', $search) && mb_strlen($search) <= 100) {
            $key = TdKomplektFeed::key($search);
            $keys = array_values(array_unique([...$variants, $key, mb_strtolower($key)]));
            $supplierIds = DB::table('supplier_products')->whereNotNull('product_id')
                ->where(fn ($q) => $q->whereNull('match_status')->orWhere('match_status', '!=', 'ignored'))
                ->where(fn ($q) => $q->whereIn('supplier_article', $keys)->orWhereIn('supplier_article_normalized', $keys)
                    ->orWhereIn('supplier_article_compact', $keys)->orWhereIn('supplier_catalog_code', $keys))
                ->distinct()->limit(300)->pluck('product_id')->all();
            $integrationIds = DB::table('integration_products')->whereNotNull('product_id')->where('match_status', 'matched')
                ->whereIn('external_sku', $keys)->distinct()->limit(300)->pluck('product_id')->all();
            $ids = array_merge($ids, $supplierIds, $integrationIds, $this->oneCIds($keys, article: true));
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids !== []) {
            return $query->whereIn('products.id', $ids);
        }

        return app(StorefrontProductSearch::class)->applyForResults($query, $search, true);
    }

    public function oneCCode(string $search): ?string
    {
        $search = preg_replace('/[\s\x{00a0}]+/u', '', mb_strtoupper(trim($search)));
        $search = str_replace(['–', '—', '‑', '−'], '-', $search);
        if (! preg_match('/^(?:00)?(?:УТ|UT)-?(\d{1,8})$/u', $search, $match)) {
            return null;
        }

        return 'УТ-'.str_pad($match[1], 8, '0', STR_PAD_LEFT);
    }

    public function unlinkedOneCCode(string $search): ?string
    {
        $code = $this->oneCCode($search);
        if ($code === null) {
            return null;
        }

        $ids = $this->oneCIds([$code, '00'.$code], knownItem: $knownItem);

        return $knownItem && $ids === [] ? $code : null;
    }

    /** Both the nomenclature cache and characteristic-specific integration links are authoritative. */
    private function oneCIds(array $keys, bool $article = false, ?bool &$knownItem = null): array
    {
        $items = OneCItem::query()->where('is_folder', false)->where('deletion_mark', false)
            ->whereIn($article ? 'article' : 'code', $keys)->limit(300)->get(['ref_key', 'product_id']);
        $knownItem = $items->isNotEmpty();
        $refs = $items->pluck('ref_key')->all();
        $sourceIds = DB::table('integration_sources')->where('code', 'onec')->pluck('id')->all();
        $ids = $items->pluck('product_id')->filter()->all();
        if ($sourceIds !== []) {
            $links = DB::table('integration_products')->whereIn('integration_source_id', $sourceIds)
                ->where('match_status', 'matched')->whereNotNull('product_id')
                ->where(function ($q) use ($article, $keys, $refs): void {
                    $q->whereIn($article ? 'external_sku' : 'external_code', $keys);
                    foreach ($refs as $ref) {
                        $q->orWhere('external_id', $ref)->orWhere('external_id', 'like', $ref.'#%');
                    }
                })->distinct()->limit(300)->pluck('product_id')->all();
            $ids = array_merge($ids, $links);
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}

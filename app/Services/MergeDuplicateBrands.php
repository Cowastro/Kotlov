<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MergeDuplicateBrands
{
    public function run(): array
    {
        return DB::transaction(function (): array {
            $groups = DB::table('brands')->orderBy('id')->lockForUpdate()->get()->groupBy(fn ($b) => Brand::nameKey($b->name));
            $result = [];
            foreach ($groups as $key => $brands) {
                if ($key === '' || $brands->count() < 2) {
                    continue;
                }
                $canonical = $brands->sortBy(fn ($b) => [preg_match('/-\d+$/', $b->slug) ? 1 : 0, $b->id])->first();
                $ids = $brands->pluck('id');
                $duplicates = $ids->reject(fn ($id) => $id === $canonical->id)->values();
                $products = DB::table('products')->whereIn('brand_id', $ids)->get();
                $links = DB::table('brand_supplier')->whereIn('brand_id', $ids)->get();
                $path = 'private/brand-merges/exact-'.$canonical->id.'-'.now()->format('Ymd-His-u').'.json';
                if (! Storage::disk('local')->put($path, json_encode(['brands' => $brands, 'products' => $products, 'supplier_links' => $links], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Не удалось сохранить резервную копию брендов.');
                }
                $updates = ['name' => Brand::displayName($canonical->name), 'is_active' => $brands->contains(fn ($b) => (bool) $b->is_active), 'is_featured' => $brands->contains(fn ($b) => (bool) $b->is_featured)];
                foreach (['h1', 'logo', 'country', 'producer', 'content', 'meta_title', 'meta_keywords', 'meta_description'] as $field) {
                    if (blank($canonical->$field)) {
                        $updates[$field] = $brands->pluck($field)->first(fn ($value) => filled($value));
                    }
                }
                foreach ($links as $link) {
                    DB::table('brand_supplier')->insertOrIgnore(['brand_id' => $canonical->id, 'supplier_id' => $link->supplier_id, 'created_at' => $link->created_at, 'updated_at' => $link->updated_at]);
                }
                foreach ($duplicates as $id) {
                    $old = $brands->firstWhere('id', $id);
                    DB::table('brand_aliases')->where('canonical_id', $id)->update(['canonical_id' => $canonical->id]);
                    DB::table('brand_aliases')->updateOrInsert(['original_id' => $id], ['canonical_id' => $canonical->id, 'slug' => $old->slug]);
                    DB::table('redirects')->updateOrInsert(['from_url' => '/brands/'.$old->slug], ['to_url' => '/brands/'.$canonical->slug, 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                // Only this foreign key changes: no prices, stock, SKU, specs or timestamps.
                DB::table('products')->whereIn('brand_id', $duplicates)->update(['brand_id' => $canonical->id]);
                DB::table('brand_supplier')->whereIn('brand_id', $duplicates)->delete();
                DB::table('brands')->whereIn('id', $duplicates)->delete();
                DB::table('brands')->where('id', $canonical->id)->update($updates);
                $result[] = ['name' => $updates['name'], 'canonical_id' => $canonical->id, 'removed_ids' => $duplicates->all(), 'products' => $products->count(), 'backup' => $path];
            }

            return $result;
        });
    }
}

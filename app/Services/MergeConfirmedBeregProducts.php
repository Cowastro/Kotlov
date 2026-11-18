<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Explicit reviewed pairs only. Retain old rows as non-catalog identities. */
class MergeConfirmedBeregProducts
{
    public function run(array $pairs): array
    {
        $references = [];
        foreach (Schema::getTables() as $table) {
            if ($table['name'] === 'products') {
                continue;
            }
            foreach (Schema::getColumns($table['name']) as $column) {
                if (in_array($column['name'], ['product_id', 'analog_product_id', 'equipment_product_id', 'selected_product_id', 'matched_product_id'], true)) {
                    $references[] = [$table['name'], $column['name']];
                }
            }
        }

        return DB::transaction(function () use ($pairs, $references): array {
            $result = [];
            foreach ($pairs as $pair) {
                $old = DB::table('products')->where('id', $pair['duplicate'])->lockForUpdate()->first();
                $canonical = DB::table('products')->where('id', $pair['canonical'])->lockForUpdate()->first();
                if (! $old && ! $canonical) {
                    continue;
                } // Other installations do not have these reviewed IDs.
                if ($old && (int) $old->merged_into_product_id === (int) $pair['canonical']) {
                    continue;
                }
                if (! $old || ! $canonical || $canonical->merged_into_product_id) {
                    throw new RuntimeException('Состав подтверждённой пары изменён.');
                }
                foreach ([$old, $canonical] as $card) {
                    $specs = json_decode($card->specs ?: '[]', true, 512, JSON_THROW_ON_ERROR);
                    $maker = collect($specs)->firstWhere('key', 'Код производителя')['value'] ?? '';
                    if ($card->sku !== $pair['sku'] || $this->name($card->name) !== $this->name($pair['name']) || trim($maker) !== $pair['maker'] || $old->brand_id !== $canonical->brand_id) {
                        throw new RuntimeException('Идентификация пары '.$pair['sku'].' изменилась. Объединение остановлено.');
                    }
                }
                $map = [(int) $old->id => (int) $canonical->id];
                $backup = ['pair' => $pair, 'products' => [$old, $canonical], 'references' => [], 'json' => []];
                $category = DB::table('categories')->where('id', $canonical->category_id)->value('slug');
                $oldCategory = DB::table('categories')->where('id', $old->category_id)->value('slug');
                if (! $category || ! $oldCategory) {
                    throw new RuntimeException('Нет категории подтверждённой пары.');
                }
                $backup['redirects'] = DB::table('redirects')->whereIn('from_url', ['/'.$oldCategory.'/'.$old->slug, '/'.$old->slug])->get();
                foreach ($references as [$table, $column]) {
                    $rows = DB::table($table)->whereIn($column, [$old->id, $canonical->id])->lockForUpdate()->get();
                    if ($rows->isNotEmpty()) {
                        $backup['references'][$table.'.'.$column] = $rows;
                    }
                }
                $backup['product_pricing_rules'] = DB::table('b2b_pricing_rules')->where('scope_type', 'product')->whereIn('scope_id', [$old->id, $canonical->id])->lockForUpdate()->get();
                $jsonUpdates = [];
                foreach (['customer_carts', 'customer_selections', 'b2b_saved_carts'] as $table) {
                    foreach (DB::table($table)->where(function ($query) use ($table, $old): void {
                        $query->where('items', 'like', '%'.$old->id.'%');
                        if ($table !== 'b2b_saved_carts') {
                            $query->orWhere('meta', 'like', '%'.$old->id.'%');
                        }
                    })->orderBy('id')->lockForUpdate()->get() as $row) {
                        $items = json_decode($row->items ?: '[]', true, 512, JSON_THROW_ON_ERROR);
                        $meta = isset($row->meta) ? json_decode($row->meta ?: '[]', true, 512, JSON_THROW_ON_ERROR) : [];
                        $identity = app(MergedProductIdentity::class);
                        $newItems = $identity->items($items, $map);
                        $newMeta = $identity->meta($meta, $map);
                        if ($newItems === $items && $newMeta === $meta) {
                            continue;
                        }
                        $backup['json'][$table][] = $row;
                        $update = ['items' => json_encode($newItems, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];
                        if (isset($row->meta)) {
                            $update['meta'] = json_encode($newMeta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                        }
                        if (isset($row->revision)) {
                            $update['revision'] = $row->revision + 1;
                        }
                        $jsonUpdates[] = [$table, $row->id, $update];
                    }
                }
                $path = 'private/product-merges/bereg-'.$old->id.'-'.$canonical->id.'-'.now()->format('Ymd-His-u').'.json';
                if (! Storage::disk('local')->put($path, json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))) {
                    throw new RuntimeException('Не удалось сохранить резервную копию.');
                }

                // Resolve only equal/empty duplicate attribute values. Never ignore a unique-key error.
                foreach (DB::table('product_attribute_values')->where('product_id', $old->id)->get() as $attribute) {
                    $existing = DB::table('product_attribute_values')->where('product_id', $canonical->id)->where('attribute_id', $attribute->attribute_id)->first();
                    if (! $existing) {
                        continue;
                    }
                    $fill = [];
                    foreach (['option_id', 'value', 'is_checked'] as $field) {
                        if ($existing->$field !== null && $existing->$field !== '' && $attribute->$field !== null && $attribute->$field !== '' && (string) $existing->$field !== (string) $attribute->$field) {
                            throw new RuntimeException('Конфликт характеристики '.$pair['sku'].' / '.$attribute->attribute_id);
                        }
                        if (($existing->$field === null || $existing->$field === '') && $attribute->$field !== null) {
                            $fill[$field] = $attribute->$field;
                        }
                    }
                    if ($fill) {
                        DB::table('product_attribute_values')->where('id', $existing->id)->update($fill);
                    }
                    DB::table('product_attribute_values')->where('id', $attribute->id)->delete();
                }
                foreach ($references as [$table, $column]) {
                    // Query-builder updates intentionally preserve timestamps, prices, quantities,
                    // offer IDs and captured order procurement snapshots (no model save hooks).
                    DB::table($table)->where($column, $old->id)->update([$column => $canonical->id]);
                }
                DB::table('b2b_pricing_rules')->where('scope_type', 'product')->where('scope_id', $old->id)->update(['scope_id' => $canonical->id]);
                foreach ($jsonUpdates as [$table, $id, $update]) {
                    DB::table($table)->where('id', $id)->update($update);
                }
                $updates = ['is_active' => (bool) ($old->is_active || $canonical->is_active)];
                foreach (['content', 'short_description', 'images', 'service_info', 'documents', 'video_url', 'content_source_url', 'meta_title', 'meta_description'] as $field) {
                    if (in_array($canonical->$field, [null, '', '[]'], true) && ! in_array($old->$field, [null, '', '[]'], true)) {
                        $updates[$field] = $old->$field;
                    }
                }
                $specs = json_decode($canonical->specs ?: '[]', true);
                foreach (json_decode($old->specs ?: '[]', true) as $spec) {
                    $existing = collect($specs)->firstWhere('key', $spec['key']);
                    if ($existing && filled($existing['value'] ?? null) && filled($spec['value'] ?? null) && trim((string) $existing['value']) !== trim((string) $spec['value'])) {
                        throw new RuntimeException('Конфликт спецификации '.$pair['sku']);
                    }
                    if (! $existing) {
                        $specs[] = $spec;
                    }
                }
                $updates['specs'] = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                DB::table('products')->where('id', $canonical->id)->update($updates);
                DB::table('products')->where('id', $old->id)->update(['is_active' => false, 'is_archived' => true, 'merged_into_product_id' => $canonical->id]);
                foreach (['/'.$oldCategory.'/'.$old->slug, '/'.$old->slug] as $url) {
                    DB::table('redirects')->updateOrInsert(['from_url' => $url], ['to_url' => '/'.$category.'/'.$canonical->slug, 'status_code' => 301, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                $result[] = ['old' => $old->id, 'canonical' => $canonical->id, 'backup' => $path];
            }

            return $result;
        }, 3);
    }

    private function name(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $value)));
    }
}

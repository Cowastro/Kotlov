<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const WAREHOUSE = 'СанБизнесГруп';

    public function up(): void
    {
        $categoryId = DB::table('categories')->where('slug', 'schetchiki-gaza')->value('id');
        if (! $categoryId) {
            return;
        }

        $productIds = DB::table('products')->where('category_id', $categoryId)->pluck('id');
        if ($productIds->isEmpty()) {
            return;
        }

        $eliconIds = DB::table('suppliers')
            ->where(fn ($query) => $query
                ->where('code', 'elicon')
                ->orWhereRaw('LOWER(name) = ?', ['эликон']))
            ->pluck('id');

        DB::transaction(function () use ($productIds, $eliconIds): void {
            if (Schema::hasTable('supplier_products')) {
                if ($eliconIds->isNotEmpty()) {
                    DB::table('supplier_products')
                        ->whereIn('product_id', $productIds)
                        ->whereIn('supplier_id', $eliconIds)
                        ->delete();
                }

                DB::table('supplier_products')
                    ->whereIn('product_id', $productIds)
                    ->update([
                        'warehouse_name' => self::WAREHOUSE,
                        'updated_at' => now(),
                    ]);
            }

            if (Schema::hasTable('supplier_product_mappings')) {
                DB::table('supplier_product_mappings')
                    ->whereIn('product_id', $productIds)
                    ->where('supplier_code', 'elicon')
                    ->delete();
            }

            if ($eliconIds->isNotEmpty() && Schema::hasTable('integration_products') && Schema::hasTable('integration_sources')) {
                $sourceIds = DB::table('integration_sources')->whereIn('supplier_id', $eliconIds)->pluck('id');
                if ($sourceIds->isNotEmpty()) {
                    DB::table('integration_products')
                        ->whereIn('product_id', $productIds)
                        ->whereIn('integration_source_id', $sourceIds)
                        ->update([
                            'product_id' => null,
                            'match_status' => 'unmatched',
                            'match_method' => null,
                            'match_confidence' => null,
                            'matched_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            }

            $this->cleanProductCopy($productIds->all());
            $this->normalizeBelomoSource();

            if ($eliconIds->isNotEmpty() && Schema::hasTable('suppliers')) {
                DB::table('suppliers')->whereIn('id', $eliconIds)->update([
                    'is_active' => false,
                    'notes' => 'Старая конкурентная привязка счетчиков удалена. Закупка ведется напрямую у БелОМО, приемка — склад СанБизнесГруп.',
                    'updated_at' => now(),
                ]);
            }
        });

        Cache::forget('home:data');
        Cache::forget('home:data:v4');
    }

    public function down(): void
    {
        // Старые конкурентные связи намеренно не восстанавливаются.
    }

    private function cleanProductCopy(array $productIds): void
    {
        DB::table('products')
            ->whereIn('id', $productIds)
            ->orderBy('id')
            ->get(['id', 'content', 'short_description', 'meta_keywords', 'meta_description', 'specs'])
            ->each(function (object $product): void {
                $specs = $this->decodeJson($product->specs);
                foreach (['supplier', 'Поставщик'] as $key) {
                    if (isset($specs[$key]) && preg_match('/эликон|elicon/ui', (string) $specs[$key])) {
                        unset($specs[$key]);
                    }
                }
                foreach (['source_url', 'Источник'] as $key) {
                    if (isset($specs[$key]) && preg_match('/elicon/ui', (string) $specs[$key])) {
                        unset($specs[$key]);
                    }
                }
                unset($specs['source_wp_id'], $specs['price_scraped_at']);

                DB::table('products')->where('id', $product->id)->update([
                    'content' => $this->cleanText($product->content),
                    'short_description' => $this->cleanText($product->short_description),
                    'meta_keywords' => $this->cleanKeywords($product->meta_keywords),
                    'meta_description' => $this->cleanText($product->meta_description),
                    'specs' => json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            });
    }

    private function normalizeBelomoSource(): void
    {
        if (! Schema::hasTable('integration_sources')) {
            return;
        }

        $source = DB::table('integration_sources')->where('code', 'belomo_wholesale')->first();
        if (! $source) {
            return;
        }

        $settings = $this->decodeJson($source->settings ?? null);
        $settings['warehouse_label'] = self::WAREHOUSE;
        DB::table('integration_sources')->where('id', $source->id)->update([
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    private function cleanText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = preg_replace('/Актуализировано по каталогу\s+elicon\.by[^.]*\.?/ui', '', $value) ?? $value;
        $value = preg_replace('/Артикул поставщика\s+Эликон\s*:/ui', 'Артикул БелОМО:', $value) ?? $value;
        $value = preg_replace('/\bЭликон\b|\belicon(?:\.by)?\b/ui', '', $value) ?? $value;

        return trim(preg_replace('/\s{2,}/u', ' ', $value) ?? $value);
    }

    private function cleanKeywords(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return collect(explode(',', $value))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter(fn (string $keyword): bool => $keyword !== '' && ! preg_match('/эликон|elicon/ui', $keyword))
            ->implode(', ');
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
